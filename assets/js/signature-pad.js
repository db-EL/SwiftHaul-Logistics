// ============================================================
// Minimal canvas-based signature pad. No external library —
// just pointer events drawing onto a <canvas id="signatureCanvas">.
// Exposes window.signaturePad with .clear() and .toDataURL()/.isEmpty().
// ============================================================
(function () {
    const canvas = document.getElementById('signatureCanvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    let drawing = false;
    let hasDrawn = false;

    function resizeCanvas() {
        // Preserve drawing across resizes isn't critical here since the
        // pad is cleared between uses; just make sure it's crisp on
        // high-DPI screens.
        const ratio = window.devicePixelRatio || 1;
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * ratio;
        canvas.height = rect.height * ratio;
        ctx.scale(ratio, ratio);
        ctx.lineWidth = 2.2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#0B1F3A';
    }

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        const point = e.touches ? e.touches[0] : e;
        return { x: point.clientX - rect.left, y: point.clientY - rect.top };
    }

    function start(e) {
        drawing = true;
        hasDrawn = true;
        const pos = getPos(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
        e.preventDefault();
    }
    function move(e) {
        if (!drawing) return;
        const pos = getPos(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
        e.preventDefault();
    }
    function end() { drawing = false; }

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    window.addEventListener('mouseup', end);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', move, { passive: false });
    canvas.addEventListener('touchend', end);

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    window.signaturePad = {
        clear() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hasDrawn = false;
        },
        isEmpty() {
            return !hasDrawn;
        },
        toDataURL() {
            return canvas.toDataURL('image/png');
        },
    };

    const clearBtn = document.getElementById('signatureClearBtn');
    clearBtn?.addEventListener('click', () => window.signaturePad.clear());
})();
