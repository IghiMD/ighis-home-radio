#!/usr/bin/env python3
"""Stiahne zoznam 2 500 najstreamovanejších skladieb Spotify (kworb.net) -> data/kworb_spotify_top.csv.
Druhý, na Deezeri nezávislý signál známosti (celkové streamy od vydania, nie aktuálny trend)."""
import csv
import re
import urllib.request

URL = "https://kworb.net/spotify/songs.html"
h = urllib.request.urlopen(urllib.request.Request(URL, headers={"User-Agent": "Mozilla/5.0"}), timeout=60).read().decode("utf-8", "replace")
rows = re.findall(r'<tr><td class="text"><div>(.*?)</div></td><td>([\d,]+)</td>', h)
with open("data/kworb_spotify_top.csv", "w", newline="", encoding="utf-8") as f:
    w = csv.writer(f)
    w.writerow(["interpret", "nazov", "streamy"])
    for txt, n in rows:
        txt = re.sub(r"<[^>]+>", "", txt).replace("&amp;", "&").replace("&#039;", "'")
        a, _, t = txt.partition(" - ")
        w.writerow([a, t, n.replace(",", "")])
print(len(rows), "skladieb")
