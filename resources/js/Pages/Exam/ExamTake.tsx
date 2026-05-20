import { Head, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { ChevronLeft, ChevronRight, FileSpreadsheet, Loader2, Save, Send } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { FileCard, MathContent, TrixEditor } from '@/Components';
import { ExamTimer, QuestionNavigator, useExamTimer } from '@/Components/Exam';
import type { ExamQuestionItem, ExamTakePageProps } from '@/Components/Exam';
import { StudentLayout } from '@/Layouts';
import { toast } from '@/lib';
import { PageProps } from '@/types';

type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';

const DEBOUNCE_MS = 800;

export default function ExamTake() {
    const { props } = usePage<PageProps<ExamTakePageProps>>();
    const { course, material, exam, session, questions, answers: initialAnswers, server_time } = props;

    const [currentIndex, setCurrentIndex] = useState(0);
    const [answers, setAnswers] = useState<Record<string, string | null>>(() => ({ ...initialAnswers }));
    const [saveStatus, setSaveStatus] = useState<SaveStatus>('idle');
    const [submitting, setSubmitting] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);

    const expiredHandledRef = useRef(false);
    const pendingTimersRef = useRef<Record<string, ReturnType<typeof setTimeout>>>({});

    const submitSession = useCallback(
        (auto: boolean) => {
            if (submitting) return;
            setSubmitting(true);

            // Pastikan semua pending auto-save selesai sebelum submit final.
            Object.values(pendingTimersRef.current).forEach((id) => clearTimeout(id));
            pendingTimersRef.current = {};

            router.post(
                route('student.exams.submit', { session: session.id }),
                {},
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        if (auto) toast.info('Waktu habis — ujian otomatis dikumpulkan.');
                    },
                    onError: (errs) => {
                        const first = Object.values(errs)[0];
                        if (first) toast.error(first as string);
                    },
                    onFinish: () => setSubmitting(false),
                },
            );
        },
        [session.id, submitting],
    );

    const handleExpire = useCallback(() => {
        if (expiredHandledRef.current) return;
        expiredHandledRef.current = true;
        submitSession(true);
    }, [submitSession]);

    const timer = useExamTimer({
        expiresAt: session.expires_at,
        serverTime: server_time,
        onExpire: handleExpire,
    });

    const totalQuestions = questions.length;
    const current = questions[currentIndex];
    const answeredCount = useMemo(
        () => Object.values(answers).filter((v) => typeof v === 'string' && v.trim().length > 0).length,
        [answers],
    );

    /**
     * Deteksi server rejection yang menandakan session sudah disubmit (mis. di tab
     * lain, atau auto-submit dari timer di backend lain). Saat itu terjadi, tidak
     * ada gunanya retry — kita redirect siswa ke halaman hasil supaya tidak stuck
     * mengulang save yang selalu gagal.
     */
    const handleSessionClosed = useCallback(
        (reason: string) => {
            toast.info(reason);
            // Stop semua pending save & redirect ke result.
            Object.values(pendingTimersRef.current).forEach((id) => clearTimeout(id));
            pendingTimersRef.current = {};
            router.visit(route('student.exams.result', { session: session.id }));
        },
        [session.id],
    );

    const persistAnswer = useCallback(
        (questionId: string, value: string | null) => {
            setSaveStatus('saving');
            window.axios
                .post(route('student.exams.answer', { session: session.id }), {
                    question_id: questionId,
                    answer: value,
                })
                .then(() => setSaveStatus('saved'))
                .catch((err) => {
                    setSaveStatus('error');
                    const sessionError = err?.response?.data?.errors?.session?.[0] as string | undefined;
                    const message =
                        sessionError ??
                        err?.response?.data?.message ??
                        'Gagal menyimpan jawaban — periksa koneksi.';

                    // Match pesan dari SaveExamAnswer.php:
                    //  - "Ujian sudah dikumpulkan, tidak bisa diubah lagi."
                    //  - "Waktu ujian sudah habis."
                    // Kedua kondisi = session closed, antar siswa ke hasil supaya tidak stuck.
                    if (sessionError && (sessionError.includes('dikumpulkan') || sessionError.includes('habis'))) {
                        handleSessionClosed(sessionError);

                        return;
                    }

                    toast.error(message);
                });
        },
        [session.id, handleSessionClosed],
    );

    const scheduleSave = useCallback(
        (questionId: string, value: string | null) => {
            const existing = pendingTimersRef.current[questionId];
            if (existing) clearTimeout(existing);

            pendingTimersRef.current[questionId] = setTimeout(() => {
                delete pendingTimersRef.current[questionId];
                persistAnswer(questionId, value);
            }, DEBOUNCE_MS);
        },
        [persistAnswer],
    );

    const handleAnswerChange = (questionId: string, value: string | null) => {
        setAnswers((prev) => ({ ...prev, [questionId]: value }));
        scheduleSave(questionId, value);
    };

    // Cleanup pending timers saat unmount.
    useEffect(() => {
        return () => {
            Object.values(pendingTimersRef.current).forEach((id) => clearTimeout(id));
            pendingTimersRef.current = {};
        };
    }, []);

    const goPrev = () => setCurrentIndex((i) => Math.max(0, i - 1));
    const goNext = () => setCurrentIndex((i) => Math.min(totalQuestions - 1, i + 1));

    return (
        <StudentLayout title={exam.title}>
            <Head title={exam.title} />

            <motion.div
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3, ease: [0.22, 1, 0.36, 1] }}
                className="mb-6 flex flex-wrap items-start justify-between gap-3"
            >
                <div className="flex items-start gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                        <FileSpreadsheet className="h-5 w-5" />
                    </div>
                    <div className="min-w-0">
                        <h1 className="text-xl font-semibold tracking-tight text-slate-900">{exam.title}</h1>
                        <div className="mt-1 text-xs text-slate-500">
                            {course.classroom_name && <span>{course.classroom_name}</span>}
                            {material.title && (
                                <>
                                    <span className="mx-1.5" aria-hidden>
                                        •
                                    </span>
                                    <span>{material.title}</span>
                                </>
                            )}
                        </div>
                    </div>
                </div>
                <ExamTimer
                    formatted={timer.formatted}
                    isCritical={timer.isCritical}
                    isExpired={timer.isExpired}
                />
            </motion.div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="space-y-4 lg:col-span-2">
                    {current && (
                        <QuestionCard
                            question={current}
                            index={currentIndex}
                            total={totalQuestions}
                            answer={answers[current.id] ?? ''}
                            onChange={(value) => handleAnswerChange(current.id, value)}
                            disabled={timer.isExpired || submitting}
                            saveStatus={saveStatus}
                        />
                    )}

                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4">
                        <button
                            type="button"
                            onClick={goPrev}
                            disabled={currentIndex === 0}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <ChevronLeft className="h-4 w-4" />
                            Sebelumnya
                        </button>
                        <div className="text-xs text-slate-500">
                            Soal {currentIndex + 1} dari {totalQuestions} · {answeredCount} terjawab
                        </div>
                        {currentIndex < totalQuestions - 1 ? (
                            <button
                                type="button"
                                onClick={goNext}
                                className="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                            >
                                Berikutnya
                                <ChevronRight className="h-4 w-4" />
                            </button>
                        ) : (
                            <button
                                type="button"
                                onClick={() => setShowConfirm(true)}
                                disabled={submitting}
                                className="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-70"
                            >
                                {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                                Selesaikan
                            </button>
                        )}
                    </div>
                </div>

                <aside className="space-y-4">
                    <QuestionNavigator
                        questions={questions}
                        answers={answers}
                        currentIndex={currentIndex}
                        onJump={(idx) => setCurrentIndex(idx)}
                    />
                    <button
                        type="button"
                        onClick={() => setShowConfirm(true)}
                        disabled={submitting}
                        className="inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-violet-600 px-3 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-70"
                    >
                        {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                        Selesaikan Ujian
                    </button>
                </aside>
            </div>

            {showConfirm && (
                <ConfirmSubmitModal
                    onCancel={() => setShowConfirm(false)}
                    onConfirm={() => {
                        setShowConfirm(false);
                        submitSession(false);
                    }}
                    answered={answeredCount}
                    total={totalQuestions}
                    submitting={submitting}
                />
            )}
        </StudentLayout>
    );
}

function QuestionCard({
    question,
    index,
    total,
    answer,
    onChange,
    disabled,
    saveStatus,
}: {
    question: ExamQuestionItem;
    index: number;
    total: number;
    answer: string;
    onChange: (value: string) => void;
    disabled: boolean;
    saveStatus: SaveStatus;
}) {
    const options = useMemo(() => normalizeOptions(question.options), [question.options]);

    return (
        <article className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <div className="mb-4 flex items-center justify-between">
                <div className="text-xs font-medium uppercase tracking-wide text-violet-600">
                    Soal {index + 1} dari {total}
                </div>
                <SaveBadge status={saveStatus} />
            </div>

            <div className="prose prose-slate max-w-none">
                <MathContent html={question.question} />
            </div>

            {question.files.length > 0 && (
                <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    {question.files.map((file) => (
                        <FileCard key={file.id} file={file} />
                    ))}
                </div>
            )}

            <div className="mt-5 space-y-2">
                {question.type === 'multiple_choice' && (
                    <fieldset className="space-y-2" disabled={disabled}>
                        <legend className="sr-only">Pilih jawaban</legend>
                        {options.map(({ key, text }) => {
                            const selected = answer === key;

                            return (
                                <label
                                    key={key}
                                    className={`flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition ${selected ? 'border-violet-500 bg-violet-50' : 'border-slate-200 bg-white hover:border-slate-300'} ${disabled ? 'cursor-not-allowed opacity-70' : ''}`}
                                >
                                    <input
                                        type="radio"
                                        name={`q-${question.id}`}
                                        value={key}
                                        checked={selected}
                                        onChange={(e) => onChange(e.target.value)}
                                        className="h-4 w-4 accent-violet-600"
                                    />
                                    <span
                                        className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-xs font-semibold ${selected ? 'bg-violet-600 text-white' : 'bg-slate-100 text-slate-600'}`}
                                    >
                                        {key}
                                    </span>
                                    <div className="prose prose-sm prose-slate min-w-0 flex-1 max-w-none text-sm text-slate-900 prose-p:my-0">
                                        <MathContent html={text} />
                                    </div>
                                </label>
                            );
                        })}
                    </fieldset>
                )}

                {question.type === 'short_answer' && (
                    <input
                        type="text"
                        value={answer}
                        onChange={(e) => onChange(e.target.value)}
                        disabled={disabled}
                        placeholder="Ketik jawabanmu di sini"
                        className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100 disabled:bg-slate-50"
                    />
                )}

                {question.type === 'essay' && (
                    <TrixEditor
                        value={answer}
                        onChange={onChange}
                        disabled={disabled}
                        placeholder="Tulis jawabanmu di sini."
                        aria-label="Jawaban esai"
                        minHeightClass="min-h-[200px]"
                    />
                )}
            </div>
        </article>
    );
}

function SaveBadge({ status }: { status: SaveStatus }) {
    if (status === 'idle') return null;

    const tone =
        status === 'error'
            ? 'bg-rose-50 text-rose-700'
            : status === 'saving'
              ? 'bg-slate-50 text-slate-600'
              : 'bg-emerald-50 text-emerald-700';

    return (
        <span className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-medium ${tone}`}>
            {status === 'saving' ? <Loader2 className="h-3 w-3 animate-spin" /> : <Save className="h-3 w-3" />}
            {status === 'saving' ? 'Menyimpan' : status === 'saved' ? 'Tersimpan' : 'Gagal menyimpan'}
        </span>
    );
}

function ConfirmSubmitModal({
    onCancel,
    onConfirm,
    answered,
    total,
    submitting,
}: {
    onCancel: () => void;
    onConfirm: () => void;
    answered: number;
    total: number;
    submitting: boolean;
}) {
    const unanswered = total - answered;

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
            role="dialog"
            aria-modal="true"
        >
            <motion.div
                initial={{ opacity: 0, scale: 0.95 }}
                animate={{ opacity: 1, scale: 1 }}
                transition={{ duration: 0.15 }}
                className="w-full max-w-md rounded-2xl bg-white p-6 shadow-lg"
            >
                <h2 className="text-base font-semibold tracking-tight text-slate-900">Selesaikan ujian?</h2>
                <p className="mt-1 text-sm text-slate-600">
                    Kamu telah menjawab <strong>{answered}</strong> dari {total} soal.
                    {unanswered > 0 && <> Masih ada {unanswered} soal yang belum dijawab.</>} Setelah dikirim,
                    kamu tidak bisa mengubah jawaban lagi.
                </p>
                <div className="mt-5 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        onClick={onCancel}
                        disabled={submitting}
                        className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={submitting}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-violet-700 disabled:opacity-70"
                    >
                        {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                        Kirim Sekarang
                    </button>
                </div>
            </motion.div>
        </div>
    );
}

/**
 * Normalize options ke list `{key, text}` — dari DB bisa berbentuk:
 *  - object {A: '...', B: '...'} (umum dari seeder/Filament)
 *  - array ['...', '...']
 */
function normalizeOptions(raw: ExamQuestionItem['options']): { key: string; text: string }[] {
    if (Array.isArray(raw)) {
        return raw.map((text, i) => ({ key: String.fromCharCode(65 + i), text }));
    }
    if (raw && typeof raw === 'object') {
        return Object.entries(raw as Record<string, string>).map(([key, text]) => ({ key, text }));
    }

    return [];
}
