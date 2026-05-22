import { router } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { ArrowRight, Clock, FileSpreadsheet } from 'lucide-react';
import { useEffect, useState } from 'react';
import { UpcomingExam } from './dashboard.type';

interface Props {
    exam: UpcomingExam;
}

function diffNow(iso: string): { days: number; hours: number; minutes: number; total: number } {
    const target = new Date(iso).getTime();
    const now = Date.now();
    const total = target - now;
    const totalMin = Math.max(0, Math.floor(total / 60_000));
    const days = Math.floor(totalMin / 1440);
    const hours = Math.floor((totalMin % 1440) / 60);
    const minutes = totalMin % 60;
    return { days, hours, minutes, total };
}

export default function UpcomingExamCard({ exam }: Props) {
    const [countdown, setCountdown] = useState(() =>
        exam.starts_at ? diffNow(exam.starts_at) : null,
    );

    useEffect(() => {
        if (!exam.starts_at) return;
        const id = setInterval(() => {
            setCountdown(diffNow(exam.starts_at!));
        }, 30_000);
        return () => clearInterval(id);
    }, [exam.starts_at]);

    const startsLabel = exam.starts_at
        ? new Date(exam.starts_at).toLocaleString('id-ID', {
              weekday: 'short',
              day: 'numeric',
              month: 'short',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '—';

    return (
        <motion.div
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.3 }}
            className="mb-8 overflow-hidden rounded-2xl border border-sky-200 bg-gradient-to-br from-sky-50 to-indigo-50 p-5 sm:p-6"
        >
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-start gap-3">
                    <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-sky-600 shadow-sm">
                        <FileSpreadsheet className="h-5 w-5" />
                    </div>
                    <div className="min-w-0">
                        <p className="text-[11px] font-semibold uppercase tracking-wider text-sky-700">
                            Ujian Terdekat
                        </p>
                        <h3 className="mt-0.5 truncate text-base font-semibold text-slate-900 sm:text-lg">
                            {exam.title}
                        </h3>
                        <p className="mt-0.5 text-sm text-slate-600">
                            {exam.subject_name ?? 'Tanpa mata pelajaran'} · {startsLabel}
                            {exam.duration_minutes ? ` · ${exam.duration_minutes} menit` : ''}
                        </p>
                    </div>
                </div>

                <div className="flex flex-shrink-0 items-center gap-3">
                    {countdown && (
                        <div className="flex items-center gap-1.5 rounded-xl bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm">
                            <Clock className="h-4 w-4 text-sky-600" />
                            {countdown.days > 0 && `${countdown.days}h `}
                            {countdown.hours > 0 && `${countdown.hours}j `}
                            {countdown.minutes}m
                        </div>
                    )}
                    {exam.url && (
                        <button
                            type="button"
                            onClick={() => router.visit(exam.url!)}
                            className="inline-flex items-center gap-1.5 rounded-xl bg-sky-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700"
                        >
                            Lihat
                            <ArrowRight className="h-3.5 w-3.5" />
                        </button>
                    )}
                </div>
            </div>
        </motion.div>
    );
}
