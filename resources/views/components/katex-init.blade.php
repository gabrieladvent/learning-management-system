{{-- KaTeX (display rendering) --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css">
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js"></script>

{{-- MathLive (visual input editor) --}}
<script src="https://unpkg.com/mathlive@0.103.0/dist/mathlive.min.js"></script>

<style>
    /* Floating math editor button — icon only, expand on hover */
    .math-editor-fab {
        position: fixed; bottom: 24px; right: 24px; z-index: 9999;
        background: linear-gradient(135deg, #0ea5e9, #0284c7);
        color: white; border: none; border-radius: 9999px;
        height: 52px; min-width: 52px; padding: 0 16px;
        display: flex; align-items: center; justify-content: center; gap: 0;
        font-size: 14px; font-weight: 600;
        box-shadow: 0 10px 25px rgba(14, 165, 233, 0.4);
        cursor: pointer; overflow: hidden; white-space: nowrap;
        transition: gap 0.25s, padding 0.25s, box-shadow 0.25s, transform 0.15s;
    }
    .math-editor-fab .math-fab-label {
        max-width: 0; opacity: 0; overflow: hidden;
        transition: max-width 0.25s, opacity 0.2s;
    }
    .math-editor-fab:hover {
        gap: 8px; padding-right: 20px;
        box-shadow: 0 15px 30px rgba(14, 165, 233, 0.5);
        transform: translateY(-2px);
    }
    .math-editor-fab:hover .math-fab-label {
        max-width: 180px; opacity: 1;
    }
    .math-editor-fab svg { width: 22px; height: 22px; flex-shrink: 0; }

    .math-editor-overlay {
        position: fixed; inset: 0; z-index: 2147483647;
        background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
        display: none; align-items: center; justify-content: center; padding: 20px;
    }
    .math-editor-overlay.active { display: flex; }

    .math-editor-modal {
        background: white; border-radius: 16px; width: 100%; max-width: 720px;
        box-shadow: 0 25px 50px rgba(0,0,0,0.25); overflow: hidden;
        max-height: 90vh; display: flex; flex-direction: column;
    }
    .dark .math-editor-modal { background: #1f2937; color: white; }
    .math-editor-header {
        padding: 20px 24px; border-bottom: 1px solid #e5e7eb;
        display: flex; align-items: center; justify-content: space-between;
    }
    .dark .math-editor-header { border-color: #374151; }
    .math-editor-header h2 { margin: 0; font-size: 18px; font-weight: 600; }
    .math-editor-close {
        background: transparent; border: none; cursor: pointer;
        font-size: 28px; line-height: 1; color: #6b7280;
    }
    .math-editor-body { padding: 20px 24px; overflow-y: auto; flex: 1; }
    .math-editor-label {
        font-size: 13px; font-weight: 500; color: #6b7280; margin-bottom: 8px;
    }
    .math-editor-body math-field {
        display: block; width: 100%; min-height: 90px;
        font-size: 24px; padding: 16px;
        border: 2px solid #0ea5e9; border-radius: 12px; background: #f9fafb;
    }
    .dark .math-editor-body math-field {
        background: #111827; border-color: #0ea5e9; color: white;
    }
    .math-editor-body math-field:focus-within {
        outline: 3px solid rgba(14, 165, 233, 0.2);
    }

    .math-templates {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
        gap: 8px; margin-top: 16px;
    }
    .math-template-btn {
        padding: 10px 8px; background: white;
        border: 1px solid #d1d5db; border-radius: 8px;
        cursor: pointer; font-size: 14px;
        display: flex; align-items: center; justify-content: center; min-height: 44px;
    }
    .math-template-btn:hover { background: #f0f9ff; border-color: #0ea5e9; }
    .dark .math-template-btn { background: #374151; border-color: #4b5563; color: white; }
    .math-template-btn .katex { font-size: 16px !important; }

    .math-editor-preview {
        margin-top: 16px; padding: 16px;
        background: #f0f9ff; border: 1px dashed #0ea5e9; border-radius: 8px;
        min-height: 50px; text-align: center; font-size: 20px;
    }
    .dark .math-editor-preview { background: #082f49; }
    .math-editor-preview-label {
        font-size: 11px; color: #0369a1; text-align: left; margin-bottom: 6px;
    }
    .dark .math-editor-preview-label { color: #7dd3fc; }

    .math-editor-latex {
        margin-top: 12px; padding: 10px 12px;
        background: #f3f4f6; border-radius: 8px;
        font-family: ui-monospace, monospace;
        font-size: 12px; word-break: break-all; min-height: 18px; color: #374151;
    }
    .dark .math-editor-latex { background: #111827; color: #d1d5db; }

    .math-editor-footer {
        padding: 14px 24px; border-top: 1px solid #e5e7eb;
        display: flex; justify-content: space-between; gap: 12px; background: #f9fafb;
    }
    .dark .math-editor-footer { background: #111827; border-color: #374151; }
    .math-btn {
        padding: 10px 18px; border-radius: 8px; font-weight: 500;
        cursor: pointer; border: none; font-size: 14px;
        display: inline-flex; align-items: center; gap: 6px;
    }
    .math-btn-secondary { background: #e5e7eb; color: #374151; }
    .math-btn-secondary:hover { background: #d1d5db; }
    .math-btn-success { background: #10b981; color: white; }
    .math-btn-success:hover { background: #059669; }
    .dark .math-btn-secondary { background: #4b5563; color: white; }

    .math-hint {
        font-size: 12px; color: #6b7280; margin-top: 8px;
        background: #fef3c7; padding: 8px 12px; border-radius: 6px; border: 1px solid #fde68a;
    }
    .dark .math-hint { background: #422006; color: #fde68a; border-color: #78350f; }
</style>

<button id="math-editor-fab" class="math-editor-fab" type="button" title="Editor Formula Matematika" onclick="window.openMathEditor && window.openMathEditor()">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
    </svg>
    <span class="math-fab-label">Editor Formula</span>
</button>

<div id="math-editor-overlay" class="math-editor-overlay">
    <div class="math-editor-modal">
        <div class="math-editor-header">
            <h2>🧮 Editor Formula Matematika</h2>
            <button class="math-editor-close" type="button" onclick="window.closeMathEditor && window.closeMathEditor()">&times;</button>
        </div>
        <div class="math-editor-body">
            <div class="math-editor-label">Klik di kotak biru lalu mulai ketik:</div>
            <math-field id="math-input"></math-field>

            <div class="math-hint">
                💡 <strong>Cara cepat:</strong> klik kotak biru lalu ketik: <code>x^2</code> (pangkat), <code>1/2</code> (pecahan), <code>sqrt(x)</code> (akar), <code>pi</code> (π).
            </div>

            <div class="math-editor-label" style="margin-top: 18px;">Template Cepat:</div>
            <div class="math-templates" id="math-templates"></div>

            <div class="math-editor-preview-label" style="margin-top: 18px;">Preview hasil:</div>
            <div class="math-editor-preview" id="math-preview">—</div>

            <div class="math-editor-label" style="margin-top: 12px;">Kode LaTeX:</div>
            <div class="math-editor-latex" id="math-latex-output">—</div>
        </div>
        <div class="math-editor-footer">
            <button class="math-btn math-btn-secondary" type="button" onclick="window.clearMathEditor && window.clearMathEditor()">Bersihkan</button>
            <div style="display:flex; gap:8px;">
                <button class="math-btn math-btn-secondary" type="button" onclick="window.closeMathEditor && window.closeMathEditor()">Tutup</button>
                <button id="math-copy-btn" class="math-btn math-btn-success" type="button" onclick="window.copyMathToClipboard && window.copyMathToClipboard()">
                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    Sisipkan (Copy ke Clipboard)
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const katexConfig = {
        delimiters: [
            { left: '$$', right: '$$', display: true },
            { left: '$', right: '$', display: false },
            { left: '\\(', right: '\\)', display: false },
            { left: '\\[', right: '\\]', display: true },
        ],
        throwOnError: false,
    };

    const renderMath = (root) => {
        if (typeof window.renderMathInElement === 'function') {
            try { window.renderMathInElement(root || document.body, katexConfig); }
            catch (e) { console.warn('KaTeX render error:', e); }
        }
    };

    const templates = [
        { label: 'Pecahan',     latex: '\\frac{a}{b}',            display: '\\frac{a}{b}' },
        { label: 'Pangkat',     latex: 'x^{2}',                   display: 'x^{2}' },
        { label: 'Indeks',      latex: 'x_{1}',                   display: 'x_{1}' },
        { label: 'Akar',        latex: '\\sqrt{x}',               display: '\\sqrt{x}' },
        { label: 'Akar Pangkat n',latex: '\\sqrt[n]{x}',          display: '\\sqrt[n]{x}' },
        { label: 'Persamaan Kuadrat', latex: 'ax^2 + bx + c = 0', display: 'ax^2+bx+c=0' },
        { label: 'Rumus ABC',   latex: 'x = \\frac{-b \\pm \\sqrt{b^2 - 4ac}}{2a}', display: 'x=\\frac{-b\\pm\\sqrt{b^2-4ac}}{2a}' },
        { label: 'Sigma',       latex: '\\sum_{i=1}^{n} a_i',     display: '\\sum_{i=1}^{n}' },
        { label: 'Integral',    latex: '\\int_{a}^{b} f(x) dx',   display: '\\int_{a}^{b}' },
        { label: 'Limit',       latex: '\\lim_{x \\to a} f(x)',   display: '\\lim_{x \\to a}' },
        { label: 'Theta',       latex: '\\theta',                 display: '\\theta' },
        { label: 'Pi',          latex: '\\pi',                    display: '\\pi' },
    ];

    let inertElements = [];

    const lockBackground = () => {
        inertElements = [];
        Array.from(document.body.children).forEach(el => {
            if (el.id === 'math-editor-overlay' || el.id === 'math-editor-fab') return;
            if (el.tagName === 'SCRIPT' || el.tagName === 'STYLE') return;
            if (!el.hasAttribute('inert')) {
                el.setAttribute('inert', '');
                inertElements.push(el);
            }
        });
    };

    const unlockBackground = () => {
        inertElements.forEach(el => el.removeAttribute('inert'));
        inertElements = [];
    };

    const cleanLatex = (raw) => {
        if (!raw) return '';
        return raw.replace(/\\placeholder\{\s*\}/g, '').trim();
    };

    const getMathInput = () => document.getElementById('math-input');
    const getOverlay = () => document.getElementById('math-editor-overlay');

    const updateOutputs = () => {
        const mathInput = getMathInput();
        const latexOutput = document.getElementById('math-latex-output');
        const preview = document.getElementById('math-preview');
        if (!mathInput || !latexOutput || !preview) return;

        const raw = mathInput.value || '';
        const cleaned = cleanLatex(raw);
        latexOutput.textContent = cleaned ? `$${cleaned}$` : '—';

        if (cleaned && window.katex) {
            try {
                preview.innerHTML = '';
                window.katex.render(cleaned, preview, { throwOnError: false });
            } catch (e) {
                preview.textContent = cleaned;
            }
        } else {
            preview.textContent = '—';
        }
    };

    const focusMathField = () => {
        const mf = getMathInput();
        if (mf) {
            try { mf.focus(); } catch (e) { console.warn('Focus error:', e); }
        }
    };

    // Expose global functions
    window.openMathEditor = function() {
        console.log('[MathEditor] Opening...');
        const overlay = getOverlay();
        if (!overlay) {
            console.error('[MathEditor] Overlay not found');
            return;
        }
        if (document.activeElement && typeof document.activeElement.blur === 'function') {
            document.activeElement.blur();
        }
        overlay.classList.add('active');
        lockBackground();
        requestAnimationFrame(focusMathField);
        setTimeout(focusMathField, 100);
        setTimeout(focusMathField, 300);
    };

    window.closeMathEditor = function() {
        const overlay = getOverlay();
        if (overlay) overlay.classList.remove('active');
        unlockBackground();
    };

    window.clearMathEditor = function() {
        const mf = getMathInput();
        if (mf) {
            mf.value = '';
            updateOutputs();
            focusMathField();
        }
    };

    window.copyMathToClipboard = async function() {
        const mf = getMathInput();
        if (!mf) return;
        const raw = mf.value || '';
        const cleaned = cleanLatex(raw);
        if (!cleaned) {
            alert('Buat formula matematika terlebih dahulu.');
            return;
        }
        const wrapped = `$${cleaned}$`;
        try {
            await navigator.clipboard.writeText(wrapped);
            const btn = document.getElementById('math-copy-btn');
            if (btn) {
                const original = btn.innerHTML;
                btn.innerHTML = '✓ Tersalin! Paste ke kolom soal';
                setTimeout(() => { btn.innerHTML = original; }, 2000);
            }
        } catch (err) {
            console.error('Copy failed:', err);
            alert('Gagal menyalin. Coba lagi.');
        }
    };

    const setupTemplates = () => {
        const container = document.getElementById('math-templates');
        if (!container || container.children.length > 0) return;
        if (!window.katex) return;

        templates.forEach(tpl => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'math-template-btn';
            btn.title = tpl.label;
            try {
                const span = document.createElement('span');
                window.katex.render(tpl.display, span, { throwOnError: false });
                btn.appendChild(span);
            } catch (e) {
                btn.textContent = tpl.label;
            }
            btn.addEventListener('mousedown', (e) => e.preventDefault());
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const mf = getMathInput();
                if (!mf) return;
                focusMathField();
                if (typeof mf.executeCommand === 'function') {
                    try { mf.executeCommand(['insert', tpl.latex]); }
                    catch (err) { mf.value = (mf.value || '') + tpl.latex; }
                } else {
                    mf.value = (mf.value || '') + tpl.latex;
                }
                updateOutputs();
            });
            container.appendChild(btn);
        });
    };

    const init = () => {
        console.log('[MathEditor] Init check', {
            katex: !!window.katex,
            renderMathInElement: typeof window.renderMathInElement,
            mathField: !!customElements.get('math-field'),
        });

        // Setup math-field listeners
        const mf = getMathInput();
        if (mf) {
            mf.addEventListener('input', updateOutputs);
            mf.addEventListener('change', updateOutputs);
        }

        setupTemplates();
        renderMath();

        // ESC to close
        document.addEventListener('keydown', (e) => {
            const overlay = getOverlay();
            if (e.key === 'Escape' && overlay && overlay.classList.contains('active')) {
                window.closeMathEditor();
            }
        });

        // Click backdrop to close
        const overlay = getOverlay();
        if (overlay) {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) window.closeMathEditor();
            });
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => setTimeout(init, 100));
    } else {
        setTimeout(init, 100);
    }

    // Wait for libs to be ready, then re-setup templates if needed
    let attempts = 0;
    const waitForLibs = setInterval(() => {
        attempts++;
        if (window.katex && customElements.get('math-field')) {
            clearInterval(waitForLibs);
            setupTemplates();
            renderMath();
            console.log('[MathEditor] Libs ready after', attempts, 'attempts');
        }
        if (attempts > 100) {
            clearInterval(waitForLibs);
            console.error('[MathEditor] Libs failed to load after 10s');
        }
    }, 100);

    document.addEventListener('livewire:initialized', () => {
        if (window.Livewire) {
            Livewire.hook('morph.updated', ({ el }) => renderMath(el));
            Livewire.hook('commit', ({ succeed }) => {
                succeed(() => setTimeout(() => renderMath(), 50));
            });
        }
    });
})();
</script>
