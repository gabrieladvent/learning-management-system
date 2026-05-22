import { motion } from 'framer-motion';
import { GraduationCap, Sparkles } from 'lucide-react';

interface Props {
    name: string;
    fullName: string;
    classroomName: string | null;
    academicYear: string | null;
    courseCount: number;
    homeroomTeacherName: string | null;
    semester: number | null;
    inspire: string;
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

function initialsOf(fullName: string): string {
    const parts = fullName.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return '?';
    if (parts.length === 1) return parts[0]!.charAt(0).toUpperCase();
    return (parts[0]!.charAt(0) + parts[parts.length - 1]!.charAt(0)).toUpperCase();
}

export default function HeroGreeting({
    name,
    fullName,
    classroomName,
    academicYear,
    courseCount,
    homeroomTeacherName,
    semester,
    inspire,
}: Props) {
    const now = new Date();
    const greeting = greetByHour(now.getHours());
    const initials = initialsOf(fullName);

    return (
        <motion.section
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.4, ease: [0.22, 1, 0.36, 1] }}
            className="relative mb-8 overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
        >
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_85%_-20%,rgba(14,165,233,0.18),transparent_55%),radial-gradient(circle_at_-10%_120%,rgba(99,102,241,0.12),transparent_50%)]" />

            <div className="relative flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                <div className="flex items-start gap-4">
                    <div
                        className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-sky-600 text-lg font-semibold text-white shadow-sm ring-2 ring-white"
                        aria-hidden
                    >
                        {initials}
                    </div>

                    <div>
                        <div className="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-2.5 py-1 text-xs font-medium text-sky-700 ring-1 ring-sky-100 backdrop-blur">
                            <Sparkles className="h-3 w-3" />
                            {formatToday(now)}
                        </div>

                        <h1 className="mt-3 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                            {greeting}, <span className="text-sky-700">{name}</span>
                        </h1>
                        <p className="mt-1.5 max-w-md text-sm italic text-slate-600">
                            &ldquo;{inspire}&rdquo;
                        </p>

                        {homeroomTeacherName && (
                            <p className="mt-3 inline-flex items-center gap-1.5 text-xs text-slate-500">
                                <GraduationCap className="h-3.5 w-3.5" />
                                Wali kelas: <span className="font-medium text-slate-700">{homeroomTeacherName}</span>
                            </p>
                        )}
                    </div>
                </div>

                <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
                    <Stat label="Kelas" value={classroomName ?? '—'} />
                    <Stat label="Semester" value={semester !== null ? String(semester) : '—'} />
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
