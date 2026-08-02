# Hľadač duplicitných skladieb (lokálny nástroj)

Python skript, ktorý si spustíš **na svojom vlastnom počítači** (nie v cloude) a ktorý
prehľadá zadaný priečinok s hudbou a nájde duplicitné skladby — aj keď majú iný
formát, kvalitu alebo mierne iný názov súboru. Rozpoznáva duplicity podľa
tagov interpret + názov (ID3/FLAC/M4A tagy), s fallbackom na názov súboru
v tvare `Interpret - Nazov.mp3`.

Nič sa automaticky nemaže. Skript len vypíše prehľadný report (CSV), prípadne
vie duplicity presunúť (nie zmazať) do samostatného priečinka na kontrolu.

## Rýchly štart (Windows, bez konzoly)

1. Nainštaluj [Python](https://www.python.org/downloads/) (pri inštalácii zaškrtni
   "Add python.exe to PATH").
2. Stiahni si tento priečinok (`tools/local-duplicate-finder/`) na počítač.
3. Dvojklikni na `run_windows.bat`, zadaj cestu k priečinku s hudbou a stlač Enter.
4. Po skončení sa otvorí `duplicates.csv` s prehľadom (Excel/Google Sheets).

## Manuálne spustenie (Windows/macOS/Linux)

```bash
pip install -r requirements.txt

# Len vypíš report
python find_duplicates.py "C:\Users\meno\Music" --report duplicates.csv

# Report + presun duplicitov (horšia kvalita) do samostatného priečinka
python find_duplicates.py "C:\Users\meno\Music" --move-to "C:\Users\meno\Music\_duplicates"

# Zapni aj priblizne porovnávanie názvov (chytí preklepy/varianty tagov)
python find_duplicates.py "C:\Users\meno\Music" --fuzzy --fuzzy-threshold 0.9
```

## Ako sa vyberá, ktorá kópia sa ponechá ("KEEP")

Pri každej skupine duplicít sa ako "KEEP" označí súbor s najvyššou kvalitou:
1. formát (FLAC/WAV > M4A/AAC > OGG/OPUS > MP3 > WMA),
2. bitrate,
3. veľkosť súboru.

Ostatné v skupine sú označené ako `DUPLICATE` v CSV reporte, resp. presunuté
pri použití `--move-to`.

## Podporované formáty

`.mp3 .flac .wav .m4a .aac .ogg .wma .opus`
