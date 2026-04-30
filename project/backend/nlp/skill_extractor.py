"""
Skill extractor.

Strategy:
  1. PRIMARY: word-boundary regex match against the curated alias dictionary.
     This is reliable, fast, and explainable (we know exactly why a skill was
     matched).
  2. SECONDARY (optional): spaCy NER to pull out extra entities (locations,
     organizations) that are useful for context but aren't in the skill list.

We avoid relying purely on spaCy for skill extraction because small models
(en_core_web_sm) are unreliable at recognizing tech-specific terms like
"Kubernetes" or "FastAPI" as skills.
"""

from __future__ import annotations

import logging
import re
from functools import lru_cache

from .skills_db import SKILLS, all_aliases

logger = logging.getLogger(__name__)


# ---------------------------------------------------------------------------
# Pre-compile alias regexes once at import time.
# ---------------------------------------------------------------------------
def _compile_skill_patterns() -> list[tuple[re.Pattern, str]]:
    """
    Build (compiled_pattern, canonical_skill) pairs.

    We use word-boundary matching but escape special chars carefully. Skills
    like "C++" and ".NET" need special handling since `\b` won't match around
    `+` or `.`.
    """
    patterns = []
    aliases = all_aliases()
    for alias, canonical in aliases.items():
        alias_clean = alias.strip()
        if not alias_clean:
            continue

        # Escape special regex chars
        escaped = re.escape(alias_clean)

        # Use word boundaries unless the alias starts/ends with a non-word char
        # (e.g. ".net", "c++"). For those we use a lookaround that accepts
        # whitespace or string boundaries.
        starts_word = alias_clean[0].isalnum()
        ends_word = alias_clean[-1].isalnum()
        prefix = r"\b" if starts_word else r"(?<![A-Za-z0-9])"
        suffix = r"\b" if ends_word else r"(?![A-Za-z0-9])"

        pattern = re.compile(prefix + escaped + suffix, re.IGNORECASE)
        patterns.append((pattern, canonical))
    return patterns


_PATTERNS = _compile_skill_patterns()


# ---------------------------------------------------------------------------
# spaCy is optional; load lazily and gracefully degrade if unavailable.
# ---------------------------------------------------------------------------
@lru_cache(maxsize=1)
def _get_spacy():
    """Return a loaded spaCy nlp object, or None if unavailable."""
    try:
        import spacy
        try:
            return spacy.load("en_core_web_sm")
        except OSError:
            logger.warning(
                "spaCy model 'en_core_web_sm' not installed. "
                "Run: python -m spacy download en_core_web_sm "
                "(NER features will be skipped)."
            )
            return None
    except ImportError:
        logger.warning("spaCy not installed; NER features skipped.")
        return None


# ---------------------------------------------------------------------------
# Public API
# ---------------------------------------------------------------------------
def extract_skills(text: str) -> list[str]:
    """
    Find all canonical skills mentioned in the text.

    Returns a sorted, de-duplicated list of canonical skill names.
    """
    if not text:
        return []

    found: set[str] = set()
    # Pad with spaces to make boundary matching at edges work
    padded = f" {text} "
    for pattern, canonical in _PATTERNS:
        if pattern.search(padded):
            found.add(canonical)

    return sorted(found)


def extract_entities(text: str) -> dict[str, list[str]]:
    """
    Pull named entities via spaCy. Useful for showing the user what we
    detected about their location, past employers, etc.

    Returns dict with keys: organizations, locations, people.
    Returns empty lists if spaCy isn't available.
    """
    nlp = _get_spacy()
    if nlp is None:
        return {"organizations": [], "locations": [], "people": []}

    # Cap text length to keep parsing fast
    doc = nlp(text[:50_000])
    orgs, locs, people = set(), set(), set()
    for ent in doc.ents:
        if ent.label_ == "ORG":
            orgs.add(ent.text.strip())
        elif ent.label_ in ("GPE", "LOC"):
            locs.add(ent.text.strip())
        elif ent.label_ == "PERSON":
            people.add(ent.text.strip())

    return {
        "organizations": sorted(orgs)[:20],
        "locations": sorted(locs)[:10],
        "people": sorted(people)[:5],
    }


def detect_career_level(text: str, years_exp: int | None) -> str:
    """
    Best-effort guess at career level from years of experience and title cues.

    Returns one of: "entry", "mid", "senior", "lead".
    """
    text_lower = text.lower()

    # Explicit title cues override the years heuristic.
    if any(t in text_lower for t in
           ["principal engineer", "staff engineer", "tech lead",
            "engineering manager", "director of"]):
        return "lead"
    if any(t in text_lower for t in
           ["senior ", "sr.", "lead developer", "lead engineer"]):
        return "senior"
    if any(t in text_lower for t in
           ["intern", "internship", "trainee", "fresher", "graduate"]):
        return "entry"

    if years_exp is not None:
        if years_exp <= 1:
            return "entry"
        if years_exp <= 4:
            return "mid"
        if years_exp <= 8:
            return "senior"
        return "lead"

    return "mid"  # safe default


def resume_quality_tips(text: str, skills: list[str], metadata: dict) -> list[str]:
    """
    Return a list of human-readable tips for improving the resume.
    Empty list means the resume looks good on the basics we check.
    """
    tips = []
    if not metadata.get("emails"):
        tips.append("No email address detected — make sure your contact info is plain text.")
    if not metadata.get("phones"):
        tips.append("No phone number detected — add a clearly formatted phone number.")
    if len(skills) < 5:
        tips.append("Few technical skills detected — consider adding a dedicated 'Skills' section.")
    if len(text) < 1500:
        tips.append("Resume looks short — most strong resumes are 400+ words.")
    if len(text) > 8000:
        tips.append("Resume is quite long — consider trimming to the most relevant 1–2 pages.")
    if not re.search(r"\b(20\d{2}|19\d{2})\b", text):
        tips.append("No years detected — make sure each role lists clear start/end dates.")
    weak_verbs = ["responsible for", "duties included", "worked on"]
    if any(w in text.lower() for w in weak_verbs):
        tips.append("Replace passive phrases like 'responsible for' with action verbs (built, shipped, led).")
    return tips
