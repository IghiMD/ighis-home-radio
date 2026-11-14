#!/usr/bin/env python3
"""Last.fm: počet poslucháčov a prehratí každej skladby -> data/lastfm.csv

Potrebuje premennú prostredia LASTFM_API_KEY (stačí API key, secret netreba).
Použitie:  LASTFM_API_KEY=... python3 fetch_lastfm.py
Dá sa prerušiť, pokračuje tam, kde skončil.
"""
import csv
import json
import os
import sys
import time
import urllib.parse
import urllib.request

KEY = os.environ.get("LASTFM_API_KEY", "").strip()
OUT = "data/lastfm.csv"


def info(artist, title):
    q = urllib.parse.urlencode({"method": "track.getInfo", "api_key": KEY, "artist": artist,
                                "track": title, "autocorrect": 1, "format": "json"})
    for i in range(4):
        try:
            d = json.loads(urllib.request.urlopen("https://ws.audioscrobbler.com/2.0/?" + q, timeout=30).read())
            if d.get("error") == 29:  # limit požiadaviek
                time.sleep(10)
                continue
            t = d.get("track")
            if not t:
                return None
            return int(t.get("listeners", 0)), int(t.get("playcount", 0)), t.get("artist", {}).get("name", ""), t.get("name", "")
        except Exception:
            time.sleep(2 + 3 * i)
    return None


def main():
    if not KEY:
        sys.exit("Chýba LASTFM_API_KEY.")
    songs = {}
    for r in csv.DictReader(open("data/catalog.csv", encoding="utf-8")):
        songs.setdefault(r["song_key"], (r["lead_artist"] or r["artist"], r["title_base"]))
    done = set()
    if os.path.exists(OUT):
        done = {r["song_key"] for r in csv.DictReader(open(OUT, encoding="utf-8"))}
    f = open(OUT, "a", newline="", encoding="utf-8")
    w = csv.writer(f)
    if not done:
        w.writerow(["song_key", "interpret", "nazov", "posluchaci", "prehratia", "lastfm_interpret", "lastfm_nazov"])
    todo = [(k, v) for k, v in songs.items() if k not in done]
    print(len(songs), "skladieb, zostáva", len(todo), file=sys.stderr)
    for n, (k, (a, t)) in enumerate(todo, 1):
        r = info(a, t) if a else None
        w.writerow([k, a, t] + (list(r) if r else [0, 0, "", ""]))
        if n % 200 == 0:
            f.flush()
            print(" ", n, file=sys.stderr)
        time.sleep(0.22)
    f.close()


if __name__ == "__main__":
    main()
