jQuery(function ($) {
  const $start = $("#rill-imgopt-start");
  const $reset = $("#rill-imgopt-reset");
  const $progress = $("#rill-imgopt-progress");
  const $fill = $("#rill-imgopt-progress-fill");
  const $text = $("#rill-imgopt-progress-text");

  let running = false;

  function setProgress(pct, msg) {
    $progress.show();
    $fill.css("width", Math.max(0, Math.min(100, pct)) + "%");
    $text.text(msg);
  }

  function formatBytes(bytes) {
    bytes = parseInt(bytes || 0, 10);
    const units = ["B", "KB", "MB", "GB", "TB"];
    let i = 0;
    let v = bytes;
    while (v >= 1024 && i < units.length - 1) {
      v = v / 1024;
      i++;
    }
    if (i === 0) return v + " " + units[i];
    return v.toFixed(2) + " " + units[i];
  }

  function bulkStep(offset) {
    if (!running) return;

    $.post(RillImgOpt.ajaxurl, {
      action: "rill_imgopt_bulk_step",
      nonce: RillImgOpt.nonce,
      offset: offset || 0
    })
      .done(function (res) {
        if (!res || !res.success) {
          running = false;
          setProgress(0, (res && res.data && res.data.message) ? res.data.message : "Error during optimization.");
          return;
        }

        const d = res.data;
        const total = d.total || 0;
        const off = d.offset || 0;
        const pct = total > 0 ? Math.round((off / total) * 100) : 0;

        const saved = d.saved_bytes || 0;
        setProgress(pct, "Processed " + off + " of " + total + " images. Saved " + formatBytes(saved) + " in this step.");

        if (d.done) {
          running = false;
          setProgress(100, "Done. Refresh this page to see updated totals and recent items.");
        } else {
          bulkStep(off);
        }
      })
      .fail(function () {
        running = false;
        setProgress(0, "Request failed. Try again with a smaller batch size.");
      });
  }

  $start.on("click", function () {
    if (running) return;
    running = true;
    setProgress(0, "Starting bulk optimization...");
    bulkStep(0);
  });

  $reset.on("click", function () {
    if (!confirm("Reset stats? This will NOT restore any files.")) return;

    $.post(RillImgOpt.ajaxurl, {
      action: "rill_imgopt_reset_stats",
      nonce: RillImgOpt.nonce
    })
      .done(function (res) {
        if (res && res.success) {
          alert("Stats reset. Refresh the page.");
        } else {
          alert("Could not reset stats.");
        }
      })
      .fail(function () {
        alert("Request failed.");
      });
  });
});
