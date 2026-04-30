#!/usr/bin/env bash
# ===============================================================
#   Synapse - AI Job Matcher
#   One-shot Unix/macOS launcher
# ===============================================================

set -e

echo
echo " ======================================="
echo "  Synapse - AI Job Matcher"
echo " ======================================="
echo

# Move to script's directory
cd "$(dirname "$0")"

# Pick a python command
PYTHON_CMD=""
if command -v python3 >/dev/null 2>&1; then
    PYTHON_CMD="python3"
elif command -v python >/dev/null 2>&1; then
    PYTHON_CMD="python"
else
    echo "[ERROR] Python is not installed."
    echo "        Install Python 3.10+ from https://www.python.org/downloads/"
    exit 1
fi

echo "Using: $($PYTHON_CMD --version)"

# Create venv if missing
if [ ! -d "venv" ]; then
    echo "[1/4] Creating virtual environment..."
    $PYTHON_CMD -m venv venv
else
    echo "[1/4] Virtual environment already exists. Skipping."
fi

# Activate
source venv/bin/activate

# Install deps once
if [ ! -f "venv/.deps_installed" ]; then
    echo "[2/4] Installing dependencies (one-time, ~3-5 min)..."
    pip install --upgrade pip --quiet
    pip install -r requirements.txt
    echo "[3/4] Downloading spaCy English model..."
    python -m spacy download en_core_web_sm
    touch venv/.deps_installed
else
    echo "[2/4] Dependencies already installed. Skipping."
    echo "[3/4] spaCy model already present. Skipping."
fi

# Run server
echo "[4/4] Starting server on http://127.0.0.1:8000"
echo
echo " Open your browser to:  http://127.0.0.1:8000"
echo " Press Ctrl+C to stop the server."
echo
python -m backend.app
