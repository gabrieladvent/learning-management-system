import { motion } from 'framer-motion';
import { CheckCircle2, ClipboardList, FileSpreadsheet, Trophy } from 'lucide-react';
import { StudentStats } from './dashboard.type';
import { fadeUp, staggerContainer } from '@/lib';

interface Props {
    stats: StudentStats;
}

export default function StatsRow({ stats }: Props) {
    const items = [
        {
            label: 'Tugas Belum',
            value: String(stats.assignments_pending),
            icon: ClipboardList,
            accent: stats.assignments_pending > 0 ? 'text-amber-700' : 'text-slate-700',
            bg: stats.assignments_pending > 0 ? 'bg-amber-50' : 'bg-slate-50',
        },
        {
            label: 'Tugas Selesai',
            value: String(stats.assignments_completed),
            icon: CheckCircle2,
            accent: 'text-emerald-700',
            bg: 'bg-emerald-50',
        },
        {
            label: 'Ujian Selesai',
            value: String(stats.exams_completed),
            icon: FileSpreadsheet,
            accent: 'text-sky-700',
            bg: 'bg-sky-50',
        },
        {
            label: 'Rata-rata Nilai',
            value: stats.avg_score !== null ? Number(stats.avg_score).toFixed(1) : '—',
            icon: Trophy,
            accent: 'text-violet-700',
            bg: 'bg-violet-50',
        },
    ];

    return (
        <motion.div
            initial="hidden"
            animate="visible"
            variants={staggerContainer}
            className="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4"
        >
            {items.map((item) => {
                const Icon = item.icon;
                return (
                    <motion.div
                        key={item.label}
                        variants={fadeUp}
                        className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <div className="flex items-center gap-3">
                            <div className={`flex h-9 w-9 items-center justify-center rounded-xl ${item.bg} ${item.accent}`}>
                                <Icon className="h-4 w-4" />
                            </div>
                            <div className="min-w-0">
                                <p className="truncate text-[11px] font-medium uppercase tracking-wider text-slate-500">
                                    {item.label}
                                </p>
                                <p className="text-lg font-semibold text-slate-900">{item.value}</p>
                            </div>
                        </div>
                    </motion.div>
                );
            })}
        </motion.div>
    );
}
