import { router } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { AlarmClock, ClipboardList, FileSpreadsheet } from 'lucide-react';
import { SharedTodoItem } from '@/types';
import { fadeUp, staggerContainer } from '@/lib';

interface Props {
    items: SharedTodoItem[];
    emptyMessage?: string;
}

const STATE_STYLES: Record<string, { ring: string; chip: string; chipText: string; label: string }> = {
    overdue: {
        ring: 'border-red-200 bg-red-50/40',
        chip: 'bg-red-100',
        chipText: 'text-red-700',
        label: 'Terlambat',
    },
    pending: {
        ring: 'border-slate-200 bg-white',
        chip: 'bg-sky-100',
        chipText: 'text-sky-700',
        label: 'Belum dikumpul',
    },
    upcoming: {
        ring: 'border-slate-200 bg-white',
        chip: 'bg-amber-100',
        chipText: 'text-amber-700',
        label: 'Akan datang',
    },
    available: {
        ring: 'border-amber-200 bg-amber-50/40',
        chip: 'bg-amber-200',
        chipText: 'text-amber-800',
        label: 'Tersedia sekarang',
    },
};

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function TodoSection({ items, emptyMessage }: Props) {
    if (items.length === 0) {
        return (
            <div className="rounded-2xl border border-dashed border-slate-200 bg-white p-8 text-center">
                <AlarmClock className="mx-auto h-8 w-8 text-slate-300" />
                <p className="mt-2 text-sm text-slate-500">
                    {emptyMessage ?? 'Tidak ada tugas atau ujian yang perlu dikerjakan saat ini.'}
                </p>
            </div>
        );
    }

    return (
        <motion.ul
            initial="hidden"
            animate="visible"
            variants={staggerContainer}
            className="grid grid-cols-1 gap-3 sm:grid-cols-2"
        >
            {items.map((item) => {
                const styles = STATE_STYLES[item.state];
                const Icon = item.kind === 'assignment' ? ClipboardList : FileSpreadsheet;
                const subline =
                    item.kind === 'assignment'
                        ? `Deadline: ${formatDate(item.deadline)}`
                        : item.state === 'available'
                          ? `Tersedia sampai: ${formatDate(item.available_until)}`
                          : `Mulai: ${formatDate(item.starts_at)}`;

                return (
                    <motion.li key={`${item.kind}-${item.id}`} variants={fadeUp}>
                        <button
                            type="button"
                            disabled={!item.url}
                            onClick={() => item.url && router.visit(item.url)}
                            className={`flex w-full items-start gap-3 rounded-2xl border p-4 text-left transition hover:border-slate-300 disabled:cursor-not-allowed disabled:opacity-60 ${styles.ring}`}
                        >
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                                <Icon className="h-4 w-4" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                    <span
                                        className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${styles.chip} ${styles.chipText}`}
                                    >
                                        {styles.label}
                                    </span>
                                    {item.subject_name && (
                                        <span className="truncate text-[11px] text-slate-500">
                                            {item.subject_name}
                                        </span>
                                    )}
                                </div>
                                <h3 className="mt-1.5 truncate text-sm font-semibold text-slate-900">
                                    {item.title}
                                </h3>
                                <p className="mt-0.5 truncate text-xs text-slate-500">{subline}</p>
                            </div>
                        </button>
                    </motion.li>
                );
            })}
        </motion.ul>
    );
}
