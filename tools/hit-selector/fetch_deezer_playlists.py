#!/usr/bin/env python3
"""Koľkokrát je skladba v cudzích "hitových" playlistoch na Deezeri -> data/deezer_playlists.csv

Nezávislý signál od ranku: ľudia si do playlistov typu "České hity", "80s hits" dávajú to,
čo za hit považujú - funguje aj pre staré a domáce skladby.
"""
import csv
import json
import sys
import time
import urllib.parse
import urllib.request
from collections import Counter

QUERIES = {
    "czsk": ["ceske hity", "české hity", "slovenske hity", "slovenské hity", "cesko slovenske hity",
             "cz sk hity", "ceske a slovenske hity", "české pecky", "slovenske pesnicky",
             "ceske pisnicky", "cesky pop", "slovensky pop", "ceske oldies", "cesky rock",
             "80. leta ceske", "90. leta ceske", "diskopribeh", "ceske hity 2000", "ceskoslovenske"],
    "intl": ["80s hits", "90s hits", "2000s hits", "2010s hits", "70s hits", "greatest hits of all time",
             "party hits", "best songs ever", "throwback hits", "oldies hity", "hity 80 90",
             "eurodance 90s", "dance classics", "disco hits", "pop hits", "radio hits", "top hits",
             "karaoke hits", "wedding party", "hity vsech dob", "najvacsie hity", "nejvetsi hity"],
}
MAX_PL = 45  # playlistov na skupinu


def get(url):
    for i in range(6):
        try:
            d = json.loads(urllib.request.urlopen(urllib.request.Request(
                url, headers={"User-Agent": "hit-selector/1.0"}), timeout=30).read().decode())
            if isinstance(d, dict) and d.get("error"):
                time.sleep(3 + 2 * i)
                continue
            time.sleep(0.25)
            return d
        except Exception:
            time.sleep(2 + 2 * i)
    return {}


def main():
    rows = []
    for group, qs in QUERIES.items():
        pls = {}
        for q in qs:
            d = get("https://api.deezer.com/search/playlist?" + urllib.parse.urlencode({"q": q, "limit": 25}))
            for p in d.get("data", []):
                if 40 <= p.get("nb_tracks", 0) <= 1500:
                    pls[p["id"]] = p
        chosen = sorted(pls.values(), key=lambda p: -p.get("nb_tracks", 0))[:MAX_PL * 2]
        chosen = chosen[::2][:MAX_PL]  # mix veľkých aj menších
        cnt, meta = Counter(), {}
        for p in chosen:
            url = f"https://api.deezer.com/playlist/{p['id']}/tracks?limit=500"
            seen = set()
            while url:
                d = get(url)
                for t in d.get("data", []):
                    k = (t["artist"]["name"], t["title"])
                    if k not in seen:
                        seen.add(k)
                        cnt[k] += 1
                url = d.get("next")
        print(group, len(chosen), "playlistov,", len(cnt), "skladieb", file=sys.stderr)
        for (a, t), n in cnt.items():
            rows.append((group, a, t, n, len(chosen)))
    with open("data/deezer_playlists.csv", "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(["skupina", "interpret", "nazov", "pocet_playlistov", "z_playlistov"])
        w.writerows(sorted(rows, key=lambda r: (r[0], -r[3])))


if __name__ == "__main__":
    main()
