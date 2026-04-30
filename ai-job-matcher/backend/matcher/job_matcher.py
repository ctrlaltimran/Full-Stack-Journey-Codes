"""
Job matcher — ranks jobs against a parsed resume.

We compute a blended score:

    final = 0.55 * skill_overlap + 0.35 * tfidf_similarity + 0.10 * level_bonus

  * skill_overlap: fraction of required skills the candidate already has.
    This is the most important signal because it's directly explainable.

  * tfidf_similarity: cosine similarity of the resume text vs the job
    description (TF-IDF vectors). Catches semantic context that pure skill
    matching misses (e.g. "led a team of 5" matching against "leadership").

  * level_bonus: small adjustment for matching seniority level.

Each component is in [0, 1] and the final score is a percentage in [0, 100].
"""

from __future__ import annotations

import logging
import re
from dataclasses import dataclass, field
from typing import Iterable

from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

from backend.nlp.skill_extractor import extract_skills
from backend.graph.skill_graph import skill_gap

logger = logging.getLogger(__name__)


# ---------------------------------------------------------------------------
# Result types
# ---------------------------------------------------------------------------
@dataclass
class MatchResult:
    """A single ranked job with score breakdown."""
    job: dict
    score: float                     # 0–100 final score
    skill_overlap: float             # 0–1
    tfidf_similarity: float          # 0–1
    level_bonus: float               # 0–1
    matched_skills: list[str] = field(default_factory=list)
    missing_skills: list[str] = field(default_factory=list)
    job_skills: list[str] = field(default_factory=list)
    coverage_pct: int = 0
    easy_wins: list[str] = field(default_factory=list)

    def to_dict(self) -> dict:
        return {
            "job": self.job,
            "score": round(self.score, 1),
            "breakdown": {
                "skill_overlap": round(self.skill_overlap * 100, 1),
                "tfidf_similarity": round(self.tfidf_similarity * 100, 1),
                "level_bonus": round(self.level_bonus * 100, 1),
            },
            "matched_skills": self.matched_skills,
            "missing_skills": self.missing_skills,
            "job_skills": self.job_skills,
            "coverage_pct": self.coverage_pct,
            "easy_wins": self.easy_wins,
        }


# ---------------------------------------------------------------------------
# Filter spec (passed in from the API layer)
# ---------------------------------------------------------------------------
@dataclass
class JobFilters:
    location_keyword: str | None = None
    job_type: str | None = None         # e.g. "full-time", "contract"
    min_score: float = 0.0              # 0–100
    career_level: str | None = None     # entry / mid / senior / lead
    limit: int = 25


# ---------------------------------------------------------------------------
# Main matching function
# ---------------------------------------------------------------------------
def rank_jobs(resume_text: str,
              resume_skills: list[str],
              career_level: str,
              jobs: list[dict],
              filters: JobFilters | None = None) -> list[dict]:
    """
    Rank `jobs` by relevance to the resume. Returns serialized MatchResult
    dicts sorted by score (descending).
    """
    filters = filters or JobFilters()

    # Pre-filter on hard criteria (location, job type) before scoring
    candidates = list(_apply_hard_filters(jobs, filters))
    if not candidates:
        return []

    # Build TF-IDF on the candidate set + resume in one corpus
    documents = [resume_text] + [_job_text(j) for j in candidates]
    try:
        vectorizer = TfidfVectorizer(
            stop_words="english",
            max_features=5000,
            ngram_range=(1, 2),
            min_df=1,
        )
        tfidf_matrix = vectorizer.fit_transform(documents)
        resume_vec = tfidf_matrix[0]
        job_vecs = tfidf_matrix[1:]
        similarities = cosine_similarity(resume_vec, job_vecs).flatten()
    except ValueError as e:
        # Empty vocabulary etc. — fall back to zeros
        logger.warning("TF-IDF failed: %s. Falling back to skill-only match.", e)
        similarities = [0.0] * len(candidates)

    resume_skill_set = set(resume_skills)
    results: list[MatchResult] = []

    for job, sim in zip(candidates, similarities):
        # Extract skills from the job description and tags
        job_skills = _extract_job_skills(job)

        # Skill overlap
        if job_skills:
            matched = sorted(set(job_skills) & resume_skill_set)
            missing = sorted(set(job_skills) - resume_skill_set)
            overlap_score = len(matched) / len(job_skills)
        else:
            matched, missing = [], []
            overlap_score = 0.0

        # Skill-gap analysis (uses BFS over the skill graph)
        gap = skill_gap(resume_skills, job_skills)

        # Career level bonus
        level_bonus = _level_match_bonus(job, career_level)

        # Blended final score
        final = (0.55 * overlap_score
                 + 0.35 * float(sim)
                 + 0.10 * level_bonus) * 100

        if final < filters.min_score:
            continue

        results.append(MatchResult(
            job=job,
            score=final,
            skill_overlap=overlap_score,
            tfidf_similarity=float(sim),
            level_bonus=level_bonus,
            matched_skills=matched,
            missing_skills=missing,
            job_skills=job_skills,
            coverage_pct=gap["coverage_pct"],
            easy_wins=gap["easy_wins"],
        ))

    # Sort by score descending
    results.sort(key=lambda r: r.score, reverse=True)
    return [r.to_dict() for r in results[:filters.limit]]


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
def _apply_hard_filters(jobs: Iterable[dict],
                         filters: JobFilters) -> Iterable[dict]:
    """Yield jobs that pass the location/job-type filters."""
    loc_kw = (filters.location_keyword or "").strip().lower()
    job_type = (filters.job_type or "").strip().lower()

    for job in jobs:
        # Skip empty descriptions
        if not job.get("description") and not job.get("title"):
            continue
        if loc_kw:
            location = (job.get("location") or "").lower()
            # Treat "remote" specially -- include if user typed remote OR
            # if user typed a country and the job is fully remote
            if loc_kw not in location and "remote" not in location:
                continue
        if job_type:
            jt = (job.get("job_type") or "").lower()
            if job_type not in jt:
                continue
        yield job


def _job_text(job: dict) -> str:
    """Build the text blob we'll vectorize for TF-IDF."""
    parts = [
        job.get("title", ""),
        job.get("description", ""),
        " ".join(job.get("tags", [])),
    ]
    return " ".join(p for p in parts if p)


def _extract_job_skills(job: dict) -> list[str]:
    """
    Extract canonical skills from a job posting using the same extractor we
    use on resumes. We also include any tags that map to canonical skills.
    """
    text = _job_text(job)
    skills = set(extract_skills(text))

    # Tags from the API are often clean keywords -- match them too
    from backend.nlp.skills_db import all_aliases
    aliases = all_aliases()
    for tag in job.get("tags", []):
        canonical = aliases.get(tag.lower().strip())
        if canonical:
            skills.add(canonical)

    return sorted(skills)


def _level_match_bonus(job: dict, candidate_level: str) -> float:
    """
    Small bonus for jobs matching the candidate's career level. Returns
    a value in [0, 1].
    """
    if not candidate_level:
        return 0.5

    title = (job.get("title") or "").lower()
    desc = (job.get("description") or "")[:500].lower()
    text = f"{title} {desc}"

    senior_cues = ["senior", "sr.", "lead", "principal", "staff"]
    junior_cues = ["junior", "jr.", "entry", "intern", "graduate", "trainee"]
    mid_cues = ["mid-level", "mid level", "intermediate"]

    job_level = "mid"  # default
    if any(c in text for c in senior_cues):
        job_level = "senior"
    elif any(c in text for c in junior_cues):
        job_level = "entry"
    elif any(c in text for c in mid_cues):
        job_level = "mid"

    # Distance between levels
    order = {"entry": 0, "mid": 1, "senior": 2, "lead": 3}
    distance = abs(order.get(candidate_level, 1) - order.get(job_level, 1))
    return max(0.0, 1.0 - 0.4 * distance)
