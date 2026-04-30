@echo off
setlocal EnableExtensions

REM ===============================================================
REM   Synapse - AI Job Matcher
REM   Safer Windows launcher: keeps window open + writes run_error.log
REM ===============================================================

cd /d "%~dp0"
title Synapse AI Job Matcher

set LOG=run_error.log
echo Starting Synapse AI Job Matcher... > "%LOG%"
echo Folder: %CD% >> "%LOG%"
echo. >> "%LOG%"

echo.
echo =======================================
echo  Synapse - AI Job Matcher
echo =======================================
echo.
echo Working folder: %CD%
echo.

REM Make sure this is not being run from a ZIP/temp location
echo %CD% | find /I "Temp" >nul
if not errorlevel 1 (
  echo [WARNING] It looks like you may be running this from a temporary ZIP folder.
  echo Please right-click the ZIP file, choose Extract All, then run this file from the extracted folder.
  echo.
)

REM Prefer Python 3.12 or 3.11 if available, otherwise use python/py
set PYTHON_CMD=
py -3.12 --version >nul 2>&1
if not errorlevel 1 set PYTHON_CMD=py -3.12
if not defined PYTHON_CMD (
  py -3.11 --version >nul 2>&1
  if not errorlevel 1 set PYTHON_CMD=py -3.11
)
if not defined PYTHON_CMD (
  py -3.10 --version >nul 2>&1
  if not errorlevel 1 set PYTHON_CMD=py -3.10
)
if not defined PYTHON_CMD (
  python --version >nul 2>&1
  if not errorlevel 1 set PYTHON_CMD=python
)
if not defined PYTHON_CMD (
  py --version >nul 2>&1
  if not errorlevel 1 set PYTHON_CMD=py
)

if not defined PYTHON_CMD (
  echo [ERROR] Python is not installed or not added to PATH.
  echo Install Python 3.12 from python.org and tick "Add python.exe to PATH".
  echo [ERROR] Python missing. >> "%LOG%"
  goto end
)

echo Using Python:
%PYTHON_CMD% --version
%PYTHON_CMD% --version >> "%LOG%" 2>&1
echo.

if not exist "requirements.txt" (
  echo [ERROR] requirements.txt not found. Make sure you are inside the extracted ai-job-matcher folder.
  echo [ERROR] requirements.txt not found. >> "%LOG%"
  goto end
)

if not exist "venv\Scripts\python.exe" (
  echo [1/4] Creating virtual environment...
  %PYTHON_CMD% -m venv venv >> "%LOG%" 2>&1
  if errorlevel 1 (
    echo [ERROR] Failed to create virtual environment. See run_error.log.
    goto end
  )
) else (
  echo [1/4] Virtual environment already exists.
)

call "venv\Scripts\activate.bat"
if errorlevel 1 (
  echo [ERROR] Could not activate virtual environment. Delete the venv folder and try again.
  echo [ERROR] venv activate failed. >> "%LOG%"
  goto end
)

echo [2/4] Upgrading pip...
python -m pip install --upgrade pip >> "%LOG%" 2>&1
if errorlevel 1 (
  echo [ERROR] pip upgrade failed. See run_error.log.
  goto end
)

echo [3/4] Installing requirements...
python -m pip install -r requirements.txt >> "%LOG%" 2>&1
if errorlevel 1 (
  echo [ERROR] Dependency install failed. See run_error.log.
  echo.
  echo Common fix: install Python 3.12, then delete the venv folder and run this again.
  goto end
)

echo [Optional] Downloading spaCy English model...
python -m spacy download en_core_web_sm >> "%LOG%" 2>&1
if errorlevel 1 (
  echo [WARN] spaCy model failed to download. App can still start without it.
  echo [WARN] spaCy model download failed. >> "%LOG%"
)

echo.
echo =======================================
echo  Starting server...
echo  Open this in browser:
echo  http://127.0.0.1:8000
echo =======================================
echo.
python -m backend.app

:end
echo.
echo =======================================
echo If there was an error, open run_error.log in this folder.
echo Press any key to close this window.
echo =======================================
pause >nul
exit /b
