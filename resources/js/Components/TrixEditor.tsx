import 'trix';
import 'trix/dist/trix.css';
import { useEffect, useId, useRef } from 'react';

/**
 * Tipe minimal untuk Trix custom element. Trix mendaftarkan `<trix-editor>`
 * sebagai web component dengan event `trix-change` saat konten berubah.
 */
interface TrixElement extends HTMLElement {
    editor?: {
        loadHTML: (html: string) => void;
    };
}

interface Props {
    /** HTML konten awal. Trix akan render apa adanya. */
    value: string;
    /** Dipanggil tiap kali konten editor berubah. */
    onChange: (html: string) => void;
    /** Kalau true, editor read-only (toolbar disembunyikan, area tidak bisa di-edit). */
    disabled?: boolean;
    placeholder?: string;
    /**
     * ARIA label untuk editor (Trix tidak otomatis menempelkan label, jadi
     * konsumen wajib mendeskripsikan apa yang sedang di-edit).
     */
    'aria-label'?: string;
    /** Tinggi minimum area editor (Tailwind class). */
    minHeightClass?: string;
}

/**
 * React wrapper untuk Trix editor (web component dari Basecamp).
 *
 * Pola integrasi: kita render hidden `<input>` yang dipakai Trix sebagai source
 * of truth (Trix sinkron ke `input.value`), lalu dengarkan `trix-change` untuk
 * propagate ke React state. `loadHTML()` dipanggil saat `value` dari luar
 * berbeda dengan editor content (mis. reset / load existing).
 */
export default function TrixEditor({
    value,
    onChange,
    disabled = false,
    placeholder,
    minHeightClass = 'min-h-[180px]',
    ...rest
}: Props) {
    const inputId = useId();
    const editorRef = useRef<TrixElement | null>(null);
    const lastEmittedRef = useRef<string>(value);

    useEffect(() => {
        const el = editorRef.current;
        if (!el) return;

        const handleChange = () => {
            const html = el.innerHTML;
            lastEmittedRef.current = html;
            onChange(html);
        };

        el.addEventListener('trix-change', handleChange);

        return () => {
            el.removeEventListener('trix-change', handleChange);
        };
    }, [onChange]);

    // Sinkronkan `value` dari luar (mis. reset form) tanpa loop tak hingga.
    useEffect(() => {
        const el = editorRef.current;
        if (!el || !el.editor) return;
        if (value === lastEmittedRef.current) return;

        el.editor.loadHTML(value ?? '');
        lastEmittedRef.current = value;
    }, [value]);

    return (
        <div className={`trix-wrapper ${disabled ? 'trix-disabled opacity-70' : ''}`}>
            <input id={inputId} type="hidden" defaultValue={value} />
            <trix-editor
                ref={editorRef as React.RefObject<HTMLElement>}
                input={inputId}
                placeholder={placeholder}
                aria-label={rest['aria-label']}
                class={`trix-content prose prose-sm prose-slate max-w-none rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-violet-100 ${minHeightClass}`}
                contenteditable={disabled ? 'false' : 'true'}
            />
        </div>
    );
}
