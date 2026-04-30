"""
FastAPI app — AI Job Matcher.

The app uses project-owned jobs only. Uploaded resumes are parsed in memory
for matching and are not stored on disk.
"""

from __future__ import annotations

import logging
import os
import secrets
import time
from pathlib import Path

from fastapi import Cookie, FastAPI, File, Form, HTTPException, Response, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import FileResponse
from fastapi.staticfiles import StaticFiles
from pydantic import BaseModel, Field

from backend.parser.resume_parser import parse_resume, extract_metadata
from backend.nlp.skill_extractor import (
    extract_skills, extract_entities,
    detect_career_level, resume_quality_tips,
)
from backend.graph.skill_graph import adjacent_skills, explore_paths
from backend.jobs.job_fetcher import fetch_jobs, load_custom_jobs, save_custom_job
from backend.matcher.job_matcher import rank_jobs, JobFilters

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
    datefmt="%H:%M:%S",
)
logger = logging.getLogger("ai-job-matcher")


class CustomJobIn(BaseModel):
    title: str = Field(..., min_length=2, max_length=120)
    company: str = Field(default="CtrlAltImran Jobs", max_length=120)
    location: str = Field(default="Karachi, Pakistan", max_length=140)
    job_type: str = Field(default="Full-Time", max_length=40)
    salary: str = Field(default="", max_length=80)
    tags: list[str] | str = Field(default_factory=list)
    description: str = Field(default="", max_length=5000)
    url: str = Field(default="https://ctrlaltimran.com")


class LoginIn(BaseModel):
    username: str = Field(..., min_length=1, max_length=80)
    password: str = Field(..., min_length=1, max_length=200)


app = FastAPI(
    title="AI Job Matcher",
    description="Resume-based job recommendation engine using project-owned jobs.",
    version="1.1.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

_PROJECT_ROOT = Path(__file__).resolve().parent.parent
_FRONTEND_DIR = _PROJECT_ROOT / "frontend"

ADMIN_USERNAME = os.environ.get("AJM_ADMIN_USERNAME", "admin")
ADMIN_PASSWORD = os.environ.get("AJM_ADMIN_PASSWORD", "admin123")
_ADMIN_SESSIONS: set[str] = set()


def _is_admin(session_token: str | None) -> bool:
    return bool(session_token and session_token in _ADMIN_SESSIONS)


def _require_admin(session_token: str | None) -> None:
    if not _is_admin(session_token):
        raise HTTPException(status_code=401, detail="Admin login required.")


@app.get("/api/health")
def health():
    return {"status": "ok", "version": "1.1.0", "job_source": "local"}


@app.get("/api/jobs/refresh")
def refresh_jobs():
    jobs = fetch_jobs(force_refresh=True)
    return {"count": len(jobs), "source": "local"}


@app.post("/api/admin/login")
def admin_login(payload: LoginIn, response: Response):
    ok_user = secrets.compare_digest(payload.username, ADMIN_USERNAME)
    ok_pass = secrets.compare_digest(payload.password, ADMIN_PASSWORD)
    if not (ok_user and ok_pass):
        raise HTTPException(status_code=401, detail="Invalid admin username or password.")
    token = secrets.token_urlsafe(32)
    _ADMIN_SESSIONS.add(token)
    response.set_cookie(
        "ajm_admin_session",
        token,
        httponly=True,
        samesite="lax",
        secure=False,
        max_age=60 * 60 * 8,
    )
    return {"ok": True, "username": ADMIN_USERNAME}


@app.post("/api/admin/logout")
def admin_logout(response: Response, ajm_admin_session: str | None = Cookie(default=None)):
    if ajm_admin_session:
        _ADMIN_SESSIONS.discard(ajm_admin_session)
    response.delete_cookie("ajm_admin_session")
    return {"ok": True}


@app.get("/api/admin/status")
def admin_status(ajm_admin_session: str | None = Cookie(default=None)):
    logged_in = _is_admin(ajm_admin_session)
    return {"authenticated": logged_in, "username": ADMIN_USERNAME if logged_in else None}


@app.get("/api/admin/jobs")
def admin_jobs(ajm_admin_session: str | None = Cookie(default=None)):
    _require_admin(ajm_admin_session)
    jobs = load_custom_jobs()
    return {"count": len(jobs), "jobs": jobs}


@app.post("/api/admin/jobs")
def admin_add_job(job: CustomJobIn, ajm_admin_session: str | None = Cookie(default=None)):
    _require_admin(ajm_admin_session)
    saved = save_custom_job(job.model_dump())
    return {"ok": True, "job": saved, "count": len(load_custom_jobs())}


@app.post("/api/analyze")
async def analyze(
    resume: UploadFile = File(...),
    location_keyword: str = Form(""),
    job_type: str = Form(""),
    min_score: float = Form(0.0),
    limit: int = Form(25),
):
    t0 = time.time()
    file_bytes = await resume.read()
    if not file_bytes:
        raise HTTPException(400, "Empty file uploaded.")
    if len(file_bytes) > 10 * 1024 * 1024:
        raise HTTPException(400, "File too large (max 10 MB).")

    try:
        resume_text = parse_resume(file_bytes, resume.filename or "resume")
    except (ValueError, RuntimeError) as e:
        raise HTTPException(400, str(e))

    skills = extract_skills(resume_text)
    metadata = extract_metadata(resume_text)
    entities = extract_entities(resume_text)
    career_level = detect_career_level(resume_text, metadata.get("years_experience"))
    tips = resume_quality_tips(resume_text, skills, metadata)
    adjacent = adjacent_skills(skills, max_hops=2, limit=8)

    career_paths = []
    if skills:
        top_skill = skills[0]
        for path in explore_paths(top_skill, max_depth=3, max_paths=3):
            career_paths.append(path)

    all_jobs = fetch_jobs()
    filters = JobFilters(
        location_keyword=location_keyword or None,
        job_type=job_type or None,
        min_score=min_score,
        career_level=career_level,
        limit=int(limit),
    )
    matched = rank_jobs(resume_text, skills, career_level, all_jobs, filters)
    elapsed = time.time() - t0

    return {
        "elapsed_seconds": round(elapsed, 2),
        "resume": {
            "skills": skills,
            "skill_count": len(skills),
            "career_level": career_level,
            "metadata": metadata,
            "entities": entities,
            "tips": tips,
            "preview": resume_text[:900],
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


if _FRONTEND_DIR.exists():
    @app.get("/")
    def root():
        return FileResponse(_FRONTEND_DIR / "index.html")


    @app.get("/admin")
    def admin_page():
        return FileResponse(_FRONTEND_DIR / "admin.html")

    for sub in ("css", "js", "assets"):
        sub_path = _FRONTEND_DIR / sub
        if sub_path.exists():
            app.mount(f"/{sub}", StaticFiles(directory=sub_path), name=sub)
else:
    logger.warning("Frontend directory not found at %s", _FRONTEND_DIR)


if __name__ == "__main__":
    import uvicorn
    uvicorn.run("backend.app:app", host="127.0.0.1", port=8000, reload=False)
