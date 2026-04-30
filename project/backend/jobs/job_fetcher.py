"""
Job fetcher — project-owned job data only.

External job APIs are removed. The app reads jobs from data/custom_jobs.json
and from jobs posted through the admin panel. Uploaded resumes are parsed in
memory only and are not saved.
"""

from __future__ import annotations

import json
import logging
import re
import time
from pathlib import Path
from typing import Any

logger = logging.getLogger(__name__)

_CACHE: dict[str, Any] = {"jobs": None, "fetched_at": 0.0}
_CACHE_TTL_SECONDS = 60
_PROJECT_ROOT = Path(__file__).resolve().parent.parent.parent
_CUSTOM_JOBS_FILE = _PROJECT_ROOT / "data" / "custom_jobs.json"
_APPLY_URL = "https://ctrlaltimran.com"


def fetch_jobs(force_refresh: bool = False) -> list[dict]:
    now = time.time()
    age = now - _CACHE["fetched_at"]
    if not force_refresh and _CACHE["jobs"] is not None and age < _CACHE_TTL_SECONDS:
        return _CACHE["jobs"]
    jobs = load_custom_jobs()
    _CACHE["jobs"] = jobs
    _CACHE["fetched_at"] = now
    logger.info("Loaded %d project-owned jobs.", len(jobs))
    return jobs


def load_custom_jobs() -> list[dict]:
    if not _CUSTOM_JOBS_FILE.exists():
        _CUSTOM_JOBS_FILE.parent.mkdir(parents=True, exist_ok=True)
        _CUSTOM_JOBS_FILE.write_text("[]", encoding="utf-8")
        return []
    try:
        data = json.loads(_CUSTOM_JOBS_FILE.read_text(encoding="utf-8"))
        if not isinstance(data, list):
            return []
        return [_normalize_custom_job(j, idx) for idx, j in enumerate(data, start=1)]
    except Exception as e:
        logger.warning("Could not read custom jobs: %s", e)
        return []


def save_custom_job(job: dict) -> dict:
    _CUSTOM_JOBS_FILE.parent.mkdir(parents=True, exist_ok=True)
    existing_raw = []
    if _CUSTOM_JOBS_FILE.exists():
        try:
            existing_raw = json.loads(_CUSTOM_JOBS_FILE.read_text(encoding="utf-8"))
            if not isinstance(existing_raw, list):
                existing_raw = []
        except Exception:
            existing_raw = []
    clean = _normalize_custom_job(job, len(existing_raw) + 1)
    existing_raw.append(clean)
    _CUSTOM_JOBS_FILE.write_text(json.dumps(existing_raw, indent=2), encoding="utf-8")
    _CACHE["jobs"] = None
    _CACHE["fetched_at"] = 0.0
    return clean


def _clean_text(value: object, max_len: int = 8000) -> str:
    text = str(value or "").strip()
    text = re.sub(r"[\x00-\x08\x0b\x0c\x0e-\x1f]", "", text)
    return text[:max_len]


def _normalize_custom_job(j: dict, idx: int) -> dict:
    tags = j.get("tags", [])
    if isinstance(tags, str):
        tags = [t.strip() for t in tags.split(",") if t.strip()]
    if not isinstance(tags, list):
        tags = []
    tags = [_clean_text(t, 40) for t in tags[:12] if _clean_text(t, 40)]
    title = _clean_text(j.get("title") or "Software Job", 120)
    company = _clean_text(j.get("company") or "CtrlAltImran Jobs", 120)
    location = _clean_text(j.get("location") or "Karachi, Pakistan", 140)
    return {
        "id": _clean_text(j.get("id") or f"job-{idx:04d}", 40),
        "title": title,
        "company": company,
        "location": location,
        "job_type": _clean_text(j.get("job_type") or "Full-Time", 40),
        "url": _APPLY_URL,
        "description": _clean_text(j.get("description") or f"{title} role at {company} in {location}.", 5000),
        "tags": tags,
        "posted_at": _clean_text(j.get("posted_at") or "Admin Posted", 80),
        "source": _clean_text(j.get("source") or "CtrlAltImran Jobs", 80),
        "salary": _clean_text(j.get("salary") or "", 80),
        "lat": j.get("lat"),
        "lng": j.get("lng"),
    }
