import { Head, Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    ArrowLeft,
    Award,
    CheckCircle2,
    Clock,
    FileSpreadsheet,
    Hash,
    LucideIcon,
    Sparkles,
} from 'lucide-react';
import type { ExamResultPageProps } from '@/Components/Exam';
import { StudentLayout } from '@/Layouts';
import { PageProps } from '@/types';

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

export default function ExamResult() {
    const { props } = usePage<PageProps<ExamResultPageProps>>();
    const { course, material, exam, session, questions, answers } = props;

    const answered = questions.filter((q) => {
        const v = answers[q.id];

        return typeof v === 'string' && v.trim().length > 0;
    }).length;

    const submittedAt = formatDateTime(session.submitted_at);
    // Session.total_score belum tentu final — kalau ada essay yang belum dinilai,
    // ini hanya skor parsial. Kita lihat dari frontend dengan flag heuristic:
    // total_score === null → belum dihitung sama sekali (mis. tidak ada MC/short).
    // total_score !== null & ada essay → "menunggu penilaian guru untuk soal essay".

    const hasEssay = questions.some((q) => q.type === 'essay');
    const totalScore = session.total_score ?? null;

    return (
        <StudentLayout title={`Hasil ${exam.title}`}>
            <Head title={`Hasil ${exam.title}`} />

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
                    <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                        <CheckCircle2 className="h-6 w-6" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="text-xs font-medium uppercase tracking-wide text-emerald-600">
                            Hasil Ujian
                        </div>
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
                            {submittedAt && (
                                <>
                                    <span aria-hidden>•</span>
                                    <span>Dikumpulkan {submittedAt}</span>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </motion.div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="space-y-4 lg:col-span-2">
                    <motion.div
                        initial={{ opacity: 0, y: 8 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.3, delay: 0.05 }}
                        className="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-6"
                    >
                        <div className="flex items-center gap-2 text-sm font-medium text-emerald-700">
                            <Sparkles className="h-4 w-4" />
                            Skor sementara
                        </div>
                        <div className="mt-2 flex items-baseline gap-2">
                            <div className="text-4xl font-semibold tracking-tight text-emerald-900">
                                {totalScore !== null ? totalScore : '—'}
                            </div>
                            {exam.max_score != null && (
                                <div className="text-sm text-emerald-700">/ {exam.max_score}</div>
                            )}
                        </div>
                        {hasEssay && (
                            <p className="mt-3 text-sm text-emerald-800">
                                Beberapa soal berupa esai — guru masih perlu menilai secara manual.
                                Skor akhir akan diperbarui setelah penilaian selesai.
                            </p>
                        )}
                    </motion.div>

                    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                        <h2 className="text-sm font-semibold tracking-tight text-slate-900">Ringkasan Pengerjaan</h2>
                        <ul className="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-3">
                            <SummaryRow icon={Hash} label="Soal dijawab" value={`${answered} / ${questions.length}`} />
                            <SummaryRow icon={Clock} label="Durasi ujian" value={`${exam.duration_minutes} menit`} />
                            <SummaryRow icon={Award} label="Mode" value={exam.mode === 'online_quiz' ? 'Kuis Online' : 'Submission'} />
                        </ul>
                        <p className="mt-4 text-xs text-slate-500">
                            Jawaban benar tidak ditampilkan di sini agar siswa lain mendapatkan kesempatan
                            ujian yang sama. Tanyakan langsung ke guru kalau kamu ingin bahas pembahasan.
                        </p>
                    </section>

                    <div className="flex justify-end">
                        <Link
                            href={route('student.materials.show', { course: course.id, material: material.id })}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Kembali ke Materi
                        </Link>
                    </div>
                </div>

                <aside>
                    <div className="rounded-2xl border border-slate-200 bg-white p-5">
                        <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <FileSpreadsheet className="h-3.5 w-3.5" />
                            Detail Sesi
                        </div>
                        <dl className="mt-3 space-y-2 text-sm">
                            <Row label="Dimulai" value={formatDateTime(session.started_at)} />
                            <Row label="Dikumpulkan" value={formatDateTime(session.submitted_at)} />
                            <Row label="Total soal" value={`${questions.length}`} />
                            <Row label="Terjawab" value={`${answered}`} />
                        </dl>
                    </div>
                </aside>
            </div>
        </StudentLayout>
    );
}

function SummaryRow({ icon: Icon, label, value }: { icon: LucideIcon; label: string; value: string }) {
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

function Row({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="flex items-baseline justify-between gap-3">
            <dt className="text-xs uppercase tracking-wide text-slate-500">{label}</dt>
            <dd className="text-right text-sm font-medium text-slate-900">{value ?? '—'}</dd>
        </div>
    );
}
