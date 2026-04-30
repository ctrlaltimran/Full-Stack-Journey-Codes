"""
FastAPI app — entry point.

Endpoints:
  POST /api/analyze     -- upload a resume, get ranked matching jobs
  GET  /api/jobs/refresh -- force-refresh the job cache
  GET  /api/health       -- health check

Static files (frontend) are mounted at the root.
"""

from __future__ import annotations

import logging
import time
from pathlib import Path

from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import FileResponse
from fastapi.staticfiles import StaticFiles

from backend.parser.resume_parser import parse_resume, extract_metadata
from backend.nlp.skill_extractor import (
    extract_skills, extract_entities,
    detect_career_level, resume_quality_tips,
)
from backend.graph.skill_graph import adjacent_skills, explore_paths
from backend.jobs.job_fetcher import fetch_jobs
from backend.matcher.job_matcher import rank_jobs, JobFilters

# ---------------------------------------------------------------------------
# Logging
# ---------------------------------------------------------------------------
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
    datefmt="%H:%M:%S",
)
logger = logging.getLogger("ai-job-matcher")


# ---------------------------------------------------------------------------
# App
# ---------------------------------------------------------------------------
app = FastAPI(
    title="AI Job Matcher",
    description="Resume-based job recommendation engine.",
    version="1.0.0",
)

# Allow the frontend (loaded from same origin, but useful if user hosts apart)
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)


# Resolve the project root and frontend path relative to this file
_PROJECT_ROOT = Path(__file__).resolve().parent.parent
_FRONTEND_DIR = _PROJECT_ROOT / "frontend"


# ---------------------------------------------------------------------------
# API: health check
# ---------------------------------------------------------------------------
@app.get("/api/health")
def health():
    return {"status": "ok", "version": "1.0.0"}


# ---------------------------------------------------------------------------
# API: refresh job cache
# ---------------------------------------------------------------------------
@app.get("/api/jobs/refresh")
def refresh_jobs():
    jobs = fetch_jobs(force_refresh=True)
    return {"count": len(jobs)}


# ---------------------------------------------------------------------------
# API: analyze resume
# ---------------------------------------------------------------------------
@app.post("/api/analyze")
async def analyze(
    resume: UploadFile = File(...),
    location_keyword: str = Form(""),
    job_type: str = Form(""),
    min_score: float = Form(0.0),
    limit: int = Form(25),
):
    """
    Upload a resume and get back:
      - parsed skills + metadata
      - ranked matching jobs
      - resume quality tips
      - adjacent-skills suggestions (BFS)
    """
    t0 = time.time()

    # 1. Read & parse resume
    file_bytes = await resume.read()
    if not file_bytes:
        raise HTTPException(400, "Empty file uploaded.")
    if len(file_bytes) > 10 * 1024 * 1024:  # 10 MB cap
        raise HTTPException(400, "File too large (max 10 MB).")

    try:
        resume_text = parse_resume(file_bytes, resume.filename or "resume")
    except (ValueError, RuntimeError) as e:
        raise HTTPException(400, str(e))

    logger.info("Parsed resume: %d chars in %.2fs",
                len(resume_text), time.time() - t0)

    # 2. NLP — skills, entities, metadata
    skills = extract_skills(resume_text)
    metadata = extract_metadata(resume_text)
    entities = extract_entities(resume_text)
    career_level = detect_career_level(resume_text, metadata.get("years_experience"))
    tips = resume_quality_tips(resume_text, skills, metadata)

    # 3. BFS — adjacent skills
    adjacent = adjacent_skills(skills, max_hops=2, limit=8)

    # 4. Career-path exploration via DFS on top skill (if any)
    career_paths = []
    if skills:
        top_skill = skills[0]
        for path in explore_paths(top_skill, max_depth=3, max_paths=3):
            career_paths.append(path)

    # 5. Fetch jobs
    all_jobs = fetch_jobs()

    # 6. Match
    filters = JobFilters(
        location_keyword=location_keyword or None,
        job_type=job_type or None,
        min_score=min_score,
        career_level=career_level,
        limit=int(limit),
    )
    matched = rank_jobs(resume_text, skills, career_level, all_jobs, filters)

    elapsed = time.time() - t0
    logger.info("Analysis complete: %d skills, %d matches in %.2fs",
                len(skills), len(matched), elapsed)

    return {
        "elapsed_seconds": round(elapsed, 2),
        "resume": {
            "skills": skills,
            "skill_count": len(skills),
            "career_level": career_level,
            "metadata": metadata,
            "entities": entities,
            "tips": tips,
            "preview": resume_text[:400],
        },
        "graph": {
            "adjacent_skills": adjacent,
            "career_paths": career_paths,
        },
        "jobs": {
            "total_available": len(all_jobs),
            "matched_count": len(matched),
            "matches": matched,
        },
    }


# ---------------------------------------------------------------------------
# Static frontend (mounted last so it doesn't shadow /api routes)
# ---------------------------------------------------------------------------
if _FRONTEND_DIR.exists():
    # Serve index.html at root
    @app.get("/")
    def root():
        return FileResponse(_FRONTEND_DIR / "index.html")

    # Mount /css, /js, /assets
    for sub in ("css", "js", "assets"):
        sub_path = _FRONTEND_DIR / sub
        if sub_path.exists():
            app.mount(f"/{sub}", StaticFiles(directory=sub_path), name=sub)
else:
    logger.warning("Frontend directory not found at %s", _FRONTEND_DIR)


# ---------------------------------------------------------------------------
# Entrypoint for `python -m backend.app`
# ---------------------------------------------------------------------------
if __name__ == "__main__":
    import uvicorn
    uvicorn.run("backend.app:app", host="127.0.0.1", port=8000, reload=False)
