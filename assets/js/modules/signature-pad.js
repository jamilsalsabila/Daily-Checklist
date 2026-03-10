const canvas = document.getElementById('signature-pad');
const ctx = canvas?.getContext('2d');
const strokesInput = document.getElementById('signature-strokes');
const previewInput = document.getElementById('signature-preview');
const clearButton = document.getElementById('clear-signature');
const form = document.getElementById('checklist-form');

if (canvas && ctx && strokesInput && previewInput && clearButton && form) {
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
        const point = event.touches ? event.touches[0] : event;
        return {
            x: Math.max(0, Math.min(rect.width, point.clientX - rect.left)),
            y: Math.max(0, Math.min(rect.height, point.clientY - rect.top))
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
        drawing = false;
        syncSignatureFields();
    }

    function redraw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#111827';
        ctx.lineWidth = isCoarsePointer ? 2.8 : 2.2;
        strokes.forEach((stroke) => {
            if (stroke.length < 2) return;
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
                stroke.slice(1).forEach((point) => ctx.lineTo(point.x, point.y));
            }

            ctx.stroke();
        });
    }

    function syncSignatureFields() {
        const filtered = strokes.filter((stroke) => stroke.length > 1);
        strokesInput.value = JSON.stringify(filtered);
        previewInput.value = filtered.length ? canvas.toDataURL('image/png') : '';
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

    form.addEventListener('submit', (event) => {
        syncSignatureFields();
        if (!previewInput.value || !strokesInput.value || JSON.parse(strokesInput.value).length === 0) {
            event.preventDefault();
            alert('Tanda tangan wajib diisi sebelum submit.');
        }
    });

    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();
}
