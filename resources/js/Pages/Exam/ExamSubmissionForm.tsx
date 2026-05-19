import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    AlertCircle,
    ArrowLeft,
    CalendarClock,
    CheckCircle2,
    CloudUpload,
    FileSpreadsheet,
    FileText,
    Link as LinkIcon,
    Loader2,
    LucideIcon,
    MessageSquare,
    Paperclip,
    Send,
    Target,
    Trash2,
    X,
} from 'lucide-react';
import { ChangeEvent, FormEvent, useMemo, useRef, useState } from 'react';
import { ActivityTimeline, MathContent, TrixEditor } from '@/Components';
import type { ExamStatus, ExamSubmissionFormPageProps } from '@/Components/Exam';
import { StudentLayout } from '@/Layouts';
import { toast } from '@/lib';
import { PageProps } from '@/types';

const STATUS_BANNER: Record<ExamStatus, { label: string; tone: 'violet' | 'sky' | 'amber' | 'emerald'; icon: LucideIcon }> = {
    belum_mulai: { label: 'Belum dikumpulkan', tone: 'violet', icon: FileSpreadsheet },
    in_progress: { label: 'Sedang dikerjakan', tone: 'sky', icon: FileSpreadsheet },
    submitted: { label: 'Menunggu penilaian guru', tone: 'amber', icon: CheckCircle2 },
    graded: { label: 'Sudah dinilai', tone: 'emerald', icon: CheckCircle2 },
};

const TONE_CLASS: Record<'violet' | 'sky' | 'amber' | 'emerald', { bg: string; border: string; text: string; icon: string }> = {
    violet: { bg: 'bg-violet-50', border: 'border-violet-200', text: 'text-violet-900', icon: 'text-violet-600' },
    sky: { bg: 'bg-sky-50', border: 'border-sky-200', text: 'text-sky-900', icon: 'text-sky-600' },
    amber: { bg: 'bg-amber-50', border: 'border-amber-200', text: 'text-amber-900', icon: 'text-amber-600' },
    emerald: { bg: 'bg-emerald-50', border: 'border-emerald-200', text: 'text-emerald-900', icon: 'text-emerald-600' },
};

function formatDateTime(iso: string | null): string | null {
    if (!iso) return null;
    return new Date(iso).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatBytes(bytes: number): string {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    const size = bytes / 1024 ** i;

    return `${size.toFixed(size >= 10 || i === 0 ? 0 : 1)} ${units[i]}`;
}

export default function ExamSubmissionFormPage() {
    const { props } = usePage<PageProps<ExamSubmissionFormPageProps>>();
    const { course, material, exam, submission, activities } = props;

    const isGraded = exam.status === 'graded';
    const isWindowOpen = exam.available_until ? new Date(exam.available_until).getTime() > Date.now() : true;
    const submitDisabled = isGraded || !isWindowOpen;
    const status = STATUS_BANNER[exam.status];
    const tone = TONE_CLASS[status.tone];
    const StatusIcon = status.icon;

    const [editing, setEditing] = useState(submission === null);
    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const [removedExisting, setRemovedExisting] = useState<Set<string>>(new Set());

    const { data, setData, post, processing, errors, reset } = useForm<{
        content: string;
        link_url: string;
        files: File[];
        removed_file_ids: string[];
    }>({
        content: submission?.content ?? '',
        link_url: submission?.link_url ?? '',
        files: [],
        removed_file_ids: [],
    });

    const acceptString = useMemo(
        () => exam.allowed_file_types.map((ext) => `.${ext}`).join(','),
        [exam.allowed_file_types],
    );

    const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        const list = e.target.files;
        if (!list) return;

        const incoming = Array.from(list);
        const allowed = new Set(exam.allowed_file_types.map((s) => s.toLowerCase()));
        const maxBytes = exam.max_file_size_mb * 1024 * 1024;

        const valid: File[] = [];
        for (const file of incoming) {
            const ext = file.name.split('.').pop()?.toLowerCase() ?? '';
            if (!allowed.has(ext)) {
                toast.error(`${file.name}: tipe .${ext} tidak diizinkan`);
                continue;
            }
            if (file.size > maxBytes) {
                toast.error(`${file.name}: melebihi ${exam.max_file_size_mb} MB`);
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
        if (submitDisabled) return;

        post(route('student.exams.submission.submit', { material: material.id, exam: exam.id }), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset('files');
                setRemovedExisting(new Set());
                setEditing(false);
                router.reload({ only: ['submission', 'exam', 'activities'] });
            },
            onError: (formErrors) => {
                const firstError = Object.values(formErrors)[0];
                if (firstError) toast.error(firstError as string);
            },
        });
    };

    const existingFiles = submission?.files ?? [];
    const isEdit = submission !== null;
    const showForm = !submission || editing;

    return (
        <StudentLayout title={exam.title}>
            <Head title={exam.title} />

            <motion.div
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3, ease: [0.22, 1, 0.36, 1] }}
                className="mb-6"
            >
                <Link
                    href={route('student.materials.show', { course: course.id, material: material.id })}
                    className="mb-4 inline-flex items-center gap-1.5 text-sm text-slate-500 transition-colors hover:text-slate-900"
                >
                    <ArrowLeft className="h-4 w-4" />
                    {material.title}
                </Link>

                <div className="flex items-start gap-4">
                    <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-50 text-violet-600">
                        <FileSpreadsheet className="h-6 w-6" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="text-xs font-medium uppercase tracking-wide text-violet-600">Ujian · Submission</div>
                        <h1 className="mt-0.5 text-2xl font-semibold tracking-tight text-slate-900">{exam.title}</h1>
                        <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-500">
                            {course.classroom_name && <span>{course.classroom_name}</span>}
                            {course.subject_name && (
                                <>
                                    <span aria-hidden>•</span>
                                    <span>{course.subject_name}</span>
                                </>
                            )}
                            {course.teacher_name && (
                                <>
                                    <span aria-hidden>•</span>
                                    <span>{course.teacher_name}</span>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </motion.div>

            <motion.div
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3, delay: 0.05 }}
                className={`mb-6 flex items-start gap-3 rounded-2xl border p-4 ${tone.bg} ${tone.border}`}
            >
                <StatusIcon className={`mt-0.5 h-5 w-5 shrink-0 ${tone.icon}`} />
                <div className="flex-1">
                    <div className={`text-sm font-semibold ${tone.text}`}>{status.label}</div>
                    {exam.status === 'graded' && submission?.score !== null && submission?.score !== undefined && (
                        <div className={`mt-0.5 text-sm ${tone.text}`}>
                            Nilai: <span className="font-semibold">{submission.score}</span>
                            {exam.max_score != null && <span className="opacity-70"> / {exam.max_score}</span>}
                        </div>
                    )}
                    {submission?.submitted_at && (
                        <div className={`mt-0.5 text-xs opacity-75 ${tone.text}`}>
                            Dikumpulkan: {formatDateTime(submission.submitted_at)}
                        </div>
                    )}
                </div>
            </motion.div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    {exam.description && (
                        <section>
                            <h2 className="mb-3 text-sm font-semibold tracking-tight text-slate-900">Petunjuk</h2>
                            <div className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                                <MathContent
                                    html={exam.description}
                                    className="prose prose-slate max-w-none prose-headings:font-semibold prose-headings:tracking-tight prose-a:text-sky-600"
                                />
                            </div>
                        </section>
                    )}

                    <section>
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="text-sm font-semibold tracking-tight text-slate-900">
                                {showForm ? (submission ? 'Edit Submission' : 'Kerjakan Ujian') : 'Submission Kamu'}
                            </h2>
                            {showForm && submission && (
                                <button
                                    type="button"
                                    onClick={() => setEditing(false)}
                                    className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:border-slate-300 hover:text-slate-900"
                                >
                                    <X className="h-3.5 w-3.5" />
                                    Batal edit
                                </button>
                            )}
                        </div>

                        <div className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                            {submitDisabled && !submission && (
                                <div className="mb-4 flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">
                                    <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                                    <span>
                                        {isGraded
                                            ? 'Ujian ini sudah dinilai, tidak bisa kumpul ulang.'
                                            : 'Window pengumpulan sudah ditutup.'}
                                    </span>
                                </div>
                            )}

                            {showForm ? (
                                <form onSubmit={handleSubmit} className="space-y-5">
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium text-slate-700">
                                            Jawaban / Esai
                                        </label>
                                        <TrixEditor
                                            value={data.content}
                                            onChange={(html) => setData('content', html)}
                                            disabled={submitDisabled || processing}
                                            placeholder="Tulis jawabanmu di sini."
                                            aria-label="Jawaban ujian"
                                            minHeightClass="min-h-[200px]"
                                        />
                                        {errors.content && <p className="mt-1 text-xs text-rose-600">{errors.content}</p>}
                                    </div>

                                    <div>
                                        <label htmlFor="link_url" className="mb-1.5 block text-sm font-medium text-slate-700">
                                            Tautan referensi (opsional)
                                        </label>
                                        <div className="relative">
                                            <LinkIcon className="absolute left-3 top-3 h-4 w-4 text-slate-400" />
                                            <input
                                                id="link_url"
                                                type="url"
                                                value={data.link_url}
                                                onChange={(e) => setData('link_url', e.target.value)}
                                                disabled={submitDisabled || processing}
                                                placeholder="https://..."
                                                className="w-full rounded-xl border border-slate-200 bg-white py-3 pl-9 pr-4 text-sm text-slate-900 placeholder:text-slate-400 focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100 disabled:bg-slate-50"
                                            />
                                        </div>
                                        {errors.link_url && <p className="mt-1 text-xs text-rose-600">{errors.link_url}</p>}
                                    </div>

                                    <div>
                                        <div className="mb-1.5 flex items-center justify-between">
                                            <label className="block text-sm font-medium text-slate-700">Lampiran</label>
                                            <span className="text-xs text-slate-400">
                                                {exam.allowed_file_types.join(', ')} · maks {exam.max_file_size_mb} MB
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
                                                                    {isRemoved && (
                                                                        <span className="ml-2 text-rose-600">akan dihapus</span>
                                                                    )}
                                                                </div>
                                                            </div>
                                                            {!submitDisabled && (
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
                                                        className="flex items-center gap-3 rounded-xl border border-violet-200 bg-violet-50/30 px-3 py-2.5"
                                                    >
                                                        <FileText className="h-4 w-4 shrink-0 text-violet-500" />
                                                        <div className="min-w-0 flex-1">
                                                            <div className="truncate text-sm text-slate-900">{file.name}</div>
                                                            <div className="text-xs text-violet-700">
                                                                {file.name.split('.').pop()?.toUpperCase()} · {formatBytes(file.size)} · baru
                                                            </div>
                                                        </div>
                                                        {!submitDisabled && (
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

                                        {!submitDisabled && (
                                            <motion.button
                                                type="button"
                                                onClick={() => fileInputRef.current?.click()}
                                                whileTap={{ scale: 0.98 }}
                                                className="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-4 text-sm font-medium text-slate-600 transition-colors hover:border-violet-400 hover:text-violet-600"
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
                                            aria-label="Pilih lampiran ujian"
                                            className="hidden"
                                        />
                                        {errors.files && <p className="mt-1 text-xs text-rose-600">{errors.files}</p>}
                                    </div>

                                    {!submitDisabled && (
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
                                                className="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-70"
                                            >
                                                {processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                                                {isEdit ? 'Update Submission' : 'Kumpulkan Ujian'}
                                            </motion.button>
                                        </div>
                                    )}
                                </form>
                            ) : (
                                <SubmissionPreview
                                    submission={submission!}
                                    canEdit={!submitDisabled}
                                    onEdit={() => setEditing(true)}
                                />
                            )}
                        </div>
                    </section>

                    {submission?.feedback && (
                        <section>
                            <h2 className="mb-3 flex items-center gap-2 text-sm font-semibold tracking-tight text-slate-900">
                                <MessageSquare className="h-4 w-4 text-slate-500" />
                                Feedback Guru
                            </h2>
                            <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-700">
                                {submission.feedback}
                            </div>
                        </section>
                    )}
                </div>

                <aside className="space-y-4">
                    {exam.available_until && (
                        <div className="rounded-2xl border border-slate-200 bg-white p-5">
                            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <CalendarClock className="h-3.5 w-3.5" />
                                Window Pengumpulan
                            </div>
                            <div className="mt-2 text-sm font-medium text-slate-900">
                                Sampai {formatDateTime(exam.available_until)}
                            </div>
                        </div>
                    )}

                    {exam.max_score != null && (
                        <div className="rounded-2xl border border-slate-200 bg-white p-5">
                            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <Target className="h-3.5 w-3.5" />
                                Nilai Maksimal
                            </div>
                            <div className="mt-2 text-sm font-medium text-slate-900">{exam.max_score}</div>
                        </div>
                    )}

                    <div className="rounded-2xl border border-slate-200 bg-white p-5">
                        <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <Paperclip className="h-3.5 w-3.5" />
                            Aturan File
                        </div>
                        <div className="mt-2 space-y-1 text-sm text-slate-700">
                            <div>
                                Format: <span className="font-medium">{exam.allowed_file_types.join(', ')}</span>
                            </div>
                            <div>
                                Ukuran maks: <span className="font-medium">{exam.max_file_size_mb} MB</span>
                            </div>
                        </div>
                    </div>

                    <ActivityTimeline activities={activities} />
                </aside>
            </div>
        </StudentLayout>
    );
}

function SubmissionPreview({
    submission,
    canEdit,
    onEdit,
}: {
    submission: NonNullable<ExamSubmissionFormPageProps['submission']>;
    canEdit: boolean;
    onEdit: () => void;
}) {
    return (
        <div className="space-y-4">
            {submission.content && (
                <div>
                    <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">Jawaban</div>
                    <div className="mt-2 rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-800">
                        <MathContent
                            html={submission.content}
                            className="prose prose-sm prose-slate max-w-none prose-p:my-2"
                        />
                    </div>
                </div>
            )}

            {submission.link_url && (
                <div>
                    <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">Tautan</div>
                    <a
                        href={submission.link_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-sky-600 hover:text-sky-700"
                    >
                        <LinkIcon className="h-3.5 w-3.5" />
                        {submission.link_url}
                    </a>
                </div>
            )}

            {submission.files.length > 0 && (
                <div>
                    <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Lampiran ({submission.files.length})
                    </div>
                    <ul className="mt-2 space-y-2">
                        {submission.files.map((file) => (
                            <li
                                key={file.id}
                                className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5"
                            >
                                <FileText className="h-4 w-4 shrink-0 text-slate-400" />
                                <div className="min-w-0 flex-1">
                                    <div className="truncate text-sm text-slate-900">{file.name}</div>
                                    <div className="text-xs text-slate-500">
                                        {file.extension?.toUpperCase()} · {formatBytes(file.size)}
                                    </div>
                                </div>
                                <a
                                    href={file.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-xs font-medium text-sky-600 hover:text-sky-700"
                                >
                                    Unduh
                                </a>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {canEdit && (
                <div className="border-t border-slate-100 pt-4">
                    <button
                        type="button"
                        onClick={onEdit}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300"
                    >
                        Edit Submission
                    </button>
                </div>
            )}
        </div>
    );
}
