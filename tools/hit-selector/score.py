#!/usr/bin/env python3
"""Spočíta hitovosť skladieb z viacerých nezávislých zdrojov, zatriedi ich (A/B/C/D)
a vyrobí playlisty pre AzuraCast.

Vstupy (data/):  catalog.csv, deezer_cache.jsonl, kworb_spotify_top.csv,
                 kworb_spotify_czsk.csv, ifpi_radio.csv, wiki_charts.csv,
                 deezer_playlists.csv, vynimky.csv, operacne_saly.txt
Výstupy (out/):  skladby_skore.csv, IHR_*.m3u, pomocné zoznamy
Chýbajúci zdroj sa jednoducho preskočí.
"""
import bisect
import csv
import json
import math
import os
import re
from collections import Counter, defaultdict

from common import norm, FEAT_SPLIT

HERE = os.path.dirname(os.path.abspath(__file__))
D = lambda *p: os.path.join(HERE, *p)
STOP = {"the", "and", "feat", "ft", "vs", "a", "dj", "mc", "de", "la", "of"}


def base_title(t):
    t = re.sub(r"\s*[\(\[][^\)\]]*[\)\]]", "", t)
    return re.sub(r"\s+-\s+.+$", "", t).strip() or t


def tkey(title):
    return re.sub(r"^(the|a) ", "", norm(base_title(title)))


def tokens(artist):
    return {w for w in norm(artist).split() if len(w) >= 2 and w not in STOP}


class Index:
    """Vyhľadávanie skladby v externom zozname: zhodný názov + aspoň jedno spoločné slovo v interpretovi."""

    def __init__(self):
        self.by_title = defaultdict(list)

    def add(self, artist, title, value):
        self.by_title[tkey(title)].append((tokens(artist), value))

    def find(self, artist, title):
        ta = tokens(artist)
        return [v for at, v in self.by_title.get(tkey(title), ()) if at & ta]


def pct_fn(values):
    s = sorted(values)
    n = len(s)
    return lambda v: 100.0 * bisect.bisect_right(s, v) / n if n else 0.0


def logscale(v, lo, hi):
    """lo -> 0, hi -> 100 (logaritmicky, orezané)."""
    if not v or v <= lo:
        return 0.0
    return min(100.0, 100.0 * math.log(v / lo) / math.log(hi / lo))


def read_csv(name):
    p = D("data", name)
    if not os.path.exists(p):
        return []
    return list(csv.DictReader(open(p, encoding="utf-8")))


def main():
    cfg = json.load(open(D("config.json"), encoding="utf-8"))
    files = read_csv("catalog.csv")
    dz = {}
    with open(D("data", "deezer_cache.jsonl"), encoding="utf-8") as f:
        for line in f:
            r = json.loads(line)
            dz[r["song_key"]] = r

    # --- externé zdroje -------------------------------------------------
    sp_world, sp_local, radio, radio_dom, hist, plists = (Index() for _ in range(6))
    for r in read_csv("kworb_spotify_top.csv"):
        sp_world.add(r["interpret"], r["nazov"], int(r["streamy"]))
    for r in read_csv("kworb_spotify_czsk.csv"):
        # prepočet na obyvateľa: SR má polovicu obyvateľov ČR
        sp_local.add(r["interpret"], r["nazov"], int(r["cz_streamy"]) + 2 * int(r["sk_streamy"]))
    ifpi = read_csv("ifpi_radio.csv")
    chart_pct = {}
    for ch in {r["chart"] for r in ifpi}:
        chart_pct[ch] = pct_fn([int(r["body"]) for r in ifpi if r["chart"] == ch])
    for r in ifpi:
        val = (chart_pct[r["chart"]](int(r["body"])), int(r["tyzdne"]), int(r["najvyssie"]), r["chart"])
        (radio_dom if "domace" in r["chart"] else radio).add(r["interpret"], r["nazov"], val)
    for r in read_csv("wiki_charts.csv"):
        pos = int(r["pozicia"])
        s = (100 - 0.4 * pos) if r["rebricek"] == "us_rocny" else (93 - 3.3 * pos)
        hist.add(r["interpret"], r["nazov"], (s, int(r["rok"])))
    for r in read_csv("deezer_playlists.csv"):
        plists.add(r["interpret"], r["nazov"], (r["skupina"], int(r["pocet_playlistov"])))

    lastfm = {r["song_key"]: int(r["posluchaci"]) for r in read_csv("lastfm.csv")}
    overrides = {norm(r["interpret"]) + "|" + norm(r["nazov"]): r["trieda"].strip().upper()
                 for r in read_csv("vynimky.csv")}
    or_list = set()
    p = D("data", "operacne_saly.txt")
    if os.path.exists(p):
        for line in open(p, encoding="utf-8"):
            line = line.strip()
            if line and not line.startswith("#") and " - " in line:
                a, t = line.split(" - ", 1)
                or_list.add(norm(FEAT_SPLIT.split(a)[0]) + "|" + norm(base_title(t)))

    # --- skladby --------------------------------------------------------
    by_song = defaultdict(list)
    for f in files:
        by_song[f["song_key"]].append(f)
    czsk_artists = {norm(f["lead_artist"]) for f in files if f["pool"] == "czsk"}
    czsk_artists -= {norm(f["lead_artist"]) for f in files if f["pool"] == "dance"}
    songs = []
    for key, fl in by_song.items():
        d = dz.get(key, {})
        f0 = fl[0]
        songs.append(dict(key=key, files=fl, dz=d, artist=f0["artist"] or f0["lead_artist"],
                          title=f0["title_base"],
                          pool="czsk" if norm(f0["lead_artist"]) in czsk_artists else "intl",
                          rank=(d.get("rank") or 0) if d.get("matched") else 0,
                          fans=d.get("artist_fans", 0) if d.get("matched") else 0))
    cz_rank = pct_fn([s["rank"] for s in songs if s["pool"] == "czsk" and s["rank"]])
    lf_cz = pct_fn([lastfm.get(s["key"], 0) for s in songs if s["pool"] == "czsk" and lastfm.get(s["key"], 0)])
    max_pl = defaultdict(lambda: 1)
    for lst in plists.by_title.values():
        for _, (g, n) in lst:
            max_pl[g] = max(max_pl[g], n)

    for s in songs:
        a, t = s["artist"], s["title"]
        sig = {}
        # 1) Deezer rank (aktuálna streamovacia popularita)
        if s["rank"]:
            sig["deezer"] = cz_rank(s["rank"]) if s["pool"] == "czsk" else logscale(s["rank"], 1e5, 1e6)
        # 2) Spotify svet, všetky časy (top 2 500 = aspoň 725 mil. prehratí)
        v = sp_world.find(a, t)
        if v:
            sig["spotify_svet"] = 75 + 0.25 * logscale(max(v), 7e8, 3e9)
        # 3) Spotify ČR + SR (lokálne prehratia od r. 2017)
        v = sp_local.find(a, t)
        if v:
            sig["spotify_czsk"] = logscale(max(v), 1.5e5, 1.5e7)
        # 4) rádiá ČR/SR 2006-2026 (IFPI): všeobecná Top 100 + domáca Top 50
        cand = [(55 + 0.45 * x[0], x) for x in radio.find(a, t)]
        if s["pool"] == "czsk":
            cand += [(50 + 0.5 * x[0], x) for x in radio_dom.find(a, t)]
        if cand:
            val, best = max(cand)
            sig["radio_czsk"] = val
            s["radio_info"] = f"{best[3]}: {best[1]} týž., max {best[2]}."
        # 5) historické rebríčky US (ročný) a UK (Top 10)
        v = hist.find(a, t)
        if v:
            sig["rebricky_us_uk"] = max(x[0] for x in v)
            s["rok"] = min(x[1] for x in v)
        # 6) výskyt v hitových playlistoch
        v = plists.find(a, t)
        if v:
            g, n = max(v, key=lambda x: x[1])
            if n >= 2:
                sig["playlisty"] = min(100.0, 45 + 55 * math.sqrt(n / max(max_pl[g] * 0.5, 2)))
        # 7) Last.fm: počet poslucháčov skladby (dlhodobý, nie len aktuálny záujem)
        lf = lastfm.get(s["key"], 0)
        if lf:
            sig["lastfm"] = lf_cz(lf) if s["pool"] == "czsk" else logscale(lf, 3e4, 1.5e6)
        s["sig"] = sig
        vals = sorted(sig.values(), reverse=True) + [0, 0]
        # megahit musí potvrdiť druhý zdroj: 70 % najsilnejší + 30 % druhý najsilnejší signál
        s["score"] = round(0.7 * vals[0] + 0.3 * vals[1], 1) if sig else None
        s["n_sig"] = sum(1 for x in sig.values() if x >= 50)
        genres = s["dz"].get("genres") or [] if s["dz"].get("matched") else []
        s["genre"] = genres[0] if genres else ""
        s["only_a"] = int(bool(genres) and all(g in cfg["zanre_len_A"] for g in genres))
        if s["score"] is None:
            s["cls"] = "?"
        else:
            th = cfg["czsk"] if s["pool"] == "czsk" else cfg
            sc, n = s["score"], s["n_sig"]
            radio_hit = sig.get("radio_czsk", 0) >= th["radio_staci_od"]
            if sc >= th["trieda_A_od"] and n >= th["trieda_A_min_zdrojov"]:
                s["cls"] = "A"
            elif (sc >= th["trieda_B_od"] and n >= th["trieda_B_min_zdrojov"]) or radio_hit:
                s["cls"] = "B"
            elif sc >= th["trieda_C_od"]:
                s["cls"] = "C"
            else:
                s["cls"] = "D"
        k = norm(FEAT_SPLIT.split(a)[0]) + "|" + norm(t)
        if k in overrides or s["key"] in overrides:
            s["cls"], s["override"] = overrides.get(k) or overrides[s["key"]], 1
        s["on_air"] = bool(s["cls"] == "A" or (s["cls"] == "B" and (not s["only_a"] or s.get("override"))))

    # --- výstupy ---------------------------------------------------------
    os.makedirs(D("out"), exist_ok=True)
    sig_names = ["deezer", "spotify_svet", "spotify_czsk", "radio_czsk",
                 "rebricky_us_uk", "playlisty", "lastfm"]
    rows = []
    for s in songs:
        d = s["dz"] if s["dz"].get("matched") else {}
        bpm = d.get("bpm") or 0
        for f in s["files"]:
            row = {"interpret": f["artist"], "nazov": f["title"], "typ_verzie": f["vtype"],
                   "fond": s["pool"], "skore": "" if s["score"] is None else s["score"],
                   "trieda": s["cls"], "do_vysielania": int(s["on_air"]),
                   "pocet_zdrojov": s["n_sig"]}
            for n in sig_names:
                row["s_" + n] = round(s["sig"][n]) if n in s["sig"] else ""
            row.update({"radio": s.get("radio_info", ""), "zaner": s["genre"], "len_ak_A": s["only_a"],
                        "bpm": bpm or "", "balada": int(0 < bpm < cfg["balada_bpm_pod"]),
                        "explicit": d.get("explicit", ""), "dlzka_s": d.get("duration", ""),
                        "rucna_vynimka": s.get("override", ""),
                        "v_mirrore": f["in_mirror"], "v_archive": f["in_archive"],
                        "subor": os.path.basename(f["path_mirror"] or f["path_archive"]),
                        "song_key": s["key"]})
            rows.append(row)
    rows.sort(key=lambda r: (-(r["skore"] or -1), r["interpret"]))
    cols = list(rows[0].keys())

    def write(name, sel):
        with open(D("out", name), "w", newline="", encoding="utf-8-sig") as f:
            w = csv.DictWriter(f, fieldnames=cols)
            w.writeheader()
            w.writerows(sel)
        return len(sel)

    def m3u(name, sel):
        seen, lines = set(), ["#EXTM3U"]
        for r in sel:
            if r["subor"] not in seen:
                seen.add(r["subor"])
                lines.append(r["subor"])
        open(D("out", name), "w", encoding="utf-8").write("\n".join(lines) + "\n")
        return len(seen)

    write("skladby_skore.csv", rows)
    air = [r for r in rows if r["do_vysielania"] and r["v_mirrore"] == "1"]
    orig = [r for r in air if r["typ_verzie"] == "original"]
    stats = {
        "IHR_A_megahity.m3u": m3u("IHR_A_megahity.m3u", [r for r in orig if r["trieda"] == "A"]),
        "IHR_B_hity.m3u": m3u("IHR_B_hity.m3u", [r for r in orig if r["trieda"] == "B"]),
        # pevné pravidlo: remix len večer a len ak je originál trieda A
        "IHR_vecer_remixy.m3u": m3u("IHR_vecer_remixy.m3u",
                                    [r for r in air if r["typ_verzie"] == "remix" and r["trieda"] == "A"]),
    }

    # operačné sály 08-16: pevné pravidlá platia vždy (originál, bez explicitných textov, bežná dĺžka)
    def or_ok(r):
        return r["explicit"] != 1 and (not r["dlzka_s"] or r["dlzka_s"] <= cfg["max_dlzka_dna_s"])

    if or_list:
        osel = [r for r in orig if or_ok(r) and r["song_key"] in or_list]
    else:
        osel = [r for r in orig if r["trieda"] == "A" and or_ok(r)]
    have = {r["song_key"] for r in osel}
    for r in orig:  # malý výber by sa opakoval: doplniť najlepšími hitmi (zoradené podľa skóre)
        if len(have) >= cfg["operacne_saly_min_skladieb"]:
            break
        if r["song_key"] not in have and or_ok(r):
            osel.append(r)
            have.add(r["song_key"])
    stats["IHR_operacne_saly.m3u"] = m3u("IHR_operacne_saly.m3u", osel)

    stats["hity_na_doplnenie_do_mirroru.csv"] = write(
        "hity_na_doplnenie_do_mirroru.csv", [r for r in rows if r["do_vysielania"] and r["v_mirrore"] == "0"])
    stats["vyradit_z_rotacie.csv"] = write(
        "vyradit_z_rotacie.csv",
        [r for r in rows if r["v_mirrore"] == "1" and not r["do_vysielania"] and r["trieda"] != "?"])
    stats["na_rozhodnutie.csv"] = write("na_rozhodnutie.csv", [r for r in rows if r["trieda"] == "?"])
    # hraničné prípady na ručné posúdenie (kalibrácia prahov)
    def near(r):
        if r["skore"] == "":
            return False
        th = cfg["czsk"] if r["fond"] == "czsk" else cfg
        return abs(r["skore"] - th["trieda_B_od"]) <= 4
    stats["hranicne_pripady.csv"] = write("hranicne_pripady.csv", [r for r in rows if near(r)])

    print("skladby podľa triedy:", dict(sorted(Counter(s["cls"] for s in songs).items())))
    for pool in ("intl", "czsk"):
        print(" ", pool, dict(sorted(Counter(s["cls"] for s in songs if s["pool"] == pool).items())))
    print("pokrytie zdrojov:", {n: sum(1 for s in songs if n in s["sig"]) for n in sig_names})
    mir = {r["song_key"] for r in rows if r["v_mirrore"] == "1"}
    print("v mirrore do vysielania:", len({r["song_key"] for r in air}), "z", len(mir))
    print(stats)


if __name__ == "__main__":
    main()
