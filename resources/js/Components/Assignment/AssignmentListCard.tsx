import { Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { CheckCircle2, ChevronRight, ClipboardList, Clock, LucideIcon, XCircle } from 'lucide-react';
import { fadeUp } from '@/lib/motion';
import type { AssignmentListItem, AssignmentStatus } from './assignment.type';

interface Props {
    materialId: string;
    assignment: AssignmentListItem;
}

type StatusStyle = {
    label: string;
    pillBg: string;
    pillText: string;
    icon: LucideIcon;
};

const STATUS_STYLES: Record<AssignmentStatus, StatusStyle> = {
    pending: {
        label: 'Belum dikumpulkan',
        pillBg: 'bg-amber-50',
        pillText: 'text-amber-700',
        icon: Clock,
    },
    overdue: {
        label: 'Terlambat',
        pillBg: 'bg-rose-50',
        pillText: 'text-rose-700',
        icon: XCircle,
    },
    submitted: {
        label: 'Menunggu penilaian',
        pillBg: 'bg-sky-50',
        pillText: 'text-sky-700',
        icon: CheckCircle2,
    },
    graded: {
        label: 'Sudah dinilai',
        pillBg: 'bg-emerald-50',
        pillText: 'text-emerald-700',
        icon: CheckCircle2,
    },
};

function formatDeadline(iso: string | null): { label: string; tone: 'normal' | 'warning' | 'danger' } | null {
    if (!iso) return null;
    const date = new Date(iso);
    const now = new Date();
    const diffMs = date.getTime() - now.getTime();
    const diffHr = diffMs / (1000 * 60 * 60);

    const formatted = date.toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

    if (diffMs < 0) return { label: `Lewat sejak ${formatted}`, tone: 'danger' };
    if (diffHr < 24) return { label: `Tenggat hari ini · ${formatted}`, tone: 'warning' };
    return { label: `Tenggat ${formatted}`, tone: 'normal' };
}

export default function AssignmentListCard({ materialId, assignment }: Props) {
    const status = STATUS_STYLES[assignment.status];
    const StatusIcon = status.icon;
    const deadline = formatDeadline(assignment.deadline);

    const deadlineTone =
        deadline?.tone === 'danger'
            ? 'text-rose-600'
            : deadline?.tone === 'warning'
              ? 'text-amber-600'
              : 'text-slate-500';

    return (
        <motion.div variants={fadeUp}>
            <Link
                href={route('student.assignments.show', { material: materialId, assignment: assignment.id })}
                className="group flex items-start gap-4 rounded-2xl border border-slate-200 bg-white p-5 transition-colors hover:border-amber-300"
            >
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <ClipboardList className="h-5 w-5" />
                </div>

                <div className="min-w-0 flex-1">
                    <h3 className="truncate text-base font-semibold tracking-tight text-slate-900">
                        {assignment.title}
                    </h3>
                    {assignment.description && (
                        <p className="mt-1 line-clamp-2 text-sm text-slate-500">{assignment.description}</p>
                    )}

                    <div className="mt-3 flex flex-wrap items-center gap-2 text-xs">
                        <span className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 font-medium ${status.pillBg} ${status.pillText}`}>
                            <StatusIcon className="h-3 w-3" />
                            {status.label}
                            {assignment.status === 'graded' && assignment.score !== null && (
                                <span className="ml-1 font-semibold">
                                    {assignment.score}
                                    {assignment.max_score != null && ` / ${assignment.max_score}`}
                                </span>
                            )}
                        </span>
                        {deadline && <span className={deadlineTone}>{deadline.label}</span>}
                    </div>
                </div>

                <span className="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-300 transition-colors group-hover:bg-slate-100 group-hover:text-slate-600">
                    <ChevronRight className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                </span>
            </Link>
        </motion.div>
    );
}
