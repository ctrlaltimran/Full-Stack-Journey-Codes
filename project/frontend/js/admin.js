(() => {
  "use strict";
  const $ = (id) => document.getElementById(id);
  const loginCard = $("login-card");
  const adminLoginForm = $("admin-login-form");
  const adminPanel = $("admin-panel");
  const adminLogoutBtn = $("admin-logout-btn");
  const adminJobsList = $("admin-jobs-list");
  const adminJobsCount = $("admin-jobs-count");
  const adminTotalStat = $("admin-total-stat");
  const adminLoginStatus = $("admin-login-status");
  const customJobForm = $("custom-job-form");
  const customJobStatus = $("custom-job-status");
  const toast = $("toast");
  const toastMessage = $("toast-message");

  function escapeHtml(str) {
    return String(str ?? "").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#39;");
  }
  function showToast(msg, ms = 4200) {
    toastMessage.textContent = msg;
    toast.hidden = false;
    clearTimeout(showToast._t);
    showToast._t = setTimeout(() => { toast.hidden = true; }, ms);
  }
  function setAdminLoggedIn(loggedIn) {
    loginCard.hidden = loggedIn;
    adminPanel.hidden = !loggedIn;
    if (adminLoginStatus) adminLoginStatus.textContent = "";
  }
  async function checkAdminStatus() {
    try {
      const res = await fetch("/api/admin/status", { cache: "no-store" });
      const data = await res.json();
      setAdminLoggedIn(Boolean(data.authenticated));
      if (data.authenticated) await loadAdminJobs();
    } catch (_) { setAdminLoggedIn(false); }
  }
  async function loadAdminJobs() {
    adminJobsList.innerHTML = `<div class="empty empty--small">Loading jobs...</div>`;
    const res = await fetch("/api/admin/jobs", { cache: "no-store" });
    if (!res.ok) throw new Error("Login required");
    const data = await res.json();
    adminJobsCount.textContent = `${Number(data.count).toLocaleString()} jobs in backend`;
    adminTotalStat.textContent = Number(data.count).toLocaleString();
    if (customJobStatus) customJobStatus.textContent = `${Number(data.count).toLocaleString()} jobs available`;
    adminJobsList.innerHTML = "";
    data.jobs.slice().reverse().slice(0, 120).forEach(job => {
      const item = document.createElement("article");
      item.className = "admin-job-card";
      item.innerHTML = `
        <div>
          <h4>${escapeHtml(job.title)}</h4>
          <p>${escapeHtml(job.company)} · ${escapeHtml(job.location)} · ${escapeHtml(job.job_type || "")} ${job.salary ? "· " + escapeHtml(job.salary) : ""}</p>
          <div class="admin-job-tags">${(job.tags || []).slice(0, 7).map(t => `<span>${escapeHtml(t)}</span>`).join("")}</div>
        </div>
        <a href="${escapeHtml(job.url)}" target="_blank" rel="noopener">Apply link</a>`;
      adminJobsList.appendChild(item);
    });
  }
  adminLoginForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    adminLoginStatus.textContent = "Checking login...";
    try {
      const res = await fetch("/api/admin/login", {
        method: "POST", headers: { "Content-Type": "application/json" },
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
  adminLogoutBtn.addEventListener("click", async () => {
    await fetch("/api/admin/logout", { method: "POST" });
    setAdminLoggedIn(false);
    showToast("Admin logged out.");
  });
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
      await loadAdminJobs();
      showToast("Job posted successfully.");
    } catch (err) {
      customJobStatus.textContent = "Save failed";
      showToast(err.message || "Could not post job.");
    }
  });
  checkAdminStatus();
})();
