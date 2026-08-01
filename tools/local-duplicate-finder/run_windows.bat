@echo off
setlocal

echo === Ighi's Home Radio - hladac duplicitnych skladieb ===
echo.

set /p MUSIC_FOLDER=Zadaj cestu k priecinku s hudbou a stlac Enter:
if "%MUSIC_FOLDER%"=="" (
    echo Nezadal si ziadny priecinok. Koniec.
    pause
    exit /b 1
)

echo.
echo Instalujem/kontrolujem potrebne kniznice...
py -m pip install -r "%~dp0requirements.txt" --quiet
if errorlevel 1 (
    echo Chyba pri instalacii kniznic. Skontroluj, ci mas nainstalovany Python (python.org) a skus znova.
    pause
    exit /b 1
)

echo.
echo Prehladavam priecinok, chvilu to potrva...
py "%~dp0find_duplicates.py" "%MUSIC_FOLDER%" --report "%~dp0duplicates.csv"

echo.
echo Hotovo. Report najdes v subore: %~dp0duplicates.csv
pause
