import { Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    CheckCircle2,
    ChevronRight,
    Clock,
    FileSpreadsheet,
    LucideIcon,
    PlayCircle,
    Timer,
} from 'lucide-react';
import { fadeUp } from '@/lib/motion';
import type { ExamListItem, ExamStatus } from './exam.type';

interface Props {
    materialId: string;
    exam: ExamListItem;
}

type StatusStyle = {
    label: string;
    pillBg: string;
    pillText: string;
    icon: LucideIcon;
};

const STATUS_STYLES: Record<ExamStatus, StatusStyle> = {
    belum_mulai: {
        label: 'Belum dikerjakan',
        pillBg: 'bg-violet-50',
        pillText: 'text-violet-700',
        icon: PlayCircle,
    },
    in_progress: {
        label: 'Sedang dikerjakan',
        pillBg: 'bg-sky-50',
        pillText: 'text-sky-700',
        icon: Timer,
    },
    submitted: {
        label: 'Menunggu penilaian',
        pillBg: 'bg-amber-50',
        pillText: 'text-amber-700',
        icon: Clock,
    },
    graded: {
        label: 'Sudah dinilai',
        pillBg: 'bg-emerald-50',
        pillText: 'text-emerald-700',
        icon: CheckCircle2,
    },
};

const CTA_LABEL: Record<ExamStatus, string> = {
    belum_mulai: 'Mulai Ujian',
    in_progress: 'Lanjutkan',
    submitted: 'Lihat Hasil',
    graded: 'Lihat Hasil',
};

function formatDateTime(iso: string | null): string | null {
    if (!iso) return null;
    return new Date(iso).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function ExamListCard({ materialId, exam }: Props) {
    const status = STATUS_STYLES[exam.status];
    const StatusIcon = status.icon;
    const startsAt = formatDateTime(exam.starts_at);
    const modeLabel = exam.mode === 'online_quiz' ? 'Kuis online' : 'Kumpul jawaban';

    return (
        <motion.div variants={fadeUp}>
            <Link
                href={route('student.exams.show', { material: materialId, exam: exam.id })}
                className="group flex items-start gap-4 rounded-2xl border border-slate-200 bg-white p-5 transition-colors hover:border-violet-300"
            >
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                    <FileSpreadsheet className="h-5 w-5" />
                </div>

                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                        <h3 className="truncate text-base font-semibold tracking-tight text-slate-900">
                            {exam.title}
                        </h3>
                        <span className="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-500">
                            {modeLabel}
                        </span>
                    </div>
                    {exam.description && (
                        <p className="mt-1 line-clamp-2 text-sm text-slate-500">{exam.description}</p>
                    )}

                    <div className="mt-3 flex flex-wrap items-center gap-2 text-xs">
                        <span
                            className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 font-medium ${status.pillBg} ${status.pillText}`}
                        >
                            <StatusIcon className="h-3 w-3" />
                            {status.label}
                            {exam.status === 'graded' && exam.total_score !== null && (
                                <span className="ml-1 font-semibold">
                                    {exam.total_score}
                                    {exam.max_score != null && ` / ${exam.max_score}`}
                                </span>
                            )}
                        </span>
                        {exam.mode === 'online_quiz' && (
                            <span className="text-slate-500">
                                {exam.duration_minutes} menit · {exam.questions_count} soal
                            </span>
                        )}
                        {exam.mode === 'submission' && startsAt && (
                            <span className="text-slate-500">Mulai {startsAt}</span>
                        )}
                    </div>

                    <div className="mt-3 text-xs font-medium text-violet-700 group-hover:text-violet-800">
                        {CTA_LABEL[exam.status]} →
                    </div>
                </div>

                <span className="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-300 transition-colors group-hover:bg-slate-100 group-hover:text-slate-600">
                    <ChevronRight className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                </span>
            </Link>
        </motion.div>
    );
}
