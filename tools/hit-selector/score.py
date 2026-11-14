#!/usr/bin/env python3
"""Spočíta skóre známosti, zatriedi skladby (A/B/C/D) a vyrobí playlisty.

Vstup:  data/catalog.csv, data/deezer_cache.jsonl, config.json,
        data/vynimky.csv (ručné rozhodnutia), data/operacne_saly.txt (ručný výber)
Výstup: out/skladby_skore.csv + out/*.m3u + out/na_rozhodnutie.csv
"""
import bisect
import csv
import json
import math
import os
from collections import defaultdict

import re

from common import norm, FEAT_SPLIT

HERE = os.path.dirname(os.path.abspath(__file__))
D = lambda *p: os.path.join(HERE, *p)


def pct_fn(values):
    s = sorted(values)
    n = len(s)
    return lambda v: 100.0 * bisect.bisect_right(s, v) / n if n else 0.0


def load():
    cfg = json.load(open(D("config.json"), encoding="utf-8"))
    files = list(csv.DictReader(open(D("data", "catalog.csv"), encoding="utf-8")))
    dz = {}
    with open(D("data", "deezer_cache.jsonl"), encoding="utf-8") as f:
        for line in f:
            r = json.loads(line)
            dz[r["song_key"]] = r
    overrides = {}
    p = D("data", "vynimky.csv")
    if os.path.exists(p):
        for r in csv.DictReader(open(p, encoding="utf-8")):
            overrides[norm(r["interpret"]) + "|" + norm(r["nazov"])] = r["trieda"].strip().upper()
    or_list = set()
    p = D("data", "operacne_saly.txt")
    if os.path.exists(p):
        for line in open(p, encoding="utf-8"):
            line = line.strip()
            if line and not line.startswith("#") and " - " in line:
                a, t = line.split(" - ", 1)
                or_list.add(norm(a) + "|" + norm(t))
    kworb = {}
    p = D("data", "kworb_spotify_top.csv")
    if os.path.exists(p):
        for r in csv.DictReader(open(p, encoding="utf-8")):
            lead = FEAT_SPLIT.split(r["interpret"])[0]
            t = re.sub(r"\s*[\(\[][^\)\]]*[\)\]]", "", r["nazov"])
            t = re.sub(r"\s+-\s+.+$", "", t)
            k = norm(lead) + "|" + norm(t)
            kworb[k] = max(kworb.get(k, 0), int(r["streamy"]))
    return cfg, files, dz, overrides, or_list, kworb


def main():
    cfg, files, dz, overrides, or_list, kworb = load()
    by_song = defaultdict(list)
    for f in files:
        by_song[f["song_key"]].append(f)
    # SK/CZ fond: interpreti z priečinka CzechoSlovak
    czsk_artists = {norm(f["lead_artist"]) for f in files if f["pool"] == "czsk"}
    intl_artists = {norm(f["lead_artist"]) for f in files if f["pool"] == "dance"}
    czsk_artists -= intl_artists
    songs = {}
    for key, fl in by_song.items():
        d = dz.get(key, {})
        pool = "czsk" if norm(fl[0]["lead_artist"]) in czsk_artists else "intl"
        songs[key] = dict(key=key, files=fl, dz=d, pool=pool,
                          rank=(d.get("rank") or None) if d.get("matched") else None,
                          streams=kworb.get(key, 0),
                          fans=d.get("artist_fans", 0))
    # skóre: medzinárodné = absolútna logaritmická škála Deezer ranku,
    # SK/CZ = percentil vo vlastnom fonde (globálne čísla ich podhodnocujú)
    cz_rank = pct_fn([s["rank"] for s in songs.values() if s["pool"] == "czsk" and s["rank"]])
    fan_pct = {p: pct_fn([s["fans"] for s in songs.values() if s["pool"] == p and s["rank"]])
               for p in ("intl", "czsk")}
    for s in songs.values():
        if s["rank"] is None and not s["streams"]:
            s["score"], s["cls"] = None, "?"
            continue
        if s["rank"] is None:
            s["rank"] = 0
        if s["pool"] == "intl":
            # rank 1 000 000 -> 100, 100 000 -> 0 (logaritmicky)
            base = max(0.0, min(100.0, 100 * (math.log10(max(s["rank"], 1)) - 5.0)))
        else:
            base = cz_rank(s["rank"])
        score = cfg["vaha_rank"] * base + cfg["vaha_fanusikovia"] * fan_pct[s["pool"]](s["fans"])
        # druhý signál: celkové streamy Spotify (kworb top 2 500) dávajú spodnú hranicu skóre
        if s["streams"] >= cfg["kworb_streamy_A"]:
            score = max(score, cfg["trieda_A_od"] + 5)
        elif s["streams"]:
            score = max(score, cfg["trieda_B_od"] + 5)
        s["score"] = round(score, 1)
        genres = s["dz"].get("genres") or []
        s["genre"] = genres[0] if genres else ""
        th = cfg["czsk"] if s["pool"] == "czsk" else cfg
        cls = ("A" if score >= th["trieda_A_od"] else "B" if score >= th["trieda_B_od"]
               else "C" if score >= th["trieda_C_od"] else "D")
        s["cls_raw"] = cls
        # rap, rock, alternatíva a pod.: do vysielania len trieda A
        s["only_a"] = int(bool(genres) and all(g in cfg["zanre_len_A"] for g in genres))
        s["cls"] = cls
    for s in songs.values():
        k = norm(s["files"][0]["lead_artist"]) + "|" + norm(s["files"][0]["title_base"])
        if k in overrides:
            s["cls"], s["override"] = overrides[k], 1
    os.makedirs(D("out"), exist_ok=True)
    rows = []
    for s in songs.values():
        d = s["dz"]
        bpm = d.get("bpm") or 0
        on_air = s["cls"] == "A" or (s["cls"] == "B" and not s.get("only_a"))
        if s.get("override") and s["cls"] in ("A", "B"):
            on_air = True
        s["on_air"] = on_air
        for f in s["files"]:
            rows.append({
                "interpret": f["artist"], "nazov": f["title"], "typ_verzie": f["vtype"],
                "fond": s["pool"], "skore": "" if s["score"] is None else s["score"],
                "trieda": s["cls"], "do_vysielania": int(on_air),
                "zaner": s.get("genre", ""), "len_ak_A": s.get("only_a", ""),
                "bpm": bpm or "", "balada": int(0 < bpm < cfg["balada_bpm_pod"]),
                "explicit": d.get("explicit", ""), "dlzka_s": d.get("duration", ""),
                "deezer_rank": d.get("rank", ""), "spotify_streamy": s["streams"] or "", "fanusikovia_interpreta": d.get("artist_fans", ""),
                "deezer_zhoda": (d.get("dz_artist", "") + " - " + d.get("dz_title", "")) if d.get("matched") else "",
                "v_mirrore": f["in_mirror"], "v_archive": f["in_archive"],
                "subor": os.path.basename(f["path_mirror"] or f["path_archive"]),
                "song_key": s["key"],
            })
    rows.sort(key=lambda r: (-(r["skore"] or -1), r["interpret"]))
    with open(D("out", "skladby_skore.csv"), "w", newline="", encoding="utf-8-sig") as f:
        w = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
        w.writeheader()
        w.writerows(rows)

    def m3u(name, sel):
        seen, lines = set(), ["#EXTM3U"]
        for r in sel:
            if r["subor"] not in seen:
                seen.add(r["subor"])
                lines.append(r["subor"])
        open(D("out", name), "w", encoding="utf-8").write("\n".join(lines) + "\n")
        return len(seen)

    air = [r for r in rows if r["do_vysielania"] and r["v_mirrore"] == "1"]
    orig = [r for r in air if r["typ_verzie"] == "original"]
    stats = {}
    stats["IHR_A_megahity.m3u"] = m3u("IHR_A_megahity.m3u", [r for r in orig if r["trieda"] == "A"])
    stats["IHR_B_hity.m3u"] = m3u("IHR_B_hity.m3u", [r for r in orig if r["trieda"] == "B"])
    stats["IHR_vecer_remixy.m3u"] = m3u("IHR_vecer_remixy.m3u", [r for r in air if r["typ_verzie"] == "remix" and r["trieda"] == "A"])
    # operačné sály: ručný výber ∩ hity; kým je výber prázdny, trieda A bez explicitných
    def or_ok(r):
        return r["explicit"] != 1 and (not r["dlzka_s"] or r["dlzka_s"] <= cfg["max_dlzka_dna_s"])
    if or_list:
        osel = [r for r in orig if or_ok(r) and (r["song_key"] in or_list
                or norm(r["interpret"]) + "|" + norm(r["nazov"]) in or_list)]
    else:
        osel = [r for r in orig if r["trieda"] == "A" and or_ok(r)]
    # príliš malý výber by sa opakoval: doplniť najlepšími B
    if len({r["song_key"] for r in osel}) < cfg["operacne_saly_min_skladieb"]:
        have = {r["song_key"] for r in osel}
        for r in orig:  # zoradené podľa skóre
            if len(have) >= cfg["operacne_saly_min_skladieb"]:
                break
            if r["song_key"] not in have and or_ok(r):
                osel.append(r)
                have.add(r["song_key"])
    stats["IHR_operacne_saly.m3u"] = m3u("IHR_operacne_saly.m3u", osel)
    # hity, ktoré sú v New Archive, ale nie na streame
    add = [r for r in rows if r["do_vysielania"] and r["v_mirrore"] == "0"]
    with open(D("out", "hity_na_doplnenie_do_mirroru.csv"), "w", newline="", encoding="utf-8-sig") as f:
        w = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
        w.writeheader()
        w.writerows(add)
    # čo teraz hrá a nemalo by (C, D) + nespárované
    with open(D("out", "vyradit_z_rotacie.csv"), "w", newline="", encoding="utf-8-sig") as f:
        w = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
        w.writeheader()
        w.writerows([r for r in rows if r["v_mirrore"] == "1" and not r["do_vysielania"] and r["trieda"] != "?"])
    with open(D("out", "na_rozhodnutie.csv"), "w", newline="", encoding="utf-8-sig") as f:
        w = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
        w.writeheader()
        w.writerows([r for r in rows if r["trieda"] == "?"])
    from collections import Counter
    print("skladby podľa triedy:", dict(Counter(s["cls"] for s in songs.values())))
    print("v mirrore do vysielania:", len({r["song_key"] for r in air}),
          "z", len({r["song_key"] for r in rows if r["v_mirrore"] == "1"}))
    print("na doplnenie z archívu:", len(add))
    print(stats)


if __name__ == "__main__":
    main()
