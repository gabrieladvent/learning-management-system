import { useForm } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { CloudUpload, FileText, Loader2, Send, Trash2, X } from 'lucide-react';
import { ChangeEvent, FormEvent, useMemo, useRef, useState } from 'react';
import type { MaterialFile } from '@/Components/FileCard';
import { toast } from '@/lib';
import type { AssignmentDetail, AssignmentSubmission } from './assignment.type';

interface Props {
    materialId: string;
    assignment: AssignmentDetail;
    submission: AssignmentSubmission | null;
    /** Kalau true (mis. lewat deadline), seluruh form jadi read-only. */
    disabled?: boolean;
    /** Dipanggil setelah server response sukses — parent biasanya kembali ke view mode. */
    onSuccess?: () => void;
}

function formatBytes(bytes: number): string {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    const size = bytes / 1024 ** i;
    return `${size.toFixed(size >= 10 || i === 0 ? 0 : 1)} ${units[i]}`;
}

export default function SubmissionForm({ materialId, assignment, submission, disabled = false, onSuccess }: Props) {
    const fileInputRef = useRef<HTMLInputElement | null>(null);

    /**
     * Daftar existing files yang ditandai untuk dihapus saat submit.
     * Tetap tampil di UI dengan strike-through supaya siswa bisa undo sebelum submit.
     */
    const [removedExisting, setRemovedExisting] = useState<Set<string>>(new Set());

    const initialFiles: File[] = [];

    const { data, setData, post, processing, errors, reset } = useForm<{
        content: string;
        files: File[];
        removed_file_ids: string[];
    }>({
        content: submission?.content ?? '',
        files: initialFiles,
        removed_file_ids: [],
    });

    const acceptString = useMemo(
        () => assignment.allowed_file_types.map((ext) => `.${ext}`).join(','),
        [assignment.allowed_file_types],
    );

    const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        const list = e.target.files;
        if (!list) return;

        const incoming = Array.from(list);
        const allowed = new Set(assignment.allowed_file_types.map((s) => s.toLowerCase()));
        const maxBytes = assignment.max_file_size_mb * 1024 * 1024;

        const valid: File[] = [];
        for (const file of incoming) {
            const ext = file.name.split('.').pop()?.toLowerCase() ?? '';
            if (!allowed.has(ext)) {
                toast.error(`${file.name}: tipe .${ext} tidak diizinkan`);
                continue;
            }
            if (file.size > maxBytes) {
                toast.error(`${file.name}: melebihi ${assignment.max_file_size_mb} MB`);
                continue;
            }
            valid.push(file);
        }

        setData('files', [...data.files, ...valid]);
        e.target.value = '';
    };

    const removeNewFile = (index: number) => {
        setData(
            'files',
            data.files.filter((_, i) => i !== index),
        );
    };

    const toggleRemoveExisting = (id: string) => {
        const next = new Set(removedExisting);
        if (next.has(id)) next.delete(id);
        else next.add(id);
        setRemovedExisting(next);
        setData('removed_file_ids', Array.from(next));
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (disabled) return;

        post(route('student.assignments.submit', { material: materialId, assignment: assignment.id }), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset('files');
                setRemovedExisting(new Set());
                onSuccess?.();
            },
            onError: (formErrors) => {
                const firstError = Object.values(formErrors)[0];
                if (firstError) toast.error(firstError as string);
            },
        });
    };

    const isEdit = submission !== null;
    const existingFiles = submission?.files ?? [];

    return (
        <form onSubmit={handleSubmit} className="space-y-5">
            <div>
                <label htmlFor="content" className="mb-1.5 block text-sm font-medium text-slate-700">
                    Jawaban / Esai
                </label>
                <textarea
                    id="content"
                    rows={8}
                    value={data.content}
                    onChange={(e) => setData('content', e.target.value)}
                    disabled={disabled || processing}
                    placeholder="Tulis jawabanmu di sini. Boleh sertakan link / tautan referensi."
                    className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100 disabled:bg-slate-50 disabled:text-slate-500"
                />
                {errors.content && <p className="mt-1 text-xs text-rose-600">{errors.content}</p>}
            </div>

            <div>
                <div className="mb-1.5 flex items-center justify-between">
                    <label className="block text-sm font-medium text-slate-700">Lampiran</label>
                    <span className="text-xs text-slate-400">
                        {assignment.allowed_file_types.join(', ')} · maks {assignment.max_file_size_mb} MB
                    </span>
                </div>

                {(existingFiles.length > 0 || data.files.length > 0) && (
                    <ul className="mb-3 space-y-2">
                        {existingFiles.map((file) => {
                            const isRemoved = removedExisting.has(file.id);
                            return (
                                <li
                                    key={file.id}
                                    className={`flex items-center gap-3 rounded-xl border bg-white px-3 py-2.5 ${isRemoved ? 'border-rose-200 bg-rose-50/30' : 'border-slate-200'}`}
                                >
                                    <FileText className={`h-4 w-4 shrink-0 ${isRemoved ? 'text-rose-400' : 'text-slate-400'}`} />
                                    <div className="min-w-0 flex-1">
                                        <div className={`truncate text-sm ${isRemoved ? 'text-slate-400 line-through' : 'text-slate-900'}`}>
                                            {file.name}
                                        </div>
                                        <div className="text-xs text-slate-500">
                                            {file.extension?.toUpperCase()} · {formatBytes(file.size)}
                                            {isRemoved && <span className="ml-2 text-rose-600">akan dihapus</span>}
                                        </div>
                                    </div>
                                    {!disabled && (
                                        <button
                                            type="button"
                                            onClick={() => toggleRemoveExisting(file.id)}
                                            className={`flex h-7 w-7 items-center justify-center rounded-lg text-xs transition-colors ${isRemoved ? 'text-sky-600 hover:bg-sky-50' : 'text-slate-400 hover:bg-rose-50 hover:text-rose-600'}`}
                                            aria-label={isRemoved ? 'Batalkan hapus' : 'Hapus lampiran ini saat submit'}
                                        >
                                            {isRemoved ? <X className="h-3.5 w-3.5" /> : <Trash2 className="h-3.5 w-3.5" />}
                                        </button>
                                    )}
                                </li>
                            );
                        })}
                        {data.files.map((file, i) => (
                            <li
                                key={`new-${i}`}
                                className="flex items-center gap-3 rounded-xl border border-sky-200 bg-sky-50/30 px-3 py-2.5"
                            >
                                <FileText className="h-4 w-4 shrink-0 text-sky-500" />
                                <div className="min-w-0 flex-1">
                                    <div className="truncate text-sm text-slate-900">{file.name}</div>
                                    <div className="text-xs text-sky-700">
                                        {file.name.split('.').pop()?.toUpperCase()} · {formatBytes(file.size)} · baru
                                    </div>
                                </div>
                                {!disabled && (
                                    <button
                                        type="button"
                                        onClick={() => removeNewFile(i)}
                                        className="flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600"
                                        aria-label="Batalkan upload"
                                    >
                                        <X className="h-3.5 w-3.5" />
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                {!disabled && (
                    <motion.button
                        type="button"
                        onClick={() => fileInputRef.current?.click()}
                        whileTap={{ scale: 0.98 }}
                        className="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-4 text-sm font-medium text-slate-600 transition-colors hover:border-sky-400 hover:text-sky-600"
                    >
                        <CloudUpload className="h-5 w-5" />
                        Tambah lampiran
                    </motion.button>
                )}

                <input
                    ref={fileInputRef}
                    type="file"
                    multiple
                    accept={acceptString}
                    onChange={handleFileChange}
                    aria-label="Pilih lampiran tugas"
                    className="hidden"
                />
                {errors.files && <p className="mt-1 text-xs text-rose-600">{errors.files}</p>}
            </div>

            {!disabled && (
                <div className="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                    {isEdit && (
                        <span className="mr-auto text-xs text-slate-500">
                            Kamu mengedit submission yang sudah dikumpulkan.
                        </span>
                    )}
                    <motion.button
                        type="submit"
                        disabled={processing}
                        whileTap={{ scale: 0.97 }}
                        className="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-70"
                    >
                        {processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                        {isEdit ? 'Update Submission' : 'Kumpulkan Tugas'}
                    </motion.button>
                </div>
            )}
        </form>
    );
}
