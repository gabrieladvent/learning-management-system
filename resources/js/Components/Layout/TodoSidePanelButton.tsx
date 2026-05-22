import { router, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { ClipboardList, FileSpreadsheet, ListChecks, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { PageProps, SharedTodoItem } from '@/types';

const STATE_STYLES: Record<string, { border: string; chip: string; chipText: string; label: string }> = {
    overdue: {
        border: 'border-red-200 bg-red-50/40',
        chip: 'bg-red-100',
        chipText: 'text-red-700',
        label: 'Terlambat',
    },
    pending: {
        border: 'border-slate-200 bg-white',
        chip: 'bg-sky-100',
        chipText: 'text-sky-700',
        label: 'Belum dikumpul',
    },
    upcoming: {
        border: 'border-slate-200 bg-white',
        chip: 'bg-amber-100',
        chipText: 'text-amber-700',
        label: 'Akan datang',
    },
    available: {
        border: 'border-amber-200 bg-amber-50/40',
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
        hour: '2-digit',
        minute: '2-digit',
    });
}

function sublineFor(item: SharedTodoItem): string {
    if (item.kind === 'assignment') {
        return item.deadline ? `Deadline: ${formatDate(item.deadline)}` : 'Tanpa batas waktu';
    }
    if (item.state === 'available') {
        return `Tersedia sampai: ${formatDate(item.available_until)}`;
    }
    return `Mulai: ${formatDate(item.starts_at)}`;
}

export default function TodoSidePanelButton() {
    const { props } = usePage<PageProps>();
    const todo = props.todo;
    const thisWeek = todo?.this_week ?? [];
    const later = todo?.later ?? [];
    const count = todo?.count_this_week ?? 0;

    const [open, setOpen] = useState(false);

    useEffect(() => {
        function onKey(e: KeyboardEvent) {
            if (e.key === 'Escape') setOpen(false);
        }
        if (open) window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [open]);

    useEffect(() => {
        if (open) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
        return () => {
            document.body.style.overflow = '';
        };
    }, [open]);

    function handleItemClick(item: SharedTodoItem) {
        if (!item.url) return;
        setOpen(false);
        router.visit(item.url);
    }

    const totalAll = thisWeek.length + later.length;

    const panel = (
        <AnimatePresence>
            {open && (
                <>
                    <motion.div
                        key="todo-backdrop"
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        transition={{ duration: 0.2 }}
                        onClick={() => setOpen(false)}
                        className="fixed inset-0 z-[100] bg-slate-900/30 backdrop-blur-sm"
                    />

                    <motion.aside
                        key="todo-panel"
                        initial={{ x: '100%' }}
                        animate={{ x: 0 }}
                        exit={{ x: '100%' }}
                        transition={{ duration: 0.28, ease: [0.22, 1, 0.36, 1] }}
                        className="fixed inset-y-0 right-0 z-[101] flex w-full max-w-md flex-col border-l border-slate-200 bg-white shadow-2xl"
                        role="dialog"
                        aria-label="Daftar To-Do"
                    >
                        <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                            <div>
                                <h2 className="text-base font-semibold text-slate-900">To-Do</h2>
                                <p className="text-xs text-slate-500">
                                    {totalAll > 0
                                        ? `${totalAll} item · ${count} minggu ini`
                                        : 'Tidak ada tugas atau ujian aktif'}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setOpen(false)}
                                className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                                aria-label="Tutup"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>

                        <div className="flex-1 overflow-y-auto bg-white px-4 py-4">
                            {totalAll === 0 ? (
                                <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center text-sm text-slate-500">
                                    Tidak ada tugas atau ujian aktif.
                                </div>
                            ) : (
                                <div className="space-y-6">
                                    <TodoGroup
                                        label="1 Minggu ke Depan"
                                        description={`${thisWeek.length} item`}
                                        items={thisWeek}
                                        emptyText="Tidak ada item untuk minggu ini."
                                        onItemClick={handleItemClick}
                                    />

                                    <TodoGroup
                                        label="Tugas Lainnya"
                                        description={`${later.length} item`}
                                        items={later}
                                        emptyText="Tidak ada tugas lain di luar minggu ini."
                                        onItemClick={handleItemClick}
                                    />
                                </div>
                            )}
                        </div>
                    </motion.aside>
                </>
            )}
        </AnimatePresence>
    );

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="relative inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 transition hover:border-slate-300 hover:text-slate-900"
                aria-label="To-Do"
            >
                <ListChecks className="h-4 w-4" />
                {count > 0 && (
                    <span className="absolute -right-1 -top-1 inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-amber-500 px-1 text-[10px] font-semibold text-white">
                        {count > 99 ? '99+' : count}
                    </span>
                )}
            </button>

            {typeof document !== 'undefined' ? createPortal(panel, document.body) : null}
        </>
    );
}

interface TodoGroupProps {
    label: string;
    description: string;
    items: SharedTodoItem[];
    emptyText: string;
    onItemClick: (item: SharedTodoItem) => void;
}

function TodoGroup({ label, description, items, emptyText, onItemClick }: TodoGroupProps) {
    return (
        <section>
            <header className="mb-2 flex items-baseline justify-between border-b border-slate-100 pb-2">
                <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    {label}
                </h3>
                <span className="text-[11px] text-slate-400">{description}</span>
            </header>

            {items.length === 0 ? (
                <p className="rounded-xl bg-slate-50 px-3 py-3 text-xs text-slate-500">{emptyText}</p>
            ) : (
                <ul className="space-y-2.5">
                    {items.map((item) => {
                        const styles = STATE_STYLES[item.state];
                        const Icon = item.kind === 'assignment' ? ClipboardList : FileSpreadsheet;
                        return (
                            <li key={`${item.kind}-${item.id}`}>
                                <button
                                    type="button"
                                    disabled={!item.url}
                                    onClick={() => onItemClick(item)}
                                    className={`flex w-full items-start gap-3 rounded-2xl border p-4 text-left transition hover:border-slate-300 disabled:cursor-not-allowed disabled:opacity-60 ${styles.border}`}
                                >
                                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                                        <Icon className="h-4 w-4" />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span
                                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${styles.chip} ${styles.chipText}`}
                                            >
                                                {styles.label}
                                            </span>
                                            {item.is_today && (
                                                <span className="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700">
                                                    Hari Ini
                                                </span>
                                            )}
                                            {item.subject_name && (
                                                <span className="truncate text-[11px] text-slate-500">
                                                    {item.subject_name}
                                                </span>
                                            )}
                                        </div>
                                        <h4 className="mt-1.5 text-sm font-semibold text-slate-900">
                                            {item.title}
                                        </h4>
                                        <p className="mt-0.5 text-xs text-slate-500">{sublineFor(item)}</p>
                                    </div>
                                </button>
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}
