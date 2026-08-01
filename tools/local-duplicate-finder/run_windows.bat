@echo off
setlocal EnableDelayedExpansion

rem Relaunch itself inside a window that stays open no matter what happens,
rem so it can never vanish before you can read an error message.
if not defined DUPFINDER_KEEP_OPEN (
    set DUPFINDER_KEEP_OPEN=1
    cmd /k ""%~f0""
    exit /b
)

echo === Ighi's Home Radio - hladac duplicitnych skladieb ===
echo.

rem --- Find a working Python launcher (py or python) ---
set PYCMD=
py --version >nul 2>&1
if not errorlevel 1 set PYCMD=py
if not defined PYCMD (
    python --version >nul 2>&1
    if not errorlevel 1 set PYCMD=python
)

if not defined PYCMD (
    echo CHYBA: Python sa nenasiel.
    echo Nainstaluj ho z https://www.python.org/downloads/
    echo Pri instalacii NEZABUDNI zaskrtnut "Add python.exe to PATH".
    echo.
    echo Toto okno zostava otvorene - zavri ho manualne, alebo skus znova po instalacii Pythonu.
    goto :end
)
echo Pouzivam: %PYCMD%
echo.

set /p MUSIC_FOLDER=Zadaj cestu k priecinku s hudbou a stlac Enter:
if "%MUSIC_FOLDER%"=="" (
    echo Nezadal si ziadny priecinok.
    goto :end
)

rem Strip a trailing backslash (e.g. "C:\Music\") - it breaks quoting on Windows.
if "%MUSIC_FOLDER:~-1%"=="\" set MUSIC_FOLDER=%MUSIC_FOLDER:~0,-1%

if not exist "%MUSIC_FOLDER%" (
    echo CHYBA: priecinok "%MUSIC_FOLDER%" neexistuje. Skontroluj cestu a skus znova.
    goto :end
)

echo.
echo Instalujem/kontrolujem potrebne kniznice ^(mutagen^)...
%PYCMD% -m pip install -r "%~dp0requirements.txt"
if errorlevel 1 (
    echo.
    echo CHYBA pri instalacii kniznic ^(vid hlasenie vyssie^).
    goto :end
)

echo.
echo Prehladavam priecinok, chvilu to potrva...
%PYCMD% "%~dp0find_duplicates.py" "%MUSIC_FOLDER%" --report "%~dp0duplicates.csv"
if errorlevel 1 (
    echo.
    echo Skript skoncil s chybou ^(vid hlasenie vyssie^).
    goto :end
)

echo.
echo Hotovo. Report najdes v subore: %~dp0duplicates.csv

:end
echo.
echo (Toto okno sa neuzavrie samo - zavri ho krizikom, az si precitas hlasenie.)
pause
