#!/usr/bin/env python3
"""Vyrobí out/posluch.html – lokálne posluchové prostredie s vloženým katalógom a návrhmi tried."""
import csv
import json
import os

HERE = os.path.dirname(os.path.abspath(__file__))
cfg = json.load(open(os.path.join(HERE, "config.json"), encoding="utf-8"))
rows = list(csv.DictReader(open(os.path.join(HERE, "out", "skladby_skore.csv"), encoding="utf-8-sig")))
CH = {"cz_radio100": "rádiá ČR", "sk_radio100": "rádiá SR", "cz_radio50_domace": "rádiá ČR (domáca hitparáda)",
      "sk_radio50_domace": "rádiá SR (domáca hitparáda)"}


def radio_txt(s):
    import re
    m = re.match(r"(\w+): (\d+) týž\., max (\d+)\.", s or "")
    return f"{CH.get(m.group(1), m.group(1))}: {m.group(2)} týždňov v hitparáde, najvyššie {m.group(3)}. miesto" if m else ""


data = []
for r in rows:
    th = cfg["czsk"] if r["fond"] == "czsk" else cfg
    sc = r["skore"]
    edge = 1 if (sc == "" or abs(float(sc) - th["trieda_B_od"]) <= 6) else 0
    x = {k[2:]: int(v) for k, v in r.items() if k.startswith("s_") and v != ""}
    cls = r["trieda"]
    # rap/rock v triede B systém nehrá -> pre posluch to ukáž ako C
    if cls == "B" and r["do_vysielania"] == "0":
        cls = "C"
    data.append({"f": r["subor"], "a": r["interpret"], "t": r["nazov"], "v": r["typ_verzie"],
                 "p": r["fond"], "s": sc, "c": cls, "g": r["zaner"], "r": radio_txt(r["radio"]),
                 "k": r["song_key"], "m": int(r["v_mirrore"] or 0), "e": edge, "x": x})
tpl = open(os.path.join(HERE, "posluch.template.html"), encoding="utf-8").read()
js = json.dumps(data, ensure_ascii=False, separators=(",", ":")).replace("</", "<\\/")
out = os.path.join(HERE, "out", "posluch.html")
open(out, "w", encoding="utf-8").write(tpl.replace("/*DATA*/", js))
print(out, len(data), "skladieb,", round(os.path.getsize(out) / 1e6, 1), "MB")
