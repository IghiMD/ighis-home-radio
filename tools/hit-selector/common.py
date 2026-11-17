"""Spoločné funkcie: normalizácia názvov a rozbor názvu súboru."""
import os
import re
import unicodedata

REMIX_RE = re.compile(
    r"\b(remix|rmx|bootleg|mashup|mash-up|rework|re-work|refix|flip|vip mix|"
    r"club mix|club version|club edit|extended|12\"|12 inch|maxi|dub mix|"
    r"revibe|re-edit|reedit|megamix|hardstyle|sped up|slowed|nightcore|"
    r"house mix|dance mix|trance mix|techno mix|deep house|x-tended)\b", re.I)
# verzie, ktoré sú stále "originál" (rozhlasová podoba)
ORIG_RE = re.compile(
    r"\b(radio edit|radio version|radio mix|single version|single edit|"
    r"album version|original version|original mix|main edit|video edit|"
    r"video version|remaster(ed)?|mono|stereo|short cut|clean|explicit|"
    r"dirty version|from .* soundtrack|from the .*)\b", re.I)
OTHER_RE = re.compile(r"\b(live|unplugged|acoustic|akusticky|karaoke|instrumental|acapella|a cappella|"
                      r"demo|cover|tribute|made famous)\b", re.I)
FEAT_SPLIT = re.compile(r"\s+(feat\.?|ft\.?|featuring|vs\.?|x|&|and|a)\s+|[,;/_]", re.I)


def strip_accents(s):
    s = unicodedata.normalize("NFKD", s)
    return "".join(ch for ch in s if not unicodedata.combining(ch))


def norm(s):
    s = strip_accents(s).lower().replace("’", "'")
    s = re.sub(r"[^a-z0-9]+", " ", s)
    return s.strip()


def parse_filename(path):
    """Vráti dict: artist, title, title_base, version, vtype."""
    name = os.path.splitext(os.path.basename(path))[0]
    name = re.sub(r"\s*\(alt \d+\)\s*$", "", name)
    name = re.sub(r"_\d$", "", name)
    # číslo stopy na začiatku ("07. ", "07 - ", "15 KAI TRACID"), ale nie "50 Cent", "2Pac"
    name = re.sub(r"^\d{1,3}\s*[._]\s*(?=\D)|^\d{1,3}\s+-\s+(?=.+ - )|^\d{2}\s+(?=[A-Z]{2,}\b)", "", name)
    # DJ značky na konci: " - 6B - 125"
    name = re.sub(r"\s+-\s+\d{1,2}[AB]\s+-\s+\d{2,3}\s*$", "", name)
    parts = name.split(" - ")
    if len(parts) >= 2:
        artist, title = parts[0].strip(), " - ".join(parts[1:]).strip()
    else:
        artist, title = "", name.strip()
    brackets = re.findall(r"[\(\[]([^\)\]]*)[\)\]]", title)
    dash_tail = ""
    m = re.search(r"\s+-\s+(.+)$", title)
    if m:
        dash_tail = m.group(1)
    version = " | ".join(brackets + ([dash_tail] if dash_tail else []))
    title_base = re.sub(r"\s*[\(\[][^\)\]]*[\)\]]", "", title)
    title_base = re.sub(r"\s+-\s+.+$", "", title_base).strip() or title
    if REMIX_RE.search(version) or REMIX_RE.search(title_base):
        vtype = "remix"
    elif OTHER_RE.search(version):
        vtype = "other"
    else:
        vtype = "original"
    lead = FEAT_SPLIT.split(artist)[0].strip() if artist else ""
    return {"artist": artist, "lead_artist": lead or artist, "title": title,
            "title_base": title_base, "version": version, "vtype": vtype}


def song_key(p):
    """Kľúč skladby bez ohľadu na verziu (interpret + základ názvu)."""
    return norm(p["lead_artist"]) + "|" + norm(p["title_base"])
