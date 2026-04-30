/* ============================================================
   SYNAPSE — frontend logic
   States: upload → processing → results + admin panel
   ============================================================ */

(() => {
  "use strict";

  const $ = (id) => document.getElementById(id);

  const screens = { upload: $("state-upload"), processing: $("state-processing"), results: $("state-results") };
  const dropZone = $("drop-zone");
  const fileInput = $("file-input");
  const fileChip = $("file-chip");
  const analyzeBtn = $("analyze-btn");
  const backBtn = $("back-btn");
  const filterLocation = $("filter-location");
  const filterJobType = $("filter-job-type");
  const filterMinScore = $("filter-min-score");
  const filterMinScoreD = $("filter-min-score-display");
  const filterLimit = $("filter-limit");
  const stages = ["parse", "nlp", "graph", "fetch", "rank"].map(k => $(`stage-${k}`));
  const toast = $("toast");
  const toastMessage = $("toast-message");

  const adminOpenBtn = $("admin-open-btn");
  const adminModal = $("admin-modal");
  const adminLoginForm = $("admin-login-form");
  const adminPanel = $("admin-panel");
  const adminLogoutBtn = $("admin-logout-btn");
  const adminJobsList = $("admin-jobs-list");
  const adminJobsCount = $("admin-jobs-count");
  const adminLoginStatus = $("admin-login-status");
  const customJobForm = $("custom-job-form");
  const customJobStatus = $("custom-job-status");

  let selectedFile = null;

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

  function setFile(file) {
    if (!file) return;
    const ALLOWED = ["pdf", "docx", "doc", "txt", "png", "jpg", "jpeg"];
    const ext = file.name.split(".").pop().toLowerCase();
    if (!ALLOWED.includes(ext)) return showToast(`Unsupported file type: .${ext}`);
    if (file.size > 10 * 1024 * 1024) return showToast("File is too large (max 10 MB).");
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

  dropZone.addEventListener("click", () => fileInput.click());
  dropZone.addEventListener("keydown", (e) => {
    if (e.key === "Enter" || e.key === " ") { e.preventDefault(); fileInput.click(); }
  });
  fileInput.addEventListener("change", (e) => { if (e.target.files[0]) setFile(e.target.files[0]); });
  ["dragenter", "dragover"].forEach(evt => dropZone.addEventListener(evt, (e) => { e.preventDefault(); dropZone.classList.add("is-drag"); }));
  ["dragleave", "drop"].forEach(evt => dropZone.addEventListener(evt, (e) => { e.preventDefault(); dropZone.classList.remove("is-drag"); }));
  dropZone.addEventListener("drop", (e) => { if (e.dataTransfer.files[0]) setFile(e.dataTransfer.files[0]); });
  fileChip.querySelector(".filechip__remove").addEventListener("click", (e) => { e.stopPropagation(); clearFile(); });
  filterMinScore.addEventListener("input", (e) => { filterMinScoreD.textContent = `${e.target.value}%`; });

  function openAdminModal() {
    adminModal.hidden = false;
    checkAdminStatus();
  }
  function closeAdminModal() { adminModal.hidden = true; }

  if (adminOpenBtn && adminModal) {
    adminOpenBtn.addEventListener("click", openAdminModal);
    adminModal.querySelectorAll("[data-admin-close]").forEach(el => el.addEventListener("click", closeAdminModal));
  }

  async function checkAdminStatus() {
    try {
      const res = await fetch("/api/admin/status");
      const data = await res.json();
      setAdminLoggedIn(Boolean(data.authenticated));
      if (data.authenticated) await loadAdminJobs();
    } catch (_) { setAdminLoggedIn(false); }
  }

  function setAdminLoggedIn(loggedIn) {
    if (!adminLoginForm || !adminPanel) return;
    adminLoginForm.hidden = loggedIn;
    adminPanel.hidden = !loggedIn;
    adminLogoutBtn.hidden = !loggedIn;
    adminOpenBtn.textContent = loggedIn ? "Admin Panel" : "Admin Login";
    if (adminLoginStatus) adminLoginStatus.textContent = "";
  }

  if (adminLoginForm) {
    adminLoginForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      adminLoginStatus.textContent = "Checking login...";
      try {
        const res = await fetch("/api/admin/login", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ username: $("admin-username").value.trim(), password: $("admin-password").value })
        });
        if (!res.ok) {
          const data = await res.json().catch(() => ({}));
          throw new Error(data.detail || "Login failed");
        }
        setAdminLoggedIn(true);
        await loadAdminJobs();
        showToast("Admin logged in.");
      } catch (err) { adminLoginStatus.textContent = err.message || "Login failed"; }
    });
  }

  if (adminLogoutBtn) {
    adminLogoutBtn.addEventListener("click", async () => {
      await fetch("/api/admin/logout", { method: "POST" });
      setAdminLoggedIn(false);
      showToast("Admin logged out.");
    });
  }

  async function loadAdminJobs() {
    if (!adminJobsList) return;
    adminJobsList.innerHTML = `<div class="empty empty--small">Loading jobs...</div>`;
    try {
      const res = await fetch("/api/admin/jobs");
      if (!res.ok) throw new Error("Login required");
      const data = await res.json();
      adminJobsCount.textContent = `${data.count.toLocaleString()} jobs in backend`;
      customJobStatus.textContent = `${data.count.toLocaleString()} jobs available`;
      adminJobsList.innerHTML = "";
      data.jobs.slice().reverse().slice(0, 80).forEach(job => {
        const item = document.createElement("article");
        item.className = "admin-job-card";
        item.innerHTML = `
          <div>
            <h4>${escapeHtml(job.title)}</h4>
            <p>${escapeHtml(job.company)} · ${escapeHtml(job.location)} · ${escapeHtml(job.job_type || "")}</p>
            <div class="admin-job-tags">${(job.tags || []).slice(0, 6).map(t => `<span>${escapeHtml(t)}</span>`).join("")}</div>
          </div>
          <a href="${escapeHtml(job.url)}" target="_blank" rel="noopener">Apply link</a>
        `;
        adminJobsList.appendChild(item);
      });
    } catch (err) {
      adminJobsList.innerHTML = `<div class="empty empty--small">${escapeHtml(err.message || "Could not load jobs")}</div>`;
    }
  }

  if (customJobForm) {
    customJobForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const title = $("custom-title").value.trim();
      if (!title) return showToast("Add a job title first.");
      const payload = {
        title,
        company: $("custom-company").value.trim() || "CtrlAltImran Jobs",
        location: $("custom-location").value.trim() || "Karachi, Pakistan",
        job_type: $("custom-job-type").value,
        salary: $("custom-salary").value.trim(),
        tags: $("custom-tags").value.split(",").map(s => s.trim()).filter(Boolean),
        description: $("custom-description").value.trim() || `${title} software job listing in Karachi, Pakistan.`,
        url: "https://ctrlaltimran.com"
      };
      customJobStatus.textContent = "Posting job...";
      try {
        const res = await fetch("/api/admin/jobs", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) });
        if (!res.ok) {
          const data = await res.json().catch(() => ({}));
          throw new Error(data.detail || "Could not post job");
        }
        customJobForm.reset();
        $("custom-location").value = "Karachi, Pakistan";
        showToast("Job posted to backend data.");
        await loadAdminJobs();
      } catch (err) {
        customJobStatus.textContent = "Save failed";
        showToast(err.message || "Could not post job.");
      }
    });
  }

  checkAdminStatus();

  function resetStages() { stages.forEach(s => s.classList.remove("is-active", "is-done")); }
  async function animateStages(promise) {
    resetStages();
    const timings = [600, 700, 600, 900, 800];
    let resolved = false, error = null, result = null;
    promise.then(r => { result = r; resolved = true; }).catch(e => { error = e; resolved = true; });
    for (let i = 0; i < stages.length; i++) {
      stages[i].classList.add("is-active");
      await sleep(timings[i]);
      stages[i].classList.remove("is-active");
      stages[i].classList.add("is-done");
    }
    while (!resolved) { stages[stages.length - 1].classList.add("is-active"); await sleep(150); }
    stages[stages.length - 1].classList.remove("is-active");
    stages[stages.length - 1].classList.add("is-done");
    if (error) throw error;
    return result;
  }
  function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

  analyzeBtn.addEventListener("click", async () => {
    if (!selectedFile) return;
    const fd = new FormData();
    fd.append("resume", selectedFile);
    fd.append("location_keyword", filterLocation.value.trim());
    fd.append("job_type", filterJobType.value);
    fd.append("min_score", filterMinScore.value);
    fd.append("limit", filterLimit.value);
    showScreen("processing");
    try {
      const apiPromise = fetch("/api/analyze", { method: "POST", body: fd }).then(async (res) => {
        if (!res.ok) { const data = await res.json().catch(() => ({})); throw new Error(data.detail || `Server error (${res.status})`); }
        return res.json();
      });
      const data = await animateStages(apiPromise);
      renderResults(data);
      showScreen("results");
    } catch (err) { console.error(err); showScreen("upload"); showToast(err.message || "Something went wrong. Try a different file."); }
  });

  backBtn.addEventListener("click", () => { clearFile(); showScreen("upload"); });

  function renderResults(data) {
    const r = data.resume, g = data.graph, j = data.jobs;
    const levelLabel = { entry: "Entry-level", mid: "Mid-level", senior: "Senior", lead: "Lead / Principal" }[r.career_level] || "Mid-level";
    const yearsLine = r.metadata.years_experience ? `${r.metadata.years_experience}+ yrs` : `level inferred`;
    $("summary-headline").innerHTML = `${escapeHtml(levelLabel)} <em>profile</em>`;
    $("summary-meta").textContent = `${r.skill_count} skills · ${yearsLine} · ${j.total_available.toLocaleString()} project jobs scanned`;
    $("stat-skills").textContent = r.skill_count;
    $("stat-matches").textContent = j.matched_count;
    $("stat-elapsed").textContent = `${data.elapsed_seconds}s`;

    const previewEl = $("resume-preview");
    if (previewEl) {
      const preview = summarizePreview(r.preview || "");
      previewEl.innerHTML = preview.length ? preview.map(line => `<p>${escapeHtml(line)}</p>`).join("") : `<p>No readable preview was extracted. Try uploading a text-based PDF or DOCX.</p>`;
    }

    const cloud = $("skills-cloud");
    cloud.innerHTML = "";
    if (r.skills.length === 0) cloud.innerHTML = `<div class="empty">No skills detected — try a more detailed resume.</div>`;
    else r.skills.forEach(s => { const el = document.createElement("span"); el.className = "chip chip--accent"; el.textContent = s; cloud.appendChild(el); });
    $("skills-caption").textContent = `${r.skills.length} canonical skills detected`;

    const adj = $("adjacent-list");
    adj.innerHTML = "";
    if (g.adjacent_skills.length === 0) adj.innerHTML = `<div class="empty">Add a few more skills to your resume to surface adjacent paths.</div>`;
    else g.adjacent_skills.forEach(a => adj.insertAdjacentHTML("beforeend", `<div class="adj"><span class="adj__hops">${a.hops}h</span><span class="adj__skill">${escapeHtml(a.skill)}</span><span class="adj__bridge">via <em>${escapeHtml(a.bridge_skill)}</em></span></div>`));

    const tipsPanel = $("tips-panel");
    const tipsList = $("tips-list");
    tipsList.innerHTML = "";
    if (r.tips && r.tips.length) { tipsPanel.hidden = false; r.tips.forEach(t => { const li = document.createElement("li"); li.textContent = t; tipsList.appendChild(li); }); }
    else tipsPanel.hidden = true;

    renderMap(j.matches || []);

    const matchesEl = $("matches-list");
    matchesEl.innerHTML = "";
    $("matches-caption").textContent = j.matched_count > 0 ? `Sorted by relevance · top ${Math.min(j.matched_count, j.matches.length)} shown` : `Try loosening filters or adding more skills`;
    if (j.matches.length === 0) { matchesEl.innerHTML = `<div class="empty">No matching jobs found. Try lowering the minimum match % or removing filters.</div>`; return; }
    j.matches.forEach((m, idx) => matchesEl.appendChild(renderMatch(m, idx)));
  }

  function summarizePreview(text) {
    return String(text || "").replace(/\s+/g, " ").split(/(?<=[.!?])\s+/).filter(Boolean).slice(0, 4).map(s => s.length > 220 ? s.slice(0, 220) + "..." : s);
  }

  function renderMatch(m, idx) {
    const job = m.job;
    const matchedChips = m.matched_skills.slice(0, 8).map(s => `<span class="chip chip--accent">${escapeHtml(s)}</span>`).join("");
    const missingChips = m.missing_skills.slice(0, 5).map(s => `<span class="chip chip--missing">${escapeHtml(s)}</span>`).join("");
    const company = escapeHtml(job.company || "Unknown");
    const location = escapeHtml(job.location || "Karachi");
    const jobType = job.job_type ? `<span class="dot">·</span>${escapeHtml(job.job_type)}` : "";
    const salary = job.salary ? `<span class="dot">·</span>${escapeHtml(job.salary)}` : "";
    const desc = (job.description || "").replace(/\s+/g, " ");
    const div = document.createElement("article");
    div.className = "match";
    div.style.animation = `fadeUp 0.4s ease-out ${idx * 0.04}s both`;
    div.innerHTML = `
      <div class="match__body">
        <div class="match__head"><h4 class="match__title">${escapeHtml(job.title || "Untitled role")}</h4></div>
        <div class="match__company"><strong>${company}</strong><span class="dot">·</span>${location}${jobType}${salary}</div>
        <p class="match__desc">${escapeHtml(desc.slice(0, 220))}${desc.length > 220 ? "..." : ""}</p>
        <div class="match__skills">${matchedChips + missingChips || '<span class="chip chip--missing">No skill data available</span>'}</div>
        <div class="match__breakdown"><span>Skill overlap <strong>${m.breakdown.skill_overlap}%</strong></span><span>Semantic <strong>${m.breakdown.tfidf_similarity}%</strong></span><span>Coverage <strong>${m.coverage_pct}%</strong></span><span class="match__source">${escapeHtml(job.source || "")}</span></div>
      </div>
      <div class="match__cta"><div><div class="match__score">${Math.round(m.score)}<span style="font-size:0.5em;color:var(--text-mute);">%</span></div><div class="match__score-lbl">match</div></div><a class="match__apply" href="${escapeHtml(job.url || "https://ctrlaltimran.com")}" target="_blank" rel="noopener">Apply →</a></div>`;
    return div;
  }

  function renderMap(matches) {
    const pins = $("map-pins");
    const caption = $("map-caption");
    if (!pins) return;
    pins.innerHTML = "";
    const mapMatches = (matches || []).slice(0, 18);
    caption.textContent = mapMatches.length ? `${mapMatches.length} best matched Karachi jobs pinned` : "No matched jobs to pin yet";
    if (!mapMatches.length) { pins.innerHTML = `<div class="map-empty">Upload a resume to see matched jobs on the Karachi map.</div>`; return; }
    const bounds = { minLat: 24.77, maxLat: 24.98, minLng: 66.96, maxLng: 67.22 };
    const fallback = [[31,66],[44,54],[58,62],[38,38],[67,44],[25,48],[73,70],[49,29],[54,77],[33,74],[62,34],[42,68]];
    mapMatches.forEach((m, idx) => {
      const job = m.job || {};
      let x, y;
      if (typeof job.lat === "number" && typeof job.lng === "number") {
        x = ((job.lng - bounds.minLng) / (bounds.maxLng - bounds.minLng)) * 100;
        y = (1 - ((job.lat - bounds.minLat) / (bounds.maxLat - bounds.minLat))) * 100;
        x = Math.max(10, Math.min(88, x)); y = Math.max(12, Math.min(86, y));
      } else { [x, y] = fallback[idx % fallback.length]; }
      const pin = document.createElement("a");
      pin.className = "fake-pin";
      pin.href = job.url || "https://ctrlaltimran.com";
      pin.target = "_blank";
      pin.rel = "noopener";
      pin.style.left = `${x}%`;
      pin.style.top = `${y}%`;
      pin.innerHTML = `<span><b>${idx + 1}</b></span><em><strong>${escapeHtml(job.title || "Job")}</strong><small>${escapeHtml(job.location || "Karachi")}</small></em>`;
      pins.appendChild(pin);
    });
  }
})();
