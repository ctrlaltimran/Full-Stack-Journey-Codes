@echo off
REM ===============================================================
REM   Synapse - AI Job Matcher
REM   One-shot Windows launcher (tries python, then py launcher)
REM ===============================================================

echo.
echo  =======================================
echo   Synapse - AI Job Matcher
echo  =======================================
echo.

REM Move to the script's directory
cd /d "%~dp0"

REM Try 'python' first, then 'py' (Windows Python Launcher)
set PYTHON_CMD=
python --version >nul 2>&1
if not errorlevel 1 (
    set PYTHON_CMD=python
    goto :found_python
)

py --version >nul 2>&1
if not errorlevel 1 (
    set PYTHON_CMD=py
    goto :found_python
)

REM Neither worked
echo [ERROR] Python is not installed, or not on PATH.
echo.
echo   Fix this in 5 minutes:
echo     1. Visit  https://www.python.org/downloads/
echo     2. Download Python 3.10 or newer
echo     3. RUN THE INSTALLER and CHECK the box
echo        "Add python.exe to PATH" on the first screen
echo     4. Close this window, then double-click run.bat again
echo.
pause
exit /b 1

:found_python
echo Using: %PYTHON_CMD%
%PYTHON_CMD% --version
echo.

REM Create venv if missing
if not exist "venv\" (
    echo [1/4] Creating virtual environment...
    %PYTHON_CMD% -m venv venv
    if errorlevel 1 (
        echo [ERROR] Failed to create virtual environment.
        pause
        exit /b 1
    )
) else (
    echo [1/4] Virtual environment already exists. Skipping.
)

REM Activate venv
call venv\Scripts\activate.bat

REM Install dependencies (only on first run)
if not exist "venv\.deps_installed" (
    echo [2/4] Installing dependencies (one-time, ~3-5 min)...
    python -m pip install --upgrade pip --quiet
    python -m pip install -r requirements.txt
    if errorlevel 1 (
        echo [ERROR] Dependency install failed.
        pause
        exit /b 1
    )
    echo [3/4] Downloading spaCy English model...
    python -m spacy download en_core_web_sm
    type nul > venv\.deps_installed
) else (
    echo [2/4] Dependencies already installed. Skipping.
    echo [3/4] spaCy model already present. Skipping.
)

REM Run the server
echo [4/4] Starting server on http://127.0.0.1:8000
echo.
echo  Open your browser to:  http://127.0.0.1:8000
echo  Press Ctrl+C to stop the server.
echo.
python -m backend.app

pause
