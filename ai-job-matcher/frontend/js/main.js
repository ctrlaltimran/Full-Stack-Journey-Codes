/* ============================================================
   SYNAPSE — frontend logic
   States: upload → processing → results
   ============================================================ */

(() => {
  "use strict";

  /* -------------------- DOM cache --------------------------- */
  const $ = (id) => document.getElementById(id);

  const screens = {
    upload:     $("state-upload"),
    processing: $("state-processing"),
    results:    $("state-results"),
  };

  const dropZone   = $("drop-zone");
  const fileInput  = $("file-input");
  const fileChip   = $("file-chip");
  const analyzeBtn = $("analyze-btn");
  const backBtn    = $("back-btn");

  const filterLocation  = $("filter-location");
  const filterJobType   = $("filter-job-type");
  const filterMinScore  = $("filter-min-score");
  const filterMinScoreD = $("filter-min-score-display");
  const filterLimit     = $("filter-limit");

  const stages = ["parse", "nlp", "graph", "fetch", "rank"].map(k => $(`stage-${k}`));

  const toast        = $("toast");
  const toastMessage = $("toast-message");

  /* -------------------- State ------------------------------- */
  let selectedFile = null;

  /* -------------------- Helpers ----------------------------- */
  function showScreen(name) {
    Object.values(screens).forEach(s => s.classList.remove("is-active"));
    screens[name].classList.add("is-active");
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  function showToast(msg, ms = 4500) {
    toastMessage.textContent = msg;
    toast.hidden = false;
    clearTimeout(showToast._t);
    showToast._t = setTimeout(() => { toast.hidden = true; }, ms);
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  /* -------------------- File handling ----------------------- */
  function setFile(file) {
    if (!file) return;

    // Sanity checks
    const ALLOWED = ["pdf", "docx", "doc", "txt", "png", "jpg", "jpeg"];
    const ext = file.name.split(".").pop().toLowerCase();
    if (!ALLOWED.includes(ext)) {
      showToast(`Unsupported file type: .${ext}`);
      return;
    }
    if (file.size > 10 * 1024 * 1024) {
      showToast("File is too large (max 10 MB).");
      return;
    }

    selectedFile = file;
    fileChip.querySelector(".filechip__name").textContent = file.name;
    fileChip.hidden = false;
    analyzeBtn.disabled = false;
  }

  function clearFile() {
    selectedFile = null;
    fileInput.value = "";
    fileChip.hidden = true;
    analyzeBtn.disabled = true;
  }

  /* -------------------- Drag-drop binding ------------------ */
  dropZone.addEventListener("click", () => fileInput.click());
  dropZone.addEventListener("keydown", (e) => {
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      fileInput.click();
    }
  });
  fileInput.addEventListener("change", (e) => {
    if (e.target.files[0]) setFile(e.target.files[0]);
  });

  ["dragenter", "dragover"].forEach(evt => {
    dropZone.addEventListener(evt, (e) => {
      e.preventDefault();
      dropZone.classList.add("is-drag");
    });
  });
  ["dragleave", "drop"].forEach(evt => {
    dropZone.addEventListener(evt, (e) => {
      e.preventDefault();
      dropZone.classList.remove("is-drag");
    });
  });
  dropZone.addEventListener("drop", (e) => {
    if (e.dataTransfer.files[0]) setFile(e.dataTransfer.files[0]);
  });

  fileChip.querySelector(".filechip__remove").addEventListener("click", (e) => {
    e.stopPropagation();
    clearFile();
  });

  filterMinScore.addEventListener("input", (e) => {
    filterMinScoreD.textContent = `${e.target.value}%`;
  });

  /* -------------------- Processing animation --------------- */
  function resetStages() {
    stages.forEach(s => s.classList.remove("is-active", "is-done"));
  }

  /**
   * Walk through the visual stages while the API request is in flight.
   * The stages roughly track the actual backend steps -- we time them so
   * the user sees realistic progress without blocking on the network.
   */
  async function animateStages(promise) {
    resetStages();

    const TIMINGS = [600, 700, 600, 1100, 800];   // ms per stage
    let idx = 0;
    let resolved = false;
    let error = null;
    let result = null;

    promise.then(r => { result = r; resolved = true; })
           .catch(e => { error = e; resolved = true; });

    while (idx < stages.length) {
      stages[idx].classList.add("is-active");
      // For the last stage, wait for the request to actually finish
      const wait = (idx === stages.length - 1)
        ? Math.max(TIMINGS[idx], await waitForResolve(resolved => resolved, () => resolved))
        : TIMINGS[idx];
      await sleep(wait);
      stages[idx].classList.remove("is-active");
      stages[idx].classList.add("is-done");
      idx++;
    }

    // If the request is still in flight after all timed stages, hold on the
    // last one until it resolves.
    while (!resolved) {
      stages[stages.length - 1].classList.remove("is-done");
      stages[stages.length - 1].classList.add("is-active");
      await sleep(150);
    }
    stages[stages.length - 1].classList.remove("is-active");
    stages[stages.length - 1].classList.add("is-done");

    if (error) throw error;
    return result;
  }

  function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }
  function waitForResolve(_, check) {
    // Returns roughly how long the last stage should hold (we always
    // return 200ms; the outer loop polls until resolved is true).
    return Promise.resolve(200);
  }

  /* -------------------- Submit / API call ------------------ */
  analyzeBtn.addEventListener("click", async () => {
    if (!selectedFile) return;

    const fd = new FormData();
    fd.append("resume",            selectedFile);
    fd.append("location_keyword",  filterLocation.value.trim());
    fd.append("job_type",          filterJobType.value);
    fd.append("min_score",         filterMinScore.value);
    fd.append("limit",             filterLimit.value);

    showScreen("processing");

    try {
      const apiPromise = fetch("/api/analyze", { method: "POST", body: fd })
        .then(async (res) => {
          if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            throw new Error(data.detail || `Server error (${res.status})`);
          }
          return res.json();
        });

      const data = await animateStages(apiPromise);
      renderResults(data);
      showScreen("results");
    } catch (err) {
      console.error(err);
      showScreen("upload");
      showToast(err.message || "Something went wrong. Try a different file.");
    }
  });

  backBtn.addEventListener("click", () => {
    clearFile();
    showScreen("upload");
  });

  /* -------------------- Results rendering ------------------ */
  function renderResults(data) {
    const r = data.resume;
    const g = data.graph;
    const j = data.jobs;

    // Headline
    const levelLabel = {
      entry:  "Entry-level",
      mid:    "Mid-level",
      senior: "Senior",
      lead:   "Lead / Principal",
    }[r.career_level] || "Mid-level";

    const yearsLine = r.metadata.years_experience
      ? `${r.metadata.years_experience}+ yrs`
      : `level inferred`;

    $("summary-headline").innerHTML =
      `${escapeHtml(levelLabel)} <em>profile</em>`;
    $("summary-meta").textContent =
      `${r.skill_count} skills · ${yearsLine} · ${j.total_available.toLocaleString()} live jobs scanned`;

    $("stat-skills").textContent  = r.skill_count;
    $("stat-matches").textContent = j.matched_count;
    $("stat-elapsed").textContent = `${data.elapsed_seconds}s`;

    // Skills cloud
    const cloud = $("skills-cloud");
    cloud.innerHTML = "";
    if (r.skills.length === 0) {
      cloud.innerHTML = `<div class="empty">No skills detected — try a more detailed resume.</div>`;
    } else {
      r.skills.forEach(s => {
        const el = document.createElement("span");
        el.className = "chip chip--accent";
        el.textContent = s;
        cloud.appendChild(el);
      });
    }
    $("skills-caption").textContent = `${r.skills.length} canonical skills detected`;

    // Adjacent skills (BFS output)
    const adj = $("adjacent-list");
    adj.innerHTML = "";
    if (g.adjacent_skills.length === 0) {
      adj.innerHTML = `<div class="empty">Add a few more skills to your resume to surface adjacent paths.</div>`;
    } else {
      g.adjacent_skills.forEach(a => {
        adj.insertAdjacentHTML("beforeend", `
          <div class="adj">
            <span class="adj__hops">${a.hops}h</span>
            <span class="adj__skill">${escapeHtml(a.skill)}</span>
            <span class="adj__bridge">via <em>${escapeHtml(a.bridge_skill)}</em></span>
          </div>
        `);
      });
    }

    // Tips
    const tipsPanel = $("tips-panel");
    const tipsList = $("tips-list");
    tipsList.innerHTML = "";
    if (r.tips && r.tips.length) {
      tipsPanel.hidden = false;
      r.tips.forEach(t => {
        const li = document.createElement("li");
        li.textContent = t;
        tipsList.appendChild(li);
      });
    } else {
      tipsPanel.hidden = true;
    }

    // Matches
    const matchesEl = $("matches-list");
    matchesEl.innerHTML = "";
    $("matches-caption").textContent =
      j.matched_count > 0
        ? `Sorted by relevance · top ${Math.min(j.matched_count, j.matches.length)} shown`
        : `Try loosening filters or adding more skills`;

    if (j.matches.length === 0) {
      matchesEl.innerHTML = `<div class="empty">No matching jobs found. Try lowering the minimum match % or removing filters.</div>`;
      return;
    }

    j.matches.forEach((m, idx) => {
      matchesEl.appendChild(renderMatch(m, idx));
    });
  }

  function renderMatch(m, idx) {
    const job = m.job;

    const matchedChips = m.matched_skills
      .slice(0, 8)
      .map(s => `<span class="chip chip--accent">${escapeHtml(s)}</span>`)
      .join("");

    const missingChips = m.missing_skills
      .slice(0, 5)
      .map(s => `<span class="chip chip--missing">${escapeHtml(s)}</span>`)
      .join("");

    const allChips = matchedChips + missingChips;

    const company = escapeHtml(job.company || "Unknown");
    const location = escapeHtml(job.location || "Remote");
    const jobType = job.job_type ? `<span class="dot">·</span>${escapeHtml(job.job_type)}` : "";
    const salary = job.salary ? `<span class="dot">·</span>${escapeHtml(job.salary)}` : "";

    const div = document.createElement("article");
    div.className = "match";
    div.style.animation = `fadeUp 0.4s ease-out ${idx * 0.04}s both`;

    div.innerHTML = `
      <div class="match__body">
        <div class="match__head">
          <h4 class="match__title">${escapeHtml(job.title || "Untitled role")}</h4>
        </div>
        <div class="match__company">
          <strong>${company}</strong>
          <span class="dot">·</span>${location}
          ${jobType}${salary}
        </div>

        <div class="match__skills">${allChips || '<span class="chip chip--missing">No skill data available</span>'}</div>

        <div class="match__breakdown">
          <span>Skill overlap <strong>${m.breakdown.skill_overlap}%</strong></span>
          <span>Semantic <strong>${m.breakdown.tfidf_similarity}%</strong></span>
          <span>Coverage <strong>${m.coverage_pct}%</strong></span>
          <span class="match__source">${escapeHtml(job.source || "")}</span>
        </div>
      </div>

      <div class="match__cta">
        <div>
          <div class="match__score">${Math.round(m.score)}<span style="font-size:0.5em;color:var(--text-mute);">%</span></div>
          <div class="match__score-lbl">match</div>
        </div>
        ${job.url
          ? `<a class="match__apply" href="${escapeHtml(job.url)}" target="_blank" rel="noopener">Apply →</a>`
          : ""}
      </div>
    `;
    return div;
  }

})();
