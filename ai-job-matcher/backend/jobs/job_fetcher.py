"""
Job fetcher — pulls listings from free, no-auth job APIs.

Sources used:
  - Remotive   (https://remotive.com/api/remote-jobs)        — biggest dataset
  - RemoteOK   (https://remoteok.com/api)                    — tech-heavy
  - Arbeitnow  (https://www.arbeitnow.com/api/job-board-api) — EU-focused

Why not LinkedIn? LinkedIn has no free public jobs API. Their developer API
is restricted to approved partners and doesn't expose job search. Our three
sources together give thousands of real, current postings without auth.

Design decisions:
  - In-memory cache with a TTL so we don't hammer the APIs on every request.
  - Each source is wrapped in try/except so a single API outage doesn't break
    the whole feature.
  - All sources are normalized to a common job dict shape.
"""

from __future__ import annotations

import logging
import time
from typing import Any

import requests

logger = logging.getLogger(__name__)


# ---------------------------------------------------------------------------
# Cache
# ---------------------------------------------------------------------------
_CACHE: dict[str, Any] = {"jobs": None, "fetched_at": 0.0}
_CACHE_TTL_SECONDS = 30 * 60  # 30 minutes
_TIMEOUT = 10  # seconds per HTTP request


# ---------------------------------------------------------------------------
# Public API
# ---------------------------------------------------------------------------
def fetch_jobs(force_refresh: bool = False) -> list[dict]:
    """
    Return a unified, normalized list of jobs from all sources, with caching.

    Each job dict has:
      id, title, company, location, job_type, url, description,
      tags, posted_at, source, salary
    """
    now = time.time()
    age = now - _CACHE["fetched_at"]

    if (not force_refresh
            and _CACHE["jobs"] is not None
            and age < _CACHE_TTL_SECONDS):
        logger.info("Returning %d jobs from cache (age=%.0fs)",
                    len(_CACHE["jobs"]), age)
        return _CACHE["jobs"]

    logger.info("Fetching fresh jobs from all sources...")
    all_jobs: list[dict] = []

    for source_fn in (_fetch_remotive, _fetch_remoteok, _fetch_arbeitnow):
        try:
            jobs = source_fn()
            logger.info("  %s -> %d jobs", source_fn.__name__, len(jobs))
            all_jobs.extend(jobs)
        except Exception as e:
            # Don't let one failing source break the whole feature.
            logger.warning("  %s failed: %s", source_fn.__name__, e)

    # If everything failed, fall back to the bundled sample dataset so the
    # demo still works offline.
    if not all_jobs:
        logger.warning("All live sources failed — using bundled sample data.")
        all_jobs = _bundled_sample_jobs()

    # De-duplicate by (title, company)
    seen = set()
    deduped = []
    for job in all_jobs:
        key = (job["title"].lower().strip(), job["company"].lower().strip())
        if key not in seen:
            seen.add(key)
            deduped.append(job)

    _CACHE["jobs"] = deduped
    _CACHE["fetched_at"] = now
    logger.info("Cached %d unique jobs.", len(deduped))
    return deduped


# ---------------------------------------------------------------------------
# Source: Remotive
# ---------------------------------------------------------------------------
def _fetch_remotive() -> list[dict]:
    url = "https://remotive.com/api/remote-jobs"
    response = requests.get(url, timeout=_TIMEOUT,
                            headers={"User-Agent": "AI-Job-Matcher/1.0"})
    response.raise_for_status()
    data = response.json()

    jobs = []
    for j in data.get("jobs", []):
        jobs.append({
            "id": f"remotive-{j.get('id')}",
            "title": j.get("title", "").strip(),
            "company": j.get("company_name", "").strip(),
            "location": j.get("candidate_required_location", "Remote").strip(),
            "job_type": j.get("job_type", "").replace("_", "-").title(),
            "url": j.get("url", ""),
            "description": _strip_html(j.get("description", "")),
            "tags": j.get("tags", []) or [],
            "posted_at": j.get("publication_date", ""),
            "source": "Remotive",
            "salary": j.get("salary", ""),
        })
    return jobs


# ---------------------------------------------------------------------------
# Source: RemoteOK
# ---------------------------------------------------------------------------
def _fetch_remoteok() -> list[dict]:
    url = "https://remoteok.com/api"
    response = requests.get(
        url,
        timeout=_TIMEOUT,
        headers={"User-Agent": "AI-Job-Matcher/1.0 (educational project)"},
    )
    response.raise_for_status()
    data = response.json()

    # First element is metadata, skip it
    if isinstance(data, list) and data and "legal" in str(data[0]).lower():
        data = data[1:]

    jobs = []
    for j in data:
        if not j.get("position") or not j.get("company"):
            continue

        salary_min = j.get("salary_min") or 0
        salary_max = j.get("salary_max") or 0
        salary_str = ""
        if salary_min and salary_max:
            salary_str = f"${salary_min:,} - ${salary_max:,}"

        jobs.append({
            "id": f"remoteok-{j.get('id', j.get('slug', ''))}",
            "title": j.get("position", "").strip(),
            "company": j.get("company", "").strip(),
            "location": j.get("location", "Remote").strip() or "Remote",
            "job_type": "Full-Time",
            "url": j.get("url") or j.get("apply_url", ""),
            "description": _strip_html(j.get("description", "")),
            "tags": j.get("tags", []) or [],
            "posted_at": j.get("date", ""),
            "source": "RemoteOK",
            "salary": salary_str,
        })
    return jobs


# ---------------------------------------------------------------------------
# Source: Arbeitnow
# ---------------------------------------------------------------------------
def _fetch_arbeitnow() -> list[dict]:
    url = "https://www.arbeitnow.com/api/job-board-api"
    response = requests.get(url, timeout=_TIMEOUT,
                            headers={"User-Agent": "AI-Job-Matcher/1.0"})
    response.raise_for_status()
    data = response.json()

    jobs = []
    for j in data.get("data", []):
        jobs.append({
            "id": f"arbeitnow-{j.get('slug', '')}",
            "title": j.get("title", "").strip(),
            "company": j.get("company_name", "").strip(),
            "location": j.get("location", "").strip() or "Remote",
            "job_type": ", ".join(j.get("job_types", [])).title() or "Full-Time",
            "url": j.get("url", ""),
            "description": _strip_html(j.get("description", "")),
            "tags": j.get("tags", []) or [],
            "posted_at": j.get("created_at", ""),
            "source": "Arbeitnow",
            "salary": "",
        })
    return jobs


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
def _strip_html(html: str) -> str:
    """Quick-and-dirty HTML-to-text. Good enough for matching purposes."""
    if not html:
        return ""
    import re
    # Remove script/style blocks entirely
    html = re.sub(r"<(script|style).*?</\1>", " ", html,
                  flags=re.DOTALL | re.IGNORECASE)
    # Replace block tags with newlines for readability
    html = re.sub(r"</(p|div|li|h[1-6]|br)>", "\n", html, flags=re.IGNORECASE)
    # Strip remaining tags
    html = re.sub(r"<[^>]+>", " ", html)
    # Decode common entities
    replacements = {
        "&nbsp;": " ", "&amp;": "&", "&lt;": "<", "&gt;": ">",
        "&quot;": '"', "&#39;": "'", "&rsquo;": "'", "&ldquo;": '"',
        "&rdquo;": '"',
    }
    for k, v in replacements.items():
        html = html.replace(k, v)
    # Collapse whitespace
    html = re.sub(r"\s+", " ", html)
    return html.strip()


def _bundled_sample_jobs() -> list[dict]:
    """
    Fallback dataset used only if every live API is unreachable.
    Small, but enough to demo the matching flow offline.
    """
    return [
        {
            "id": "sample-1",
            "title": "Junior Python Developer",
            "company": "Acme Labs",
            "location": "Remote",
            "job_type": "Full-Time",
            "url": "https://example.com/jobs/1",
            "description": ("We're looking for a Python developer to join our "
                            "team. Experience with Django, REST APIs, and "
                            "PostgreSQL required. Bonus for Docker and AWS."),
            "tags": ["python", "django", "postgresql", "docker"],
            "posted_at": "",
            "source": "Sample",
            "salary": "$60,000 - $80,000",
        },
        {
            "id": "sample-2",
            "title": "Frontend Engineer",
            "company": "Pixel Forge",
            "location": "Remote",
            "job_type": "Full-Time",
            "url": "https://example.com/jobs/2",
            "description": ("React + TypeScript role. You'll build modern UIs "
                            "with Next.js and Tailwind CSS. Strong CSS and "
                            "accessibility skills required."),
            "tags": ["react", "typescript", "next.js", "tailwind"],
            "posted_at": "",
            "source": "Sample",
            "salary": "$70,000 - $95,000",
        },
        {
            "id": "sample-3",
            "title": "Machine Learning Engineer",
            "company": "Neural Stack",
            "location": "Remote",
            "job_type": "Full-Time",
            "url": "https://example.com/jobs/3",
            "description": ("Build production ML systems. Experience with "
                            "PyTorch, scikit-learn, NumPy and Pandas required. "
                            "Familiarity with NLP and Hugging Face is a plus."),
            "tags": ["python", "pytorch", "ml", "nlp"],
            "posted_at": "",
            "source": "Sample",
            "salary": "$110,000 - $150,000",
        },
        {
            "id": "sample-4",
            "title": "DevOps Engineer",
            "company": "Cloud Atlas",
            "location": "Remote",
            "job_type": "Full-Time",
            "url": "https://example.com/jobs/4",
            "description": ("Manage infrastructure on AWS. Strong Kubernetes, "
                            "Docker, Terraform and CI/CD pipeline experience "
                            "required. Linux and Bash a must."),
            "tags": ["aws", "kubernetes", "docker", "terraform"],
            "posted_at": "",
            "source": "Sample",
            "salary": "$100,000 - $140,000",
        },
        {
            "id": "sample-5",
            "title": "Full Stack Developer",
            "company": "Loom & Loop",
            "location": "Remote",
            "job_type": "Full-Time",
            "url": "https://example.com/jobs/5",
            "description": ("Node.js + React role. We build with Express, "
                            "MongoDB, and deploy on AWS. Looking for someone "
                            "comfortable across the stack."),
            "tags": ["node.js", "react", "mongodb", "aws"],
            "posted_at": "",
            "source": "Sample",
            "salary": "$80,000 - $110,000",
        },
    ]
