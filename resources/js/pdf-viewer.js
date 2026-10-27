/**
 * View-only PDF viewer (resources/views/documents/viewer.blade.php).
 * Draws each page onto a canvas with PDF.js, so there is no download or print button,
 * and blocks the usual shortcuts for saving and printing.
 */
import * as pdfjsLib from 'pdfjs-dist';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = workerUrl;

const root = document.getElementById('pdf-viewer');
const pagesEl = document.getElementById('pdf-pages');
const statusEl = document.getElementById('pdf-status');
const zoomLabel = document.getElementById('pdf-zoom');
const pageCountEl = document.getElementById('pdf-page-count');

const MIN_SCALE = 0.5;
const MAX_SCALE = 3;
let scale = 1.25;
let pdf = null;
let renderRun = 0;

// No right-click menu, dragging pages out, saving or printing.
document.addEventListener('contextmenu', (event) => event.preventDefault());
document.addEventListener('dragstart', (event) => event.preventDefault());
document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && ['s', 'p'].includes(event.key.toLowerCase())) {
        event.preventDefault();
    }
});

async function renderPages() {
    const run = ++renderRun;
    const ratio = window.devicePixelRatio || 1;
    zoomLabel.textContent = `${Math.round(scale * 100)}%`;
    pagesEl.replaceChildren();

    for (let number = 1; number <= pdf.numPages; number++) {
        const page = await pdf.getPage(number);

        // A newer zoom started; stop drawing this one.
        if (run !== renderRun) {
            return;
        }

        const viewport = page.getViewport({ scale });
        const canvas = document.createElement('canvas');
        canvas.width = Math.floor(viewport.width * ratio);
        canvas.height = Math.floor(viewport.height * ratio);
        canvas.style.width = `${Math.floor(viewport.width)}px`;
        canvas.style.height = `${Math.floor(viewport.height)}px`;
        canvas.className = 'mx-auto block max-w-none bg-white shadow-lg';
        canvas.setAttribute('aria-label', `Page ${number} of ${pdf.numPages}`);
        pagesEl.append(canvas);

        await page.render({
            canvasContext: canvas.getContext('2d'),
            viewport,
            transform: ratio !== 1 ? [ratio, 0, 0, ratio, 0, 0] : null,
        }).promise;
    }
}

function zoom(step) {
    const next = Math.min(MAX_SCALE, Math.max(MIN_SCALE, Math.round((scale + step) * 100) / 100));

    if (pdf && next !== scale) {
        scale = next;
        renderPages();
    }
}

document.getElementById('pdf-zoom-out').addEventListener('click', () => zoom(-0.25));
document.getElementById('pdf-zoom-in').addEventListener('click', () => zoom(0.25));

try {
    pdf = await pdfjsLib.getDocument({
        url: root.dataset.url,
        httpHeaders: { 'X-PMS-Viewer': '1' },
        withCredentials: true,
        disableRange: true,
        disableStream: true,
        isEvalSupported: false,
    }).promise;

    pageCountEl.textContent = `${pdf.numPages} ${pdf.numPages === 1 ? 'page' : 'pages'}`;
    statusEl.hidden = true;
    await renderPages();
} catch (error) {
    statusEl.textContent = root.dataset.errorMessage;
    statusEl.classList.add('text-red-300');
}
