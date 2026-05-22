import { router, usePage } from '@inertiajs/react';
import { Bell, Check, CheckCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { PageProps, StudentNotification } from '@/types';

const POLL_INTERVAL_MS = 30_000;

function formatRelative(iso: string | null): string {
    if (!iso) return '';
    const date = new Date(iso);
    const diff = Math.floor((Date.now() - date.getTime()) / 1000);
    if (diff < 60) return 'baru saja';
    if (diff < 3600) return `${Math.floor(diff / 60)} menit lalu`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} jam lalu`;
    if (diff < 604800) return `${Math.floor(diff / 86400)} hari lalu`;
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

export default function NotificationsDropdown() {
    const { props } = usePage<PageProps>();
    const notifications = props.notifications;

    const [open, setOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        const id = setInterval(() => {
            router.reload({ only: ['notifications'] });
        }, POLL_INTERVAL_MS);

        return () => clearInterval(id);
    }, []);

    useEffect(() => {
        function onClick(e: MouseEvent) {
            if (!containerRef.current?.contains(e.target as Node)) {
                setOpen(false);
            }
        }
        if (open) document.addEventListener('mousedown', onClick);
        return () => document.removeEventListener('mousedown', onClick);
    }, [open]);

    const unreadCount = notifications?.unread_count ?? 0;
    const items: StudentNotification[] = notifications?.recent ?? [];

    function handleItemClick(item: StudentNotification) {
        router.post(
            route('student.notifications.read', { id: item.id }),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    router.reload({ only: ['notifications'] });
                },
            },
        );

        const url = item.data?.url;
        if (url) {
            setOpen(false);
            router.visit(url);
        }
    }

    function handleMarkAllRead() {
        router.post(
            route('student.notifications.read-all'),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    router.reload({ only: ['notifications'] });
                },
            },
        );
    }

    return (
        <div className="relative" ref={containerRef}>
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="relative inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 transition hover:border-slate-300 hover:text-slate-900"
                aria-label="Notifikasi"
            >
                <Bell className="h-4 w-4" />
                {unreadCount > 0 && (
                    <span className="absolute -right-1 -top-1 inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white">
                        {unreadCount > 99 ? '99+' : unreadCount}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute right-0 z-30 mt-2 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg sm:w-96">
                    <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <h3 className="text-sm font-semibold text-slate-900">Notifikasi</h3>
                        {unreadCount > 0 && (
                            <button
                                type="button"
                                onClick={handleMarkAllRead}
                                className="inline-flex items-center gap-1 text-xs font-medium text-sky-700 hover:text-sky-900"
                            >
                                <CheckCheck className="h-3.5 w-3.5" />
                                Tandai semua dibaca
                            </button>
                        )}
                    </div>

                    <div className="max-h-96 overflow-y-auto">
                        {items.length === 0 ? (
                            <div className="px-4 py-10 text-center text-sm text-slate-500">
                                Belum ada notifikasi.
                            </div>
                        ) : (
                            <ul className="divide-y divide-slate-100">
                                {items.map((item) => {
                                    const unread = item.read_at === null;
                                    return (
                                        <li key={item.id}>
                                            <button
                                                type="button"
                                                onClick={() => handleItemClick(item)}
                                                className={`flex w-full items-start gap-3 px-4 py-3 text-left transition hover:bg-slate-50 ${
                                                    unread ? 'bg-sky-50/50' : ''
                                                }`}
                                            >
                                                <span
                                                    className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${
                                                        unread ? 'bg-sky-500' : 'bg-transparent'
                                                    }`}
                                                />
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium text-slate-900">
                                                        {item.data?.title ?? '—'}
                                                    </p>
                                                    {item.data?.body && (
                                                        <p className="mt-0.5 truncate text-xs text-slate-600">
                                                            {item.data.body}
                                                        </p>
                                                    )}
                                                    <p className="mt-1 text-[11px] text-slate-400">
                                                        {formatRelative(item.created_at)}
                                                    </p>
                                                </div>
                                                {!unread && (
                                                    <Check className="mt-1 h-3.5 w-3.5 shrink-0 text-slate-300" />
                                                )}
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
