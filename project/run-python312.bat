@echo off
setlocal
cd /d "%~dp0"

echo.
echo =======================================
echo  Synapse - AI Job Matcher
 echo =======================================
echo.
echo Working directory: %CD%
echo.

REM Prefer Python 3.12 because Python 3.14 breaks some dependencies.
set "PYTHON_CMD=py -3.12"
py -3.12 --version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Python 3.12 was not found.
    echo Install Python 3.12 from python.org and tick Add python.exe to PATH.
    goto end
)

echo Using Python 3.12:
%PYTHON_CMD% --version
echo.

if not exist "venv312\Scripts\python.exe" (
    echo [1/4] Creating virtual environment...
    %PYTHON_CMD% -m venv venv312
    if errorlevel 1 goto fail
) else (
    echo [1/4] Virtual environment already exists. Skipping.
)

call "venv312\Scripts\activate.bat"
if errorlevel 1 goto fail

if not exist "venv312\.deps_installed" (
    echo [2/4] Installing dependencies. This may take a few minutes.
    python -m pip install --upgrade pip
    if errorlevel 1 goto fail
    python -m pip install -r requirements.txt
    if errorlevel 1 goto fail
    echo [3/4] Downloading spaCy English model...
    python -m spacy download en_core_web_sm
    if errorlevel 1 echo [WARN] spaCy model failed. App can still start.
    type nul > "venv312\.deps_installed"
) else (
    echo [2/4] Dependencies already installed. Skipping.
    echo [3/4] spaCy model already checked. Skipping.
)

echo.
echo =======================================
echo [4/4] Starting server
echo Open: http://127.0.0.1:8000
echo Press CTRL+C to stop.
echo =======================================
echo.
python -m backend.app
goto end

:fail
echo.
echo [ERROR] Something failed. Read the error above.

:end
echo.
echo Press any key to close...
pause >nul
exit /b
