#!/usr/bin/env python3
"""Zo zoznamov súborov (find ... > zoznam.txt) spraví katalóg bez duplicít.

Použitie: python3 build_catalog.py zoznam1.txt zoznam2.txt -o data/catalog.csv
Berie len: RadioLibrary/_azuracast_mirror (bez Vyradene) a New Archive/{Dance,CzechoSlovak}.
"""
import argparse
import csv
import re
import sys

from common import parse_filename, norm, song_key

SOURCES = [
    (re.compile(r"/RadioLibrary/_azuracast_mirror/[^/]+$"), "mirror", ""),
    (re.compile(r"/New Archive/Dance/[^/]+$"), "archive", "dance"),
    (re.compile(r"/New Archive/CzechoSlovak/[^/]+$"), "archive", "czsk"),
    (re.compile(r"/New Archive/Pop/[^/]+$"), "archive", "pop"),
]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("lists", nargs="+")
    ap.add_argument("-o", "--out", default="data/catalog.csv")
    a = ap.parse_args()
    rows = {}
    for lst in a.lists:
        with open(lst, encoding="utf-8", errors="replace") as f:
            for line in f:
                path = line.rstrip("\n")
                if not path.lower().endswith((".mp3", ".flac", ".m4a", ".wav")):
                    continue
                for rx, src, pool in SOURCES:
                    if rx.search(path):
                        break
                else:
                    continue
                p = parse_filename(path)
                fid = norm(p["artist"]) + "|" + norm(p["title"])  # presná verzia
                r = rows.get(fid)
                if r is None:
                    r = dict(p, file_id=fid, song_key=song_key(p), in_mirror=0,
                             in_archive=0, pool="", path_mirror="", path_archive="")
                    rows[fid] = r
                if src == "mirror":
                    r["in_mirror"] = 1
                    r["path_mirror"] = r["path_mirror"] or path
                else:
                    r["in_archive"] = 1
                    r["path_archive"] = r["path_archive"] or path
                    r["pool"] = pool
    cols = ["file_id", "song_key", "artist", "lead_artist", "title", "title_base",
            "version", "vtype", "pool", "in_mirror", "in_archive", "path_mirror",
            "path_archive"]
    with open(a.out, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=cols)
        w.writeheader()
        for r in sorted(rows.values(), key=lambda r: r["file_id"]):
            w.writerow({c: r[c] for c in cols})
    n = len(rows)
    print(f"{n} súborov (unikátnych verzií), {len({r['song_key'] for r in rows.values()})} skladieb",
          file=sys.stderr)


if __name__ == "__main__":
    main()
