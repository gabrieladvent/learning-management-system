import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle2,
    ChevronRight,
    Clock,
    FileSpreadsheet,
    Hash,
    Loader2,
    LucideIcon,
    PlayCircle,
    Target,
} from 'lucide-react';
import { useState } from 'react';
import { ActivityTimeline, MathContent } from '@/Components';
import type { ExamStartPageProps, ExamStatus } from '@/Components/Exam';
import { StudentLayout } from '@/Layouts';
import { toast } from '@/lib';
import { PageProps } from '@/types';

const STATUS_BANNER: Record<ExamStatus, { label: string; tone: 'violet' | 'sky' | 'amber' | 'emerald'; icon: LucideIcon }> = {
    belum_mulai: { label: 'Siap dikerjakan', tone: 'violet', icon: PlayCircle },
    in_progress: { label: 'Sedang dikerjakan', tone: 'sky', icon: Clock },
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

export default function ExamStart() {
    const { props } = usePage<PageProps<ExamStartPageProps>>();
    const { course, material, exam, session, activities } = props;
    const [starting, setStarting] = useState(false);

    const status = STATUS_BANNER[exam.status];
    const tone = TONE_CLASS[status.tone];
    const StatusIcon = status.icon;

    const isFinished = exam.status === 'submitted' || exam.status === 'graded';

    const ctaLabel = (() => {
        if (isFinished) return 'Lihat Hasil';
        if (exam.status === 'in_progress') return 'Lanjutkan Ujian';

        return 'Mulai Ujian';
    })();

    const handleAction = () => {
        if (isFinished && session) {
            router.visit(route('student.exams.result', { session: session.id }));

            return;
        }

        if (exam.status === 'in_progress' && session) {
            router.visit(route('student.exams.take', { session: session.id }));

            return;
        }

        setStarting(true);
        router.post(
            route('student.exams.start', { material: material.id, exam: exam.id }),
            {},
            {
                preserveScroll: true,
                onError: (errs) => {
                    const first = Object.values(errs)[0];
                    if (first) toast.error(first as string);
                    setStarting(false);
                },
                onFinish: () => setStarting(false),
            },
        );
    };

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
                        <div className="text-xs font-medium uppercase tracking-wide text-violet-600">Ujian</div>
                        <h1 className="mt-0.5 text-2xl font-semibold tracking-tight text-slate-900">
                            {exam.title}
                        </h1>
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
                transition={{ duration: 0.3, delay: 0.05, ease: [0.22, 1, 0.36, 1] }}
                className={`mb-6 flex items-start gap-3 rounded-2xl border p-4 ${tone.bg} ${tone.border}`}
            >
                <StatusIcon className={`mt-0.5 h-5 w-5 shrink-0 ${tone.icon}`} />
                <div className="flex-1">
                    <div className={`text-sm font-semibold ${tone.text}`}>{status.label}</div>
                    {exam.status === 'graded' && session?.total_score !== null && session?.total_score !== undefined && (
                        <div className={`mt-0.5 text-sm ${tone.text}`}>
                            Skor: <span className="font-semibold">{session.total_score}</span>
                            {exam.max_score != null && <span className="opacity-70"> / {exam.max_score}</span>}
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
                        <h2 className="mb-3 text-sm font-semibold tracking-tight text-slate-900">Ringkasan</h2>
                        <div className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                            <ul className="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                                <InfoRow icon={Clock} label="Durasi" value={`${exam.duration_minutes} menit`} />
                                <InfoRow icon={Hash} label="Jumlah soal" value={`${exam.questions_count} soal`} />
                                <InfoRow
                                    icon={Target}
                                    label="Nilai maksimal"
                                    value={exam.max_score != null ? `${exam.max_score}` : '—'}
                                />
                                <InfoRow
                                    icon={PlayCircle}
                                    label="Waktu mulai"
                                    value={formatDateTime(exam.starts_at) ?? 'Sekarang'}
                                />
                            </ul>
                            {exam.shuffle_questions && (
                                <p className="mt-4 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                                    <AlertCircle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                    Urutan soal di ujian ini diacak — jawab dengan teliti, kamu tidak bisa
                                    kembali ke urutan asli.
                                </p>
                            )}
                            <p className="mt-3 text-xs text-slate-500">
                                Jawaban kamu disimpan otomatis. Kalau koneksi terputus atau halaman tertutup,
                                kamu bisa lanjut dari soal terakhir selama waktu masih tersisa.
                            </p>
                        </div>
                    </section>

                    <div className="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:justify-end">
                        <motion.button
                            type="button"
                            onClick={handleAction}
                            disabled={starting}
                            whileTap={{ scale: 0.97 }}
                            className="inline-flex items-center justify-center gap-2 rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-70"
                        >
                            {starting ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <ChevronRight className="h-4 w-4" />
                            )}
                            {ctaLabel}
                        </motion.button>
                    </div>
                </div>

                <aside className="space-y-4">
                    <ActivityTimeline activities={activities} />
                </aside>
            </div>
        </StudentLayout>
    );
}

function InfoRow({ icon: Icon, label, value }: { icon: LucideIcon; label: string; value: string }) {
    return (
        <li className="flex items-start gap-3">
            <div className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                <Icon className="h-4 w-4" />
            </div>
            <div className="min-w-0">
                <div className="text-xs uppercase tracking-wide text-slate-500">{label}</div>
                <div className="mt-0.5 text-sm font-medium text-slate-900">{value}</div>
            </div>
        </li>
    );
}
