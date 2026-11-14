#!/usr/bin/env python3
"""Ku každej skladbe z katalógu dohľadá údaje z Deezeru (verejné API, bez kľúča).

Ukladá priebežne do data/deezer_cache.jsonl, takže sa dá prerušiť a spustiť znova.
Použitie: python3 enrich_deezer.py data/catalog.csv
"""
import csv
import json
import os
import sys
import threading
import time
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor
from difflib import SequenceMatcher

from common import norm

API = "https://api.deezer.com"
CACHE = os.environ.get("DZ_CACHE", "data/deezer_cache.jsonl")
RATE = 8.0  # požiadaviek za sekundu (limit Deezeru je 50 / 5 s)
_lock = threading.Lock()
_next = [0.0]


def get(path, params=None):
    url = API + path + ("?" + urllib.parse.urlencode(params) if params else "")
    for attempt in range(6):
        with _lock:
            wait = _next[0] - time.time()
            _next[0] = max(time.time(), _next[0]) + 1.0 / RATE
        if wait > 0:
            time.sleep(wait)
        try:
            req = urllib.request.Request(url, headers={"User-Agent": "hit-selector/1.0"})
            with urllib.request.urlopen(req, timeout=20) as r:
                d = json.loads(r.read().decode("utf-8"))
            if isinstance(d, dict) and d.get("error"):
                if d["error"].get("code") == 4:  # kvóta
                    time.sleep(2 + attempt * 2)
                    continue
                return None
            return d
        except Exception:
            time.sleep(1 + attempt * 2)
    return None


def sim(a, b):
    a, b = norm(a), norm(b)
    if not a or not b:
        return 0.0
    if a == b:
        return 1.0
    ta, tb = set(a.split()), set(b.split())
    if ta == tb:
        return 1.0
    return SequenceMatcher(None, a, b).ratio()


def base_title(t):
    import re
    t = re.sub(r"\s*[\(\[][^\)\]]*[\)\]]", "", t)
    return re.sub(r"\s+-\s+.+$", "", t).strip() or t


_artist_cache, _album_cache = {}, {}


def lookup(song):
    """song = (song_key, lead_artist, artist, title_base)"""
    key, lead, artist, title = song
    cands = []
    for q in ([f"{lead} {title}"] if lead else [title]) + ([f"{artist} {title}"] if artist and artist != lead else []):
        d = get("/search", {"q": q, "limit": 15})
        for t in (d or {}).get("data", []):
            ts = sim(base_title(t["title"]), title)
            names = [t["artist"]["name"]]
            as_ = max([sim(n, lead) for n in names] + [sim(n, artist) for n in names]) if lead else 0.6
            # interpret sa môže skrývať v dlhom zozname ("A, B & C")
            if lead and as_ < 0.75 and norm(t["artist"]["name"]) and norm(t["artist"]["name"]) in norm(artist):
                as_ = 0.85
            cands.append((ts, as_, t))
        if any(c[0] >= 0.9 and c[1] >= 0.75 for c in cands):
            break
    good = [c for c in cands if c[0] >= 0.9 and c[1] >= 0.75]
    res = {"song_key": key, "matched": 0}
    if not good:
        if cands:
            b = max(cands, key=lambda c: c[0] + c[1])
            res["best_guess"] = f'{b[2]["artist"]["name"]} - {b[2]["title"]}'
            res["guess_score"] = round(b[0] * b[1], 2)
        return res
    # známosť skladby = najpopulárnejšia verzia originálu
    best = max(good, key=lambda c: c[2].get("rank", 0))
    t = best[2]
    res.update(matched=1, match_title=round(best[0], 2), match_artist=round(best[1], 2),
               dz_id=t["id"], dz_artist=t["artist"]["name"], dz_title=t["title"],
               rank=t.get("rank", 0), duration=t.get("duration", 0),
               explicit=int(bool(t.get("explicit_lyrics"))),
               n_versions=len(good))
    full = get(f"/track/{t['id']}") or {}
    res["bpm"] = full.get("bpm") or 0
    res["release_date"] = full.get("release_date") or ""
    # najstaršie vydanie medzi zhodami = rok originálu
    aid = t["artist"]["id"]
    with _lock:
        fans = _artist_cache.get(aid)
    if fans is None:
        fans = (get(f"/artist/{aid}") or {}).get("nb_fan", 0)
        with _lock:
            _artist_cache[aid] = fans
    res["artist_fans"] = fans
    alb = t.get("album", {}).get("id")
    if alb:
        with _lock:
            g = _album_cache.get(alb)
        if g is None:
            a = get(f"/album/{alb}") or {}
            g = {"genres": [x["name"] for x in a.get("genres", {}).get("data", [])],
                 "album_date": a.get("release_date", "")}
            with _lock:
                _album_cache[alb] = g
        res["genres"] = g["genres"]
    return res


def main():
    cat = sys.argv[1] if len(sys.argv) > 1 else "data/catalog.csv"
    songs = {}
    with open(cat, encoding="utf-8") as f:
        for r in csv.DictReader(f):
            songs.setdefault(r["song_key"], (r["song_key"], r["lead_artist"], r["artist"], r["title_base"]))
    done = set()
    if os.path.exists(CACHE):
        with open(CACHE, encoding="utf-8") as f:
            for line in f:
                try:
                    done.add(json.loads(line)["song_key"])
                except Exception:
                    pass
    todo = [s for k, s in songs.items() if k not in done]
    print(f"{len(songs)} skladieb, zostáva {len(todo)}", file=sys.stderr)
    out = open(CACHE, "a", encoding="utf-8")
    n = 0
    with ThreadPoolExecutor(max_workers=6) as ex:
        for res in ex.map(lookup, todo):
            out.write(json.dumps(res, ensure_ascii=False) + "\n")
            n += 1
            if n % 200 == 0:
                out.flush()
                print(f"  {n}/{len(todo)}", file=sys.stderr)
    out.close()


if __name__ == "__main__":
    main()
