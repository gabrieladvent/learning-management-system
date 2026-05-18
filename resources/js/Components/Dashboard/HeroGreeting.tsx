import { motion } from 'framer-motion';
import { Sparkles } from 'lucide-react';

interface Props {
    name: string;
    classroomName: string | null;
    academicYear: string | null;
    courseCount: number;
}

const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const BULAN = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

function greetByHour(hour: number): string {
    if (hour < 11) return 'Selamat pagi';
    if (hour < 15) return 'Selamat siang';
    if (hour < 18) return 'Selamat sore';
    return 'Selamat malam';
}

function formatToday(d: Date): string {
    return `${HARI[d.getDay()]}, ${d.getDate()} ${BULAN[d.getMonth()]} ${d.getFullYear()}`;
}

export default function HeroGreeting({ name, classroomName, academicYear, courseCount }: Props) {
    const now = new Date();
    const greeting = greetByHour(now.getHours());

    return (
        <motion.section
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.4, ease: [0.22, 1, 0.36, 1] }}
            className="relative mb-8 overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
        >
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_85%_-20%,rgba(14,165,233,0.18),transparent_55%),radial-gradient(circle_at_-10%_120%,rgba(99,102,241,0.12),transparent_50%)]" />

            <div className="relative flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-2.5 py-1 text-xs font-medium text-sky-700 ring-1 ring-sky-100 backdrop-blur">
                        <Sparkles className="h-3 w-3" />
                        {formatToday(now)}
                    </div>

                    <h1 className="mt-3 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                        {greeting}, <span className="text-sky-700">{name}</span>
                    </h1>
                    <p className="mt-1.5 max-w-md text-sm text-slate-600">
                        Semoga harimu produktif. Lanjutkan belajar dari mata pelajaran di bawah.
                    </p>
                </div>

                <dl className="grid grid-cols-3 gap-3 sm:gap-4">
                    <Stat label="Kelas" value={classroomName ?? '—'} />
                    <Stat label="T.A." value={academicYear ?? '—'} />
                    <Stat label="Mapel" value={String(courseCount)} />
                </dl>
            </div>
        </motion.section>
    );
}

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-[72px] rounded-xl bg-white/70 px-3 py-2 text-center ring-1 ring-slate-100 backdrop-blur">
            <dt className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{label}</dt>
            <dd className="mt-0.5 truncate text-sm font-semibold text-slate-900">{value}</dd>
        </div>
    );
}
