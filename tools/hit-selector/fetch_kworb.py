#!/usr/bin/env python3
"""Spotify dáta z kworb.net:
 - data/kworb_spotify_top.csv : 2 500 najstreamovanejších skladieb sveta (všetky časy)
 - data/kworb_spotify_czsk.csv: všetky skladby, ktoré kedy boli v dennej Top 200 v ČR / SR,
   s celkovým počtom prehratí v danej krajine (lokálny signál, od r. 2017)
"""
import csv
import html
import re
import urllib.request


def get(url):
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
    return urllib.request.urlopen(req, timeout=90).read().decode("utf-8", "replace")


def clean(s):
    return html.unescape(re.sub(r"<[^>]+>", "", s)).strip()


h = get("https://kworb.net/spotify/songs.html")
rows = re.findall(r'<tr><td class="text"><div>(.*?)</div></td><td>([\d,]+)</td>', h)
with open("data/kworb_spotify_top.csv", "w", newline="", encoding="utf-8") as f:
    w = csv.writer(f)
    w.writerow(["interpret", "nazov", "streamy"])
    for txt, n in rows:
        a, _, t = clean(txt).partition(" - ")
        w.writerow([a, t, n.replace(",", "")])
print("svet:", len(rows))

out = {}
for cc in ("cz", "sk"):
    h = get(f"https://kworb.net/spotify/country/{cc}_daily_totals.html")
    for tr in re.findall(r"<tr>(.*?)</tr>", h, flags=re.S):
        tds = re.findall(r"<td[^>]*>(.*?)</td>", tr, flags=re.S)
        if len(tds) < 7:
            continue
        a, _, t = clean(tds[0]).partition(" - ")
        days = int(clean(tds[1]).replace(",", "") or 0)
        peak = int(clean(tds[3]).replace(",", "") or 999)
        total = int(clean(tds[-1]).replace(",", "") or 0)
        r = out.setdefault((a, t), {"interpret": a, "nazov": t, "cz_streamy": 0, "sk_streamy": 0,
                                    "dni": 0, "najvyssie": 999})
        r[cc + "_streamy"] = total
        r["dni"] = max(r["dni"], days)
        r["najvyssie"] = min(r["najvyssie"], peak)
with open("data/kworb_spotify_czsk.csv", "w", newline="", encoding="utf-8") as f:
    w = csv.DictWriter(f, fieldnames=["interpret", "nazov", "cz_streamy", "sk_streamy", "dni", "najvyssie"])
    w.writeheader()
    for r in sorted(out.values(), key=lambda r: -(r["cz_streamy"] + 2 * r["sk_streamy"])):
        w.writerow(r)
print("ČR+SR:", len(out))
