(function () {
    let modalEl = null;
    let videoEl = null;
    let canvasEl = null;
    let ctx = null;
    let statusEl = null;

    let stream = null;
    let faceMesh = null;
    let mpCamera = null;
    let overlayImg = null;

    let settings = {
        overlayUrl: "",
        scale: 1.0,
        xoff: 0.0,
        yoff: 0.0
    };

    function setStatus(msg) {
        if (statusEl) statusEl.textContent = msg;
    }

    function ensureModal() {
        if (modalEl) return;

        const backdrop = document.createElement("div");
        backdrop.className = "wvt-modal-backdrop";
        backdrop.innerHTML = `
      <div class="wvt-modal" role="dialog" aria-modal="true">
        <div class="wvt-modal-header">
          <p class="wvt-modal-title">Virtual Try-On</p>
          <div class="wvt-modal-actions">
            <button type="button" class="wvt-icon-btn" data-wvt-action="flip">Flip</button>
            <button type="button" class="wvt-icon-btn" data-wvt-action="close">Close</button>
          </div>
        </div>
        <div class="wvt-stage">
          <video class="wvt-video" autoplay playsinline muted></video>
          <canvas class="wvt-canvas"></canvas>
          <div class="wvt-status">Loading...</div>
        </div>
      </div>
    `;
        document.body.appendChild(backdrop);

        modalEl = backdrop;
        videoEl = backdrop.querySelector("video");
        canvasEl = backdrop.querySelector("canvas");
        statusEl = backdrop.querySelector(".wvt-status");
        ctx = canvasEl.getContext("2d");

        backdrop.addEventListener("click", (e) => {
            if (e.target === backdrop) closeModal();
        });

        backdrop.querySelector('[data-wvt-action="close"]').addEventListener("click", closeModal);
        backdrop.querySelector('[data-wvt-action="flip"]').addEventListener("click", flipCamera);

        window.addEventListener("resize", resizeCanvas);
    }

    function openModal() {
        ensureModal();
        modalEl.style.display = "flex";
        setStatus("Requesting camera permission...");
        startTryOn().catch((err) => {
            console.error(err);
            setStatus("Camera failed. Please allow permission, and try again.");
        });
    }

    async function closeModal() {
        if (!modalEl) return;
        await stopTryOn();
        modalEl.style.display = "none";
    }

    function resizeCanvas() {
        if (!canvasEl || !videoEl) return;

        // Match canvas internal size to displayed size for sharp drawing
        const rect = canvasEl.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;

        canvasEl.width = Math.floor(rect.width * dpr);
        canvasEl.height = Math.floor(rect.height * dpr);

        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }

    function loadScriptOnce(src) {
        return new Promise((resolve, reject) => {
            const existing = document.querySelector(`script[data-wvt-src="${src}"]`);
            if (existing) {
                if (existing.dataset.loaded === "1") return resolve();
                existing.addEventListener("load", () => resolve());
                existing.addEventListener("error", reject);
                return;
            }

            const s = document.createElement("script");
            s.src = src;
            s.async = true;
            s.dataset.wvtSrc = src;
            s.addEventListener("load", () => {
                s.dataset.loaded = "1";
                resolve();
            });
            s.addEventListener("error", reject);
            document.head.appendChild(s);
        });
    }

    async function ensureMediaPipe() {
        // Loaded only after user clicks, to avoid impacting site performance
        setStatus("Loading face tracking...");
        await loadScriptOnce("https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js");
        await loadScriptOnce("https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js");
        await loadScriptOnce("https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/face_mesh.js");
    }

    async function loadOverlay(url) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.crossOrigin = "anonymous";
            img.onload = () => resolve(img);
            img.onerror = reject;
            img.src = url;
        });
    }

    async function startCamera(facingMode) {
        const constraints = {
            audio: false,
            video: {
                facingMode: facingMode || "user",
                width: { ideal: 1280 },
                height: { ideal: 720 }
            }
        };

        stream = await navigator.mediaDevices.getUserMedia(constraints);
        videoEl.srcObject = stream;

        await new Promise((resolve) => {
            videoEl.onloadedmetadata = () => resolve();
        });

        await videoEl.play();
    }

    function stopCamera() {
        if (mpCamera && mpCamera.stop) {
            try { mpCamera.stop(); } catch (e) { }
        }
        mpCamera = null;

        if (stream) {
            stream.getTracks().forEach(t => t.stop());
            stream = null;
        }
        if (videoEl) videoEl.srcObject = null;
    }

    async function startTryOn() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setStatus("Your browser does not support camera access.");
            return;
        }

        await ensureMediaPipe();

        overlayImg = await loadOverlay(settings.overlayUrl);

        await startCamera(currentFacingMode);

        resizeCanvas();

        faceMesh = new FaceMesh.FaceMesh({
            locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/${file}`
        });

        faceMesh.setOptions({
            maxNumFaces: 1,
            refineLandmarks: true,
            minDetectionConfidence: 0.6,
            minTrackingConfidence: 0.6
        });

        faceMesh.onResults(onResults);

        setStatus("Starting try-on...");

        mpCamera = new CameraUtils.Camera(videoEl, {
            onFrame: async () => {
                await faceMesh.send({ image: videoEl });
            },
            width: 1280,
            height: 720
        });

        mpCamera.start();
        setStatus("Ready");
    }

    async function stopTryOn() {
        stopCamera();
        faceMesh = null;
        overlayImg = null;

        if (ctx && canvasEl) {
            ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);
        }
        setStatus("Stopped");
    }

    // Face landmark indices used
    // 33 = left eye outer corner, 263 = right eye outer corner (from user's perspective)
    // Using these gives stable width and rotation
    function onResults(results) {
        if (!canvasEl || !ctx) return;
        ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);

        if (!results.multiFaceLandmarks || results.multiFaceLandmarks.length === 0) {
            setStatus("Align your face in the camera");
            return;
        }

        setStatus("Ready");

        const lm = results.multiFaceLandmarks[0];
        const left = lm[33];
        const right = lm[263];

        // Canvas is scaled with DPR transform, so use CSS pixels
        const rect = canvasEl.getBoundingClientRect();
        const w = rect.width;
        const h = rect.height;

        const lx = left.x * w;
        const ly = left.y * h;
        const rx = right.x * w;
        const ry = right.y * h;

        const dx = rx - lx;
        const dy = ry - ly;
        const eyeDist = Math.sqrt(dx * dx + dy * dy);
        const angle = Math.atan2(dy, dx);

        // Adjust these multipliers as needed for typical eyewear images
        const baseWidth = eyeDist * 2.15 * settings.scale;

        const imgAspect = overlayImg.width / overlayImg.height;
        const drawW = baseWidth;
        const drawH = drawW / imgAspect;

        const cx = (lx + rx) / 2 + settings.xoff;
        const cy = (ly + ry) / 2 + (drawH * 0.08) + settings.yoff;

        ctx.save();
        ctx.translate(cx, cy);
        ctx.rotate(angle);
        ctx.drawImage(overlayImg, -drawW / 2, -drawH / 2, drawW, drawH);
        ctx.restore();
    }

    let currentFacingMode = "user";

    async function flipCamera() {
        currentFacingMode = currentFacingMode === "user" ? "environment" : "user";
        setStatus("Switching camera...");
        await stopTryOn();
        await startTryOn();
    }

    function parseButtonSettings(btn) {
        settings.overlayUrl = btn.getAttribute("data-wvt-overlay-url") || "";
        settings.scale = parseFloat(btn.getAttribute("data-wvt-scale") || "1") || 1;
        settings.xoff = parseFloat(btn.getAttribute("data-wvt-xoff") || "0") || 0;
        settings.yoff = parseFloat(btn.getAttribute("data-wvt-yoff") || "0") || 0;
    }

    document.addEventListener("click", (e) => {
        const btn = e.target.closest(".wvt-tryon-btn");
        if (!btn) return;

        const overlayUrl = btn.getAttribute("data-wvt-overlay-url");
        if (!overlayUrl) {
            alert("Try-On overlay image is not set for this product.");
            return;
        }

        parseButtonSettings(btn);
        openModal();
    });
})();
