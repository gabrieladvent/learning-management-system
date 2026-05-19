import katex from 'katex';
import { useEffect, useRef } from 'react';

interface Props {
    /** Raw HTML (boleh berisi `$...$` / `$$...$$` / `\\(...\\)` / `\\[...\\]`). */
    html: string;
    className?: string;
}

const DELIMITERS: Array<{ open: string; close: string; display: boolean }> = [
    { open: '$$', close: '$$', display: true },
    { open: '\\[', close: '\\]', display: true },
    { open: '\\(', close: '\\)', display: false },
    { open: '$', close: '$', display: false },
];

/**
 * Render math di dalam text node — TIDAK menyentuh element node, jadi
 * markup HTML user (heading, list, image) tetap utuh.
 */
function renderMathInNode(node: Node) {
    if (node.nodeType === Node.TEXT_NODE) {
        const text = node.textContent ?? '';
        if (!text || !text.includes('$') && !text.includes('\\')) return;

        const fragments = splitByDelimiters(text);
        if (fragments.length === 1 && !fragments[0].math) return;

        const parent = node.parentNode;
        if (!parent) return;

        const frag = document.createDocumentFragment();
        for (const part of fragments) {
            if (part.math) {
                const span = document.createElement('span');
                try {
                    katex.render(part.value, span, {
                        displayMode: part.display,
                        throwOnError: false,
                        strict: 'ignore',
                    });
                } catch {
                    span.textContent = part.raw;
                }
                frag.appendChild(span);
            } else {
                frag.appendChild(document.createTextNode(part.value));
            }
        }
        parent.replaceChild(frag, node);
        return;
    }

    if (node.nodeType === Node.ELEMENT_NODE) {
        const el = node as HTMLElement;
        const tag = el.tagName.toLowerCase();
        if (tag === 'script' || tag === 'style' || tag === 'code' || tag === 'pre') return;
        if (el.classList.contains('katex')) return;
        Array.from(node.childNodes).forEach(renderMathInNode);
    }
}

type Fragment = { math: false; value: string } | { math: true; value: string; display: boolean; raw: string };

function splitByDelimiters(text: string): Fragment[] {
    const result: Fragment[] = [];
    let i = 0;
    let buffer = '';

    while (i < text.length) {
        const match = findNextDelimiter(text, i);
        if (!match) {
            buffer += text.slice(i);
            break;
        }

        buffer += text.slice(i, match.start);
        const closeIdx = text.indexOf(match.close, match.start + match.open.length);

        if (closeIdx === -1) {
            buffer += text.slice(match.start);
            break;
        }

        if (buffer) {
            result.push({ math: false, value: buffer });
            buffer = '';
        }

        const inner = text.slice(match.start + match.open.length, closeIdx);
        const raw = text.slice(match.start, closeIdx + match.close.length);
        result.push({ math: true, value: inner, display: match.display, raw });
        i = closeIdx + match.close.length;
    }

    if (buffer) result.push({ math: false, value: buffer });
    return result.length > 0 ? result : [{ math: false, value: text }];
}

function findNextDelimiter(text: string, from: number) {
    let best: { start: number; open: string; close: string; display: boolean } | null = null;
    for (const d of DELIMITERS) {
        const idx = text.indexOf(d.open, from);
        if (idx === -1) continue;
        if (!best || idx < best.start || (idx === best.start && d.open.length > best.open.length)) {
            best = { start: idx, open: d.open, close: d.close, display: d.display };
        }
    }
    return best;
}

export default function MathContent({ html, className }: Props) {
    const ref = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        if (!ref.current) return;
        ref.current.innerHTML = html;
        renderMathInNode(ref.current);
    }, [html]);

    return <div ref={ref} className={className} />;
}
