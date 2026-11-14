# Hit selector – výber hitov pre vysielanie

Z hudobného fondu vyberie len skladby, ktoré pozná čo najviac ľudí, a pripraví z nich
playlisty pre AzuraCast podľa dennej doby.

## Čo sa hrá kedy

| Čas | Playlist | Obsah |
|---|---|---|
| 06–08 a 16–21 | `IHR_A_megahity` + `IHR_B_hity` | originály, trieda A hrá častejšie |
| 08–16 | `IHR_operacne_saly` | len najväčšie hity, bez remixov a explicitných textov |
| 21–06 | A + B + `IHR_vecer_remixy` | navyše remixy skladieb, ktorých originál je trieda A |

## Ako sa počíta známosť (skóre 0–100)

1. **Deezer rank** skladby (85 %). Berie sa najpopulárnejšia verzia originálu, takže
   remaster ani remix skóre neznižuje. Zahraničné skladby: logaritmická škála
   (rank 100 000 = 0, 1 000 000 = 100). SK/CZ skladby: poradie vo vlastnom fonde,
   lebo svetové čísla ich podhodnocujú.
2. **Fanúšikovia interpreta** (15 %).
3. **Spotify – 2 500 najstreamovanejších skladieb všetkých čias** (kworb.net). Skladba
   z tohto zoznamu nemôže spadnúť pod triedu B; nad 1,5 mld. streamov je vždy A.
   Vyvažuje to, že Deezer uprednostňuje novinky.

Triedy: **A** megahit, **B** hit, **C** a **D** sa nehrajú (súbory sa nemažú).
Rap, rock, alternatíva a ďalšie okrajové žánre idú do vysielania len v triede A.
Prahy sú v `config.json`.

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
- `na_rozhodnutie.csv` – skladby, ktoré sa nepodarilo spoľahlivo spárovať.

## Oponentúra

Návrh pripomienkovali GPT-6.1 Sol a Gemini 4 Carbon (14. 11. 2026). Zapracované:
orezanie škály, samostatné prahy pre SK/CZ, druhý nezávislý signál, pevné pravidlá
nad výnimkami, najvyšší rank naprieč verziami, minimálna veľkosť denného bloku.
Nezapracované: lokálne dáta o rádiovom hraní v SK/CZ (IFPI/Radiomonitor nie sú voľne
dostupné) a test rozpoznania skladieb u poslucháčov.
