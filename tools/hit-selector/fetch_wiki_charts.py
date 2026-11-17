#!/usr/bin/env python3
"""Historické rebríčky z Wikipédie -> data/wiki_charts.csv
 - US: Billboard Year-End Hot 100 (1960-2025), 100 skladieb ročne
 - UK: všetky single, ktoré boli v Top 10 (1960-2025), s najvyšším umiestnením
Slúži ako signál známosti starších zahraničných hitov (pred érou streamingu).
"""
import csv
import os
import re
import sys
import time
import urllib.parse
import urllib.request

CACHE = "data/wiki_cache"


def raw(lang, title):
    fn = f"{CACHE}/{lang}_{title}.txt".replace("/", "_")
    if os.path.exists(fn):
        return open(fn, encoding="utf-8").read()
    url = f"https://{lang}.wikipedia.org/wiki/{urllib.parse.quote(title)}?action=raw"
    for i in range(4):
        try:
            req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0 (X11; Linux x86_64)"})
            t = urllib.request.urlopen(req, timeout=40).read().decode("utf-8", "replace")
            open(fn, "w", encoding="utf-8").write(t)
            time.sleep(1.2)
            return t
        except Exception as e:
            if "404" in str(e):
                return ""
            time.sleep(5 + 5 * i)
    return ""


def unlink(s):
    s = re.sub(r"<ref[^>]*>.*?</ref>|<ref[^>]*/>", "", s, flags=re.S)
    s = re.sub(r"\{\{[^{}]*\}\}", "", s)
    s = re.sub(r"\[\[(?:[^\]|]*\|)?([^\]]*)\]\]", r"\1", s)
    s = re.sub(r"<[^>]+>", "", s)
    s = re.sub(r"(align|bgcolor|style|scope|rowspan|colspan)\s*=\s*(\"[^\"]*\"|\S+)", "", s)
    return s.replace("''", "").replace("‡", "").replace("♦", "").strip(" |\"“”\n\t")


def main():
    os.makedirs(CACHE, exist_ok=True)
    out = []
    for y in range(1960, 2026):
        t = raw("en", f"Billboard_Year-End_Hot_100_singles_of_{y}")
        n = 0
        for m in re.finditer(r"^\|\s*(\d+)\s*\|\|\s*(.+?)\s*\|\|\s*(.+?)\s*$", t, flags=re.M):
            title, artist = unlink(m.group(2)), unlink(m.group(3))
            if title and artist:
                out.append(("us_rocny", y, int(m.group(1)), artist, title))
                n += 1
        t = raw("en", f"List_of_UK_top-ten_singles_in_{y}")
        k = 0
        t = re.sub(r'(align|bgcolor|style|scope)\s*=\s*("[^"]*"|[^\s|]+)\s*', "", t)
        for m in re.finditer(r'^\|[^\n]*?"(\[\[[^\n]*?\]\]|[^"\n]+)"[^\n]*\n\|([^\n]+)\n\|\s*(\d+)\s*\n', t, flags=re.M):
            title, artist = unlink(m.group(1)), unlink(m.group(2))
            if title and artist and not artist.isdigit():
                out.append(("uk_top10", y, int(m.group(3)), artist, title))
                k += 1
        print(y, "US", n, "UK", k, file=sys.stderr)
    with open("data/wiki_charts.csv", "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(["rebricek", "rok", "pozicia", "interpret", "nazov"])
        w.writerows(out)
    print(len(out), "záznamov", file=sys.stderr)


if __name__ == "__main__":
    main()
