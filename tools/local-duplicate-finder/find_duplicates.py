#!/usr/bin/env python3
"""Find duplicate songs in a local music folder by artist+title tags.

Usage:
    python find_duplicates.py "C:\\Users\\me\\Music" --report duplicates.csv
    python find_duplicates.py "C:\\Users\\me\\Music" --move-to "C:\\Users\\me\\Music\\_duplicates"
    python find_duplicates.py "C:\\Users\\me\\Music" --fuzzy --fuzzy-threshold 0.9

Never deletes anything. With --move-to it moves the lower-quality copy of
each duplicate group into a separate folder (preserving the original
sub-path) so you can review before deleting for good.
"""

import argparse
import csv
import re
import shutil
import sys
import unicodedata
from difflib import SequenceMatcher
from pathlib import Path

try:
    from mutagen import File as MutagenFile
except ImportError:
    sys.exit(
        "Chyba: chyba kniznica 'mutagen'.\n"
        "Nainstaluj ju prikazom: pip install -r requirements.txt"
    )

AUDIO_EXTENSIONS = {".mp3", ".flac", ".wav", ".m4a", ".aac", ".ogg", ".wma", ".opus"}

# Preferred format order when picking which copy to keep (higher = better).
FORMAT_RANK = {".flac": 5, ".wav": 5, ".m4a": 3, ".aac": 3, ".ogg": 2, ".opus": 2, ".mp3": 1, ".wma": 0}

FILENAME_ARTIST_TITLE_RE = re.compile(r"^(?P<artist>.+?)\s*-\s*(?P<title>.+?)$")
BRACKETED_RE = re.compile(r"[\(\[][^)\]]*[\)\]]")
NON_ALNUM_RE = re.compile(r"[^a-z0-9 ]+")
WHITESPACE_RE = re.compile(r"\s+")


class Track:
    def __init__(self, path):
        self.path = path
        self.artist = ""
        self.title = ""
        self.bitrate = 0
        self.duration = 0.0
        self.size = path.stat().st_size
        self.ext = path.suffix.lower()
        self._read_tags()

    def _read_tags(self):
        try:
            audio = MutagenFile(self.path, easy=True)
        except Exception:
            audio = None

        if audio is not None and audio.tags:
            self.artist = (audio.tags.get("artist", [""])[0] or "").strip()
            self.title = (audio.tags.get("title", [""])[0] or "").strip()

        if not self.artist or not self.title:
            match = FILENAME_ARTIST_TITLE_RE.match(self.path.stem)
            if match:
                self.artist = self.artist or match.group("artist").strip()
                self.title = self.title or match.group("title").strip()
            elif not self.title:
                self.title = self.path.stem.strip()

        if audio is not None and getattr(audio, "info", None) is not None:
            self.bitrate = getattr(audio.info, "bitrate", 0) or 0
            self.duration = getattr(audio.info, "length", 0.0) or 0.0

    def quality_key(self):
        return (FORMAT_RANK.get(self.ext, 0), self.bitrate, self.size)

    def display_key(self):
        return f"{self.artist} - {self.title}" if self.artist else self.title


def normalize(text):
    text = unicodedata.normalize("NFKD", text)
    text = "".join(ch for ch in text if not unicodedata.combining(ch))
    text = text.lower()
    text = BRACKETED_RE.sub(" ", text)
    text = NON_ALNUM_RE.sub(" ", text)
    text = WHITESPACE_RE.sub(" ", text).strip()
    return text


def normalized_key(track):
    return f"{normalize(track.artist)}::{normalize(track.title)}"


def scan_folder(folder, extensions):
    for path in folder.rglob("*"):
        if path.is_file() and path.suffix.lower() in extensions:
            yield path


def group_exact(tracks):
    groups = {}
    for track in tracks:
        groups.setdefault(normalized_key(track), []).append(track)
    return {key: members for key, members in groups.items() if len(members) > 1}


def merge_fuzzy(groups, singles, threshold):
    """Merge lone tracks into existing groups (or new groups) by similarity
    of their normalized artist+title, to catch near-duplicate tags."""
    representative = {key: normalize(members[0].display_key()) for key, members in groups.items()}
    remaining = []

    for track in singles:
        norm = normalize(track.display_key())
        best_key, best_ratio = None, 0.0
        for key, rep_norm in representative.items():
            ratio = SequenceMatcher(None, norm, rep_norm).ratio()
            if ratio > best_ratio:
                best_key, best_ratio = key, ratio
        if best_key is not None and best_ratio >= threshold:
            groups[best_key].append(track)
        else:
            remaining.append(track)

    # Pairwise-merge whatever is left over into new fuzzy groups.
    used = [False] * len(remaining)
    for i, track_a in enumerate(remaining):
        if used[i]:
            continue
        norm_a = normalize(track_a.display_key())
        cluster = [track_a]
        for j in range(i + 1, len(remaining)):
            if used[j]:
                continue
            norm_b = normalize(remaining[j].display_key())
            if SequenceMatcher(None, norm_a, norm_b).ratio() >= threshold:
                cluster.append(remaining[j])
                used[j] = True
        if len(cluster) > 1:
            used[i] = True
            groups[f"fuzzy::{normalize(track_a.display_key())}"] = cluster

    return groups


def write_report(groups, report_path):
    with open(report_path, "w", newline="", encoding="utf-8-sig") as fh:
        writer = csv.writer(fh)
        writer.writerow(["group", "status", "path", "size_mb", "bitrate_kbps", "duration_sec", "format"])
        for group_id, (_key, members) in enumerate(sorted(groups.items()), start=1):
            best = max(members, key=lambda t: t.quality_key())
            for track in members:
                status = "KEEP" if track is best else "DUPLICATE"
                writer.writerow([
                    group_id,
                    status,
                    str(track.path),
                    round(track.size / (1024 * 1024), 2),
                    round(track.bitrate / 1000) if track.bitrate else "",
                    round(track.duration, 1),
                    track.ext.lstrip("."),
                ])


def move_duplicates(groups, source_root, move_to):
    moved = 0
    for members in groups.values():
        best = max(members, key=lambda t: t.quality_key())
        for track in members:
            if track is best:
                continue
            rel_path = track.path.relative_to(source_root)
            dest = Path(move_to) / rel_path
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.move(str(track.path), str(dest))
            moved += 1
    return moved


def main():
    parser = argparse.ArgumentParser(description="Find duplicate songs in a music folder.")
    parser.add_argument("folder", help="Priecinok, ktory sa ma prehladat (rekurzivne).")
    parser.add_argument("--report", default="duplicates.csv", help="Cesta k CSV reportu (default: duplicates.csv).")
    parser.add_argument("--move-to", default=None, help="Ak zadane, duplicity (horsia kvalita) sa presunu sem namiesto zmazania.")
    parser.add_argument("--fuzzy", action="store_true", help="Zapne aj priblizne porovnavanie nazvov (chyty preklepy/varianty tagov).")
    parser.add_argument("--fuzzy-threshold", type=float, default=0.92, help="Prah podobnosti pre --fuzzy, 0-1 (default: 0.92).")
    args = parser.parse_args()

    folder = Path(args.folder).expanduser().resolve()
    if not folder.is_dir():
        sys.exit(f"Priecinok neexistuje: {folder}")

    print(f"Prehladavam: {folder}")
    tracks = [Track(p) for p in scan_folder(folder, AUDIO_EXTENSIONS)]
    print(f"Najdenych audio suborov: {len(tracks)}")

    groups = group_exact(tracks)

    if args.fuzzy:
        grouped_paths = {t.path for members in groups.values() for t in members}
        singles = [t for t in tracks if t.path not in grouped_paths]
        groups = merge_fuzzy(groups, singles, args.fuzzy_threshold)

    total_duplicate_files = sum(len(members) - 1 for members in groups.values())
    print(f"Skupin duplicit: {len(groups)}")
    print(f"Suborov oznacenych ako duplicitne (na presun/zmazanie): {total_duplicate_files}")

    write_report(groups, args.report)
    print(f"Report ulozeny do: {args.report}")

    if args.move_to:
        moved = move_duplicates(groups, folder, args.move_to)
        print(f"Presunutych suborov do '{args.move_to}': {moved}")
    elif total_duplicate_files:
        print("Tip: spusti znova s --move-to <priecinok>, aby sa duplicity presunuli (nie zmazali) na kontrolu.")


if __name__ == "__main__":
    main()
