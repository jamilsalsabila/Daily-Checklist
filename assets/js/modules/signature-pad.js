(() => {
    const widget = document.querySelector('[data-signature-widget="1"]');
    const canvas = widget?.querySelector('#signature-pad');
    const ctx = canvas?.getContext('2d');
    const strokesInput = widget?.querySelector('#signature-strokes');
    const previewInput = widget?.querySelector('#signature-preview');
    const responseInput = widget?.querySelector('#signature-response');
    const clearButton = widget?.querySelector('#clear-signature');
    const form = document.getElementById('checklist-form');

    if (!widget || !canvas || !ctx || !strokesInput || !previewInput || !clearButton || !form) {
        return;
    }

    let drawing = false;
    let currentStroke = [];
    let strokes = [];
    const isCoarsePointer = window.matchMedia('(pointer: coarse)').matches;

    function resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const rect = canvas.getBoundingClientRect();
        const targetHeight = window.innerWidth <= 720 ? 270 : 220;
        canvas.width = rect.width * ratio;
        canvas.height = targetHeight * ratio;
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        redraw();
    }

    function getPoint(event) {
        const rect = canvas.getBoundingClientRect();
        const touch = typeof TouchEvent !== 'undefined' && event instanceof TouchEvent ? event.touches[0] : null;
        const source = touch ?? event;
        return {
            x: Math.max(0, Math.min(rect.width, source.clientX - rect.left)),
            y: Math.max(0, Math.min(rect.height, source.clientY - rect.top)),
        };
    }

    function startDraw(event) {
        event.preventDefault();
        drawing = true;
        currentStroke = [];
        const point = getPoint(event);
        currentStroke.push(point);
        strokes.push(currentStroke);
        redraw();
    }

    function draw(event) {
        if (!drawing) return;
        event.preventDefault();
        currentStroke.push(getPoint(event));
        redraw();
    }

    function endDraw() {
        if (!drawing) return;
        drawing = false;
        syncSignatureFields();
    }

    function redraw() {
        const rect = canvas.getBoundingClientRect();
        ctx.clearRect(0, 0, rect.width, rect.height);
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#111827';
        ctx.lineWidth = isCoarsePointer ? 2.8 : 2.2;

        strokes.forEach((stroke) => {
            if (!Array.isArray(stroke) || stroke.length < 2) return;
            ctx.beginPath();
            ctx.moveTo(stroke[0].x, stroke[0].y);

            if (isCoarsePointer && stroke.length > 2) {
                for (let i = 1; i < stroke.length - 1; i += 1) {
                    const midX = (stroke[i].x + stroke[i + 1].x) / 2;
                    const midY = (stroke[i].y + stroke[i + 1].y) / 2;
                    ctx.quadraticCurveTo(stroke[i].x, stroke[i].y, midX, midY);
                }
                const last = stroke[stroke.length - 1];
                ctx.lineTo(last.x, last.y);
            } else {
                for (let i = 1; i < stroke.length; i += 1) {
                    ctx.lineTo(stroke[i].x, stroke[i].y);
                }
            }
            ctx.stroke();
        });
    }

    function syncSignatureFields() {
        const filtered = strokes.filter((stroke) => Array.isArray(stroke) && stroke.length > 1);
        strokesInput.value = JSON.stringify(filtered);
        previewInput.value = filtered.length ? canvas.toDataURL('image/png') : '';
        if (responseInput) {
            responseInput.value = filtered.length ? 'signed' : '';
        }
    }

    clearButton.addEventListener('click', () => {
        strokes = [];
        currentStroke = [];
        redraw();
        syncSignatureFields();
    });

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    window.addEventListener('mouseup', endDraw);
    canvas.addEventListener('touchstart', startDraw, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    window.addEventListener('touchend', endDraw);
    window.addEventListener('touchcancel', endDraw);

    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented) return;
        syncSignatureFields();
        const isRequired = Boolean(responseInput?.required);
        const hasStroke = previewInput.value && strokesInput.value && JSON.parse(strokesInput.value).length > 0;
        if (isRequired && !hasStroke) {
            event.preventDefault();
            window.alert('Tanda tangan wajib diisi sebelum submit.');
        }
    });

    window.addEventListener('orientationchange', () => {
        window.setTimeout(resizeCanvas, 180);
    }, { passive: true });
    resizeCanvas();
})();
