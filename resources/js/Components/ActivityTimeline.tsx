import { motion } from 'framer-motion';
import { Award, CheckCircle2, Megaphone, PencilLine, LucideIcon } from 'lucide-react';
import { useMemo } from 'react';
import { fadeUp, staggerContainer } from '@/lib/motion';

export type ActivityVariant = 'system' | 'create' | 'update' | 'grade';

export interface ActivityItem {
    id: string;
    title: string;
    description?: string | null;
    occurred_at: string; // ISO
    variant?: ActivityVariant;
}

interface Props {
    activities: ActivityItem[];
    title?: string;
    emptyMessage?: string;
}

const VARIANT_STYLE: Record<ActivityVariant, { icon: LucideIcon; ring: string; bg: string; fg: string }> = {
    system: { icon: Megaphone, ring: 'ring-slate-200', bg: 'bg-slate-50', fg: 'text-slate-500' },
    create: { icon: CheckCircle2, ring: 'ring-emerald-200', bg: 'bg-emerald-50', fg: 'text-emerald-600' },
    update: { icon: PencilLine, ring: 'ring-sky-200', bg: 'bg-sky-50', fg: 'text-sky-600' },
    grade: { icon: Award, ring: 'ring-amber-200', bg: 'bg-amber-50', fg: 'text-amber-600' },
};

function formatDateHeading(iso: string): string {
    return new Date(iso).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function formatTime(iso: string): string {
    return new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

/** Group activities by date (YYYY-MM-DD), preserving the input order within each group. */
function groupByDate(activities: ActivityItem[]): Array<{ dateKey: string; label: string; items: ActivityItem[] }> {
    const map = new Map<string, ActivityItem[]>();
    for (const a of activities) {
        const key = a.occurred_at.slice(0, 10);
        if (!map.has(key)) map.set(key, []);
        map.get(key)!.push(a);
    }

    return Array.from(map.entries()).map(([dateKey, items]) => ({
        dateKey,
        label: formatDateHeading(items[0].occurred_at),
        items,
    }));
}

export default function ActivityTimeline({
    activities,
    title = 'Riwayat Aktivitas',
    emptyMessage = 'Belum ada aktivitas.',
}: Props) {
    const groups = useMemo(() => groupByDate(activities), [activities]);

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5">
            <h3 className="mb-4 text-sm font-semibold tracking-tight text-slate-900">{title}</h3>

            {groups.length === 0 ? (
                <p className="text-xs text-slate-500">{emptyMessage}</p>
            ) : (
                <motion.div initial="hidden" animate="visible" variants={staggerContainer} className="space-y-5">
                    {groups.map((group) => (
                        <div key={group.dateKey}>
                            <div className="mb-2 flex items-center justify-between">
                                <div className="text-xs font-semibold text-slate-700">{group.label}</div>
                                <span className="rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-semibold text-sky-700">
                                    {group.items.length} aktivitas
                                </span>
                            </div>
                            <ul className="space-y-2">
                                {group.items.map((item) => {
                                    const style = VARIANT_STYLE[item.variant ?? 'system'];
                                    const Icon = style.icon;
                                    return (
                                        <motion.li
                                            key={item.id}
                                            variants={fadeUp}
                                            className="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/40 p-3"
                                        >
                                            <div className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-1 ${style.bg} ${style.fg} ${style.ring}`}>
                                                <Icon className="h-4 w-4" />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-start justify-between gap-2">
                                                    <div className="text-sm font-medium text-slate-900">{item.title}</div>
                                                    <span className="shrink-0 text-xs text-slate-400">{formatTime(item.occurred_at)}</span>
                                                </div>
                                                {item.description && (
                                                    <div className="mt-0.5 text-xs text-slate-500">{item.description}</div>
                                                )}
                                            </div>
                                        </motion.li>
                                    );
                                })}
                            </ul>
                        </div>
                    ))}
                </motion.div>
            )}
        </div>
    );
}
