#!/usr/bin/env python3
"""Rádiové hitparády ČNS IFPI (ČR aj SR) od roku 2006 -> data/ifpi_radio.csv.

Zdroj: https://ifpicr.cz/hitparada (verejný týždenný archív). Sťahuje sa každý 3. týždeň;
počet týždňov v hitparáde a najvyššie umiestnenie sú na stránke uvedené kumulatívne,
takže sa nič podstatné nestratí. Medzivýsledky sú v data/ifpi_cache/ (dá sa prerušiť).
"""
import csv
import html
import json
import os
import re
import sys
import time
import urllib.request

CHARTS = {2: "cz_radio100", 7: "cz_radio50_domace", 40: "sk_radio100", 42: "sk_radio50_domace"}
STEP = 3
BASE = "https://ifpicr.cz/hitparada/"
CACHE = "data/ifpi_cache"

ROW = re.compile(
    r'chart-full-row__position">\s*(\d+)\..*?chart-full-row__title">\s*<a[^>]*>\s*(.*?)\s*</a>'
    r'.*?chart-full-row__interpreter">\s*<a[^>]*>\s*(.*?)\s*</a>'
    r'.*?Počet kol:\s*(\d+).*?Nejvýše:\s*(\d+)', re.S)


def get(url):
    for i in range(5):
        try:
            req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0 (hit-selector)"})
            return urllib.request.urlopen(req, timeout=40).read().decode("utf-8", "replace")
        except Exception as e:
            time.sleep(3 + 3 * i)
    return ""


def main():
    os.makedirs(CACHE, exist_ok=True)
    first = get(BASE + "2")
    weeks = re.findall(r'<option[^>]*value="(\d+)"[^>]*>\s*(\d+)\. týden (\d{4})', first)
    weeks = [(int(w), int(n), int(y)) for w, n, y in weeks]
    weeks.sort(key=lambda x: (x[2], x[1]))
    pick = weeks[::STEP]
    if weeks[-1] not in pick:
        pick.append(weeks[-1])
    print(len(weeks), "týždňov, sťahujem", len(pick), "x", len(CHARTS), file=sys.stderr)
    n = 0
    for cid, cname in CHARTS.items():
        for wid, wn, yr in pick:
            fn = f"{CACHE}/{cname}_{yr}_{wn:02d}.json"
            if os.path.exists(fn):
                continue
            h = get(f"{BASE}{cid}?weekId={wid}")
            rows = [(int(p), html.unescape(t).strip(), html.unescape(a).strip(), int(k), int(pk))
                    for p, t, a, k, pk in ROW.findall(h)]
            if not rows:  # neúspešné stiahnutie neukladať, skúsi sa pri ďalšom spustení
                continue
            json.dump(rows, open(fn, "w", encoding="utf-8"), ensure_ascii=False)
            n += 1
            if n % 50 == 0:
                print(" ", n, cname, yr, wn, len(rows), file=sys.stderr)
            time.sleep(0.7)
    # súhrn
    agg = {}
    for f in sorted(os.listdir(CACHE)):
        cname, yr, wn = f[:-5].rsplit("_", 2)
        for p, t, a, k, pk in json.load(open(f"{CACHE}/{f}", encoding="utf-8")):
            key = (cname, a.upper(), t.upper())
            r = agg.setdefault(key, {"chart": cname, "interpret": a, "nazov": t, "tyzdne": 0,
                                     "najvyssie": 999, "body": 0, "prvy_rok": int(yr)})
            r["tyzdne"] = max(r["tyzdne"], k)
            r["najvyssie"] = min(r["najvyssie"], pk, p)
            size = 50 if "50" in cname else 100
            r["body"] += (size + 1 - p) * STEP
            r["prvy_rok"] = min(r["prvy_rok"], int(yr))
    with open("data/ifpi_radio.csv", "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=["chart", "interpret", "nazov", "tyzdne", "najvyssie", "body", "prvy_rok"])
        w.writeheader()
        for r in sorted(agg.values(), key=lambda r: (r["chart"], -r["body"])):
            w.writerow(r)
    print(len(agg), "záznamov", file=sys.stderr)


if __name__ == "__main__":
    main()
