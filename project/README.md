# Synapse — AI Job Matcher

> Drop a resume, get matched to real, current jobs from live remote-job APIs.
> Built as a complete AI lab project — Python backend, vanilla HTML/CSS/JS
> frontend, no external services required.

![Stack](https://img.shields.io/badge/stack-FastAPI%20%2B%20spaCy%20%2B%20scikit--learn-d4ff3a?style=flat-square)
![Frontend](https://img.shields.io/badge/frontend-vanilla%20HTML%2FCSS%2FJS-d4ff3a?style=flat-square)

---

## What it does

1. **You upload a resume** (PDF, DOCX, TXT, or even a phone photo).
2. **Backend parses it** — extracts text, skills, contact info, years of experience, and infers career level.
3. **A skill graph runs BFS** outward from your skills to suggest *adjacent* skills you should learn next.
4. **Live job listings are fetched** from three free public APIs (Remotive, RemoteOK, Arbeitnow) — thousands of real, current postings.
5. **Each job is scored** with a blend of skill overlap, TF-IDF semantic similarity, and career-level alignment.
6. **You get ranked matches** with an explainable score breakdown — match %, skill overlap, semantic match, missing-skills callout, and an Apply button.

---

## Why not LinkedIn?

LinkedIn does **not** offer a free public jobs API. Their developer API is partner-only and doesn't expose general job search. Instead this project uses three completely free, no-auth alternatives:

| Source     | URL                                            | What it gives                   |
| ---------- | ---------------------------------------------- | ------------------------------- |
| Remotive   | `remotive.com/api/remote-jobs`                 | ~1500 active remote jobs        |
| RemoteOK   | `remoteok.com/api`                             | Tech-focused remote jobs        |
| Arbeitnow  | `arbeitnow.com/api/job-board-api`              | EU-focused jobs (mix of remote) |

Combined, you get thousands of real, current postings with no API keys and no signup.

---

## Quick start

### Prerequisites

- **Python 3.10+** ([download](https://www.python.org/downloads/))
- An internet connection (for live job data)
- *(Optional)* **Tesseract OCR** if you want to upload image resumes — [install guide](https://github.com/tesseract-ocr/tesseract). The app works fine without it; you just won't be able to OCR images.

### Run it

**Windows:**
```bash
run.bat
```

**macOS / Linux:**
```bash
chmod +x run.sh
./run.sh
```

The first run takes 3–5 minutes (creates a virtual environment, installs dependencies, downloads the spaCy English model). Subsequent runs are instant.

When you see `Application startup complete`, open your browser to:

```
http://127.0.0.1:8000
```

### Manual setup (if scripts misbehave)

```bash
python -m venv venv
source venv/bin/activate          # Windows: venv\Scripts\activate
pip install -r requirements.txt
python -m spacy download en_core_web_sm
python -m backend.app
```

---

## Architecture

```
┌──────────────────────────────────────────────────────────────┐
│  BROWSER (vanilla HTML/CSS/JS)                               │
│  ─ Drag-drop upload                                          │
│  ─ Animated 5-stage processing screen                        │
│  ─ Results: skills, matches, BFS adjacent skills, tips       │
└────────────────────────────┬─────────────────────────────────┘
                             │ POST /api/analyze (multipart)
                             ▼
┌──────────────────────────────────────────────────────────────┐
│  FastAPI BACKEND                                             │
│                                                              │
│  ┌─────────────┐   ┌────────────┐   ┌────────────────────┐   │
│  │ Resume      │──▶│ Skill      │──▶│ Career-level       │   │
│  │ parser      │   │ extractor  │   │ detection + tips   │   │
│  │ (PDF/DOCX/  │   │ (curated   │   └────────────────────┘   │
│  │  TXT/OCR)   │   │  + spaCy)  │                            │
│  └─────────────┘   └─────┬──────┘                            │
│                          │                                   │
│                          ▼                                   │
│  ┌─────────────────────────────────────────────────────┐     │
│  │ Skill graph (NetworkX-style adjacency)              │     │
│  │   ─ BFS  → adjacent skills (1–2 hops)               │     │
│  │   ─ BFS  → skill-gap analysis                       │     │
│  │   ─ DFS  → career path exploration                  │     │
│  └─────────────────────────────────────────────────────┘     │
│                          │                                   │
│                          ▼                                   │
│  ┌─────────────────────────────────────────────────────┐     │
│  │ Job fetcher (cached)                                │     │
│  │   ─ Remotive · RemoteOK · Arbeitnow                 │     │
│  └────────────────────────┬────────────────────────────┘     │
│                           ▼                                  │
│  ┌─────────────────────────────────────────────────────┐     │
│  │ Matcher  (sklearn TF-IDF + cosine similarity)       │     │
│  │   final = 0.55·skill_overlap                        │     │
│  │         + 0.35·tfidf_similarity                     │     │
│  │         + 0.10·level_bonus                          │     │
│  └─────────────────────────────────────────────────────┘     │
└──────────────────────────────────────────────────────────────┘
```

### Where BFS / DFS earn their place

A common lab-project trap is bolting algorithms on for the rubric. Here they're load-bearing:

- **BFS for adjacent skills.** Multi-source BFS starting from every skill on the user's resume, expanding 1–2 hops over the related-skills graph, gives a ranked list of "natural next moves" — skills they'd benefit from learning to unlock more jobs.
- **BFS for skill-gap.** For each matched job, BFS from the user's known skills measures how far missing skills are. Skills 1 hop away are flagged as "easy wins."
- **DFS for career-path exploration.** Walking deeper paths from the user's strongest skill gives illustrative learning routes (e.g. `JavaScript → React → Next.js`).

---

## Project structure

```
ai-job-matcher/
├── backend/
│   ├── app.py                      # FastAPI app + endpoints
│   ├── parser/
│   │   └── resume_parser.py        # PDF / DOCX / TXT / image OCR
│   ├── nlp/
│   │   ├── skill_extractor.py      # Curated alias matching + spaCy NER
│   │   └── skills_db.py            # 200+ skills + relation graph
│   ├── graph/
│   │   └── skill_graph.py          # BFS / DFS over skill graph
│   ├── matcher/
│   │   └── job_matcher.py          # TF-IDF + cosine + scoring
│   └── jobs/
│       └── job_fetcher.py          # 3 free APIs + cache + dedupe
├── frontend/
│   ├── index.html                  # Single-page app
│   ├── css/styles.css              # Warm-dark editorial design
│   └── js/main.js                  # Drag-drop, stages, rendering
├── requirements.txt
├── run.sh / run.bat                # One-shot launchers
└── README.md
```

---

## Features

### Core
- **Multi-format resume upload** (PDF, DOCX, TXT, PNG/JPG with OCR)
- **Curated skill extraction** — 200+ canonical skills with aliases (catches "JS"/"JavaScript"/"java script")
- **Live job aggregation** from 3 free APIs with 30-min caching
- **TF-IDF + skill-overlap scoring** with a transparent 3-component breakdown
- **BFS skill graph** for adjacent-skill recommendations
- **Career-level detection** (entry / mid / senior / lead)
- **Filters**: location keyword, job type, minimum match %, result limit

### UX details
- **Drag-drop or click to upload**, with file-type and size validation
- **Animated 5-stage thinking screen** that mirrors real backend steps
- **Live job-source attribution** on every result card
- **Skill-gap visualization** — matched skills (filled) vs missing (dashed)
- **Resume quality tips** — flags weak verbs, missing contact info, undated roles
- **Keyboard accessible** — drop zone is focusable and Enter/Space activated
- **Responsive** — works on phones and tablets

### Backend details
- **Graceful degradation** — if a job API is down, others still work; if all fail, falls back to a bundled sample dataset so demos never break
- **Cache layer** — 30-minute TTL to avoid hammering free APIs
- **Deduplication** — same role posted to multiple boards is merged
- **CORS enabled** — useful if you want to host frontend separately

---

## Endpoints

| Method | Path                | Description                              |
| ------ | ------------------- | ---------------------------------------- |
| POST   | `/api/analyze`      | Upload resume + filters → ranked jobs    |
| GET    | `/api/jobs/refresh` | Force-refresh the job cache              |
| GET    | `/api/health`       | Health check                             |
| GET    | `/`                 | Serves the frontend                      |

### Example response (truncated)

```json
{
  "elapsed_seconds": 1.84,
  "resume": {
    "skills": ["Python", "Django", "PostgreSQL", "Docker"],
    "skill_count": 4,
    "career_level": "mid",
    "metadata": { "years_experience": 3, "emails": ["..."], ... },
    "tips": ["Replace passive phrases like 'responsible for' with action verbs."]
  },
  "graph": {
    "adjacent_skills": [
      { "skill": "Flask",      "hops": 1, "bridge_skill": "Python" },
      { "skill": "Kubernetes", "hops": 1, "bridge_skill": "Docker" }
    ]
  },
  "jobs": {
    "total_available": 1843,
    "matched_count": 25,
    "matches": [
      {
        "job": { "title": "Senior Python Engineer", ... },
        "score": 87.4,
        "breakdown": { "skill_overlap": 80.0, "tfidf_similarity": 42.1, "level_bonus": 60.0 },
        "matched_skills": ["Python", "Django", "Docker"],
        "missing_skills": ["AWS", "GraphQL"],
        "coverage_pct": 60
      }
    ]
  }
}
```

---

## Limitations & honest caveats

- **Skill extraction is curated, not learned.** The skill list (`backend/nlp/skills_db.py`) is hand-maintained. This is a deliberate trade-off — it's reliable and explainable, but you'll need to add new skills as they emerge. A pure spaCy NER approach would generalize but miss many tech skills.
- **TF-IDF is shallow.** It doesn't understand "led a team" ≈ "leadership" the way an embedding model would. Replacing TF-IDF with `sentence-transformers` is a one-file change if you want to upgrade.
- **Job sources are remote-heavy.** All three APIs lean toward remote / tech work. For local non-tech jobs you'd need a different data source.
- **OCR requires Tesseract.** Image-resume support depends on Tesseract being installed on your system. The app degrades gracefully if it's not.

---

## Ideas for extensions

- Replace TF-IDF with `sentence-transformers` for semantic match
- Add a "compare two resumes" mode
- Persist matches to SQLite so you can save / track applications
- Add a cover-letter generator using a local LLM
- Visualize the skill graph itself with d3.js
- Multi-language resume support (translate → parse)

---

## License

MIT — do whatever you want with this. It's a lab project; if it's useful for yours, go for it.


## Classroom demo data

This version includes `data/custom_jobs.json` with 100 Karachi demo jobs. All demo jobs use `https://ctrlaltimran.com` as the apply link.

You can add more jobs from the frontend under **Backend demo data**, or edit `data/custom_jobs.json` directly. The app still keeps the original live API matching flow.

## Updated classroom demo features

- External job APIs are removed. The matcher uses only `data/custom_jobs.json`.
- 400 fake computer-science jobs are included for Karachi/class demo use.
- All apply links point to `https://ctrlaltimran.com`.
- Admin panel is available from the header login button.
- Default local credentials: `admin` / `admin123`.
- For public hosting, set environment variables `AJM_ADMIN_USERNAME` and `AJM_ADMIN_PASSWORD`.
- Uploaded resumes are parsed in memory only and are not saved to disk.


## Admin dashboard

Open `http://127.0.0.1:8000/admin` after running the app. Demo login: `admin` / `admin123`. For a public deployment, set `AJM_ADMIN_USERNAME` and `AJM_ADMIN_PASSWORD` as environment variables.
