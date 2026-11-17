# Hit selector – výber hitov pre vysielanie

Z hudobného fondu vyberie len skladby, ktoré pozná čo najviac ľudí, a pripraví z nich
playlisty pre AzuraCast podľa dennej doby.

## Čo sa hrá kedy

| Čas | Playlist | Obsah |
|---|---|---|
| 06–08 a 16–21 | `IHR_A_megahity` + `IHR_B_hity` | originály, trieda A hrá častejšie |
| 08–16 | `IHR_operacne_saly` | len najväčšie hity, bez remixov a explicitných textov |
| 21–06 | A + B + `IHR_vecer_remixy` | navyše remixy skladieb, ktorých originál je trieda A |

## Ako sa počíta hitovosť (skóre 0–100)

Každý zdroj dá skladbe signál 0–100. Skóre = 70 % najsilnejší + 30 % druhý najsilnejší
signál, takže megahit musí potvrdiť aspoň druhý, nezávislý zdroj.

| Zdroj | Čo meria | Súbor |
|---|---|---|
| Rádiá ČR a SR (ČNS IFPI) | týždenné hitparády hranosti 2006–2026: Top 100 a domáca Top 50 | `data/ifpi_radio.csv` |
| Spotify ČR + SR (kworb.net) | prehratia v ČR a SR od 2017, prepočet na obyvateľa | `data/kworb_spotify_czsk.csv` |
| Spotify svet (kworb.net) | 2 500 najstreamovanejších skladieb všetkých čias | `data/kworb_spotify_top.csv` |
| Rebríčky US a UK (Wikipédia) | Billboard ročná Top 100 a UK Top 10, 1960–2025 | `data/wiki_charts.csv` |
| Hitové playlisty (Deezer) | v koľkých playlistoch typu „České hity“, „80s hits“ skladba je | `data/deezer_playlists.csv` |
| Deezer rank | aktuálna popularita; berie sa najpopulárnejšia verzia originálu | `data/deezer_cache.jsonl` |
| Last.fm | počet poslucháčov skladby (treba `LASTFM_API_KEY`) | `data/lastfm.csv` |

SK/CZ skladby sa pri Deezeri a Last.fm hodnotia poradím vo vlastnom fonde, lebo svetové
čísla ich podhodnocujú.

Triedy (prahy sú v `config.json`):

- **A megahit** – vysoké skóre a potvrdenie z viacerých zdrojov,
- **B hit** – nižšie skóre s aspoň dvoma zdrojmi, alebo veľmi silné hranie v rádiách ČR/SR,
- **C, D** – nehrajú sa (súbory sa nemažú).

Rap, rock, alternatíva a ďalšie okrajové žánre idú do vysielania len v triede A.

## Pevné pravidlá (neobíde ich ani ručná výnimka)

- remix nikdy pred 21:00,
- v bloku 08–16 žiadne explicitné texty a žiadne remixy,
- remix sa hrá len vtedy, keď je jeho originál trieda A.

## Ručné zásahy

- `data/vynimky.csv` – stĺpce `interpret,nazov,trieda`. Tu sa dá skladbe natrvalo určiť
  trieda (A/B/C/D), keď sa výpočet mýli.
- `data/operacne_saly.txt` – jeden riadok = `Interpret - Názov`. Kým je súbor prázdny,
  blok 08–16 tvorí trieda A. Po doplnení sa hrá len tento výber (ak je menší než 250
  skladieb, doplní sa najlepšími hitmi, aby sa neopakoval).

## Spustenie

```bash
python3 build_catalog.py zoznam_mirror.txt zoznam_new_archive.txt -o data/catalog.csv
```

```bash
python3 enrich_deezer.py data/catalog.csv
```

```bash
python3 fetch_kworb.py
```

```bash
python3 fetch_ifpi.py
```

```bash
python3 fetch_wiki_charts.py
```

```bash
python3 fetch_deezer_playlists.py
```

```bash
LASTFM_API_KEY=... python3 fetch_lastfm.py
```

```bash
python3 score.py
```

Zoznamy súborov vzniknú na Macu príkazom `find <priečinok> -type f -iname "*.mp3" > zoznam.txt`.
Párovanie s Deezerom trvá pri 7 000 skladbách asi hodinu a dá sa prerušiť – pokračuje,
kde skončilo.

## Výstupy (`out/`)

- `skladby_skore.csv` – všetky skladby so skóre, triedou a dôvodom,
- `IHR_*.m3u` – playlisty na import do AzuraCastu (názvy súborov z mirroru),
- `hity_na_doplnenie_do_mirroru.csv` – hity z New Archive, ktoré na streame chýbajú,
- `vyradit_z_rotacie.csv` – čo je teraz na streame a hitom nie je,
- `na_rozhodnutie.csv` – skladby bez akéhokoľvek signálu,
- `hranicne_pripady.csv` – skladby tesne okolo prahu triedy B, vhodné na ručné posúdenie.

## Oponentúra

Návrh pripomienkovali GPT-6.1 Sol a Gemini 4 Carbon (14. 11. 2026). Zapracované:
orezanie škál, samostatné prahy pre SK/CZ, viac nezávislých zdrojov vrátane lokálneho
rádiového hrania, pevné pravidlá nad výnimkami, najvyšší rank naprieč verziami,
minimálna veľkosť denného bloku. Nezapracované: test rozpoznania skladieb u poslucháčov.

## Známe obmedzenia

- Rádiové hitparády ČR/SR sú verejne dostupné až od roku 2006; staršie domáce hity
  stoja na playlistoch, lokálnom Spotify a Last.fm.
- Zhoda so zdrojmi ide cez názov a interpreta; odlišne zapísané názvy sa nemusia nájsť.
