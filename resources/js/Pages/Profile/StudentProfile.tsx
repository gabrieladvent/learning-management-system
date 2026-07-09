import { Head, useForm, usePage } from '@inertiajs/react';
import {
    Award,
    BookOpen,
    Camera,
    CheckCircle2,
    ClipboardList,
    KeyRound,
    Loader2,
} from 'lucide-react';
import { FormEvent, useRef, useState } from 'react';
import { StudentLayout } from '@/Layouts';
import { PageProps } from '@/types';

interface CourseProgress {
    id: string;
    subject_name: string | null;
    subject_code: string | null;
    classroom_name: string;
    teacher_name: string | null;
    assignments_total: number;
    assignments_completed: number;
    exams_total: number;
    exams_completed: number;
    progress_percent: number;
}

interface ProgressStats {
    assignments_pending: number;
    assignments_completed: number;
    exams_completed: number;
    avg_score: number | null;
}

interface ProfilePageProps {
    progress: {
        stats: ProgressStats;
        courses: CourseProgress[];
    };
    [key: string]: unknown;
}

export default function StudentProfile() {
    const { props } = usePage<PageProps<ProfilePageProps>>();
    const { auth, progress } = props;
    const student = auth.student;
    const { stats, courses } = progress;

    return (
        <StudentLayout title="Profil">
            <Head title="Profil" />

            <div className="mb-8">
                <h1 className="text-xl font-semibold tracking-tight text-slate-900">Profil Saya</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Kelola foto profil, password, dan lihat progres belajarmu.
                </p>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-1">
                    <AvatarCard
                        avatarUrl={student?.avatar_url ?? null}
                        fullName={student?.full_name ?? 'Siswa'}
                        nisn={student?.nisn ?? '-'}
                        className={student?.class ?? null}
                    />
                    <PasswordCard />
                </div>

                <div className="space-y-6 lg:col-span-2">
                    <StatsGrid stats={stats} />
                    <CourseProgressList courses={courses} />
                </div>
            </div>
        </StudentLayout>
    );
}

function AvatarCard({
    avatarUrl,
    fullName,
    nisn,
    className,
}: {
    avatarUrl: string | null;
    fullName: string;
    nisn: string;
    className: string | null;
}) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const { setData, post, processing, errors, reset } = useForm<{ photo: File | null }>({
        photo: null,
    });

    const initials = fullName
        .split(' ')
        .slice(0, 2)
        .map((word) => word.charAt(0))
        .join('')
        .toUpperCase();

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        setData('photo', file);
        setPreview(file ? URL.createObjectURL(file) : null);

        if (file) {
            post(route('student.profile.photo'), {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    reset('photo');
                    setPreview(null);
                },
            });
        }
    };

    const shownImage = preview ?? avatarUrl;

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">
            <div className="relative mx-auto h-28 w-28">
                {shownImage ? (
                    <img
                        src={shownImage}
                        alt={fullName}
                        className="h-28 w-28 rounded-full object-cover ring-4 ring-sky-50"
                    />
                ) : (
                    <div className="flex h-28 w-28 items-center justify-center rounded-full bg-sky-600 text-3xl font-semibold text-white ring-4 ring-sky-50">
                        {initials || 'S'}
                    </div>
                )}
                <button
                    type="button"
                    onClick={() => fileInput.current?.click()}
                    disabled={processing}
                    className="absolute bottom-0 right-0 flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:text-sky-600 disabled:opacity-60"
                    aria-label="Ubah foto profil"
                >
                    {processing ? (
                        <Loader2 className="h-4 w-4 animate-spin" />
                    ) : (
                        <Camera className="h-4 w-4" />
                    )}
                </button>
                <input
                    ref={fileInput}
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    className="hidden"
                    onChange={handleFileChange}
                />
            </div>

            <div className="mt-4">
                <div className="text-base font-semibold text-slate-900">{fullName}</div>
                <div className="mt-0.5 text-sm text-slate-500">NISN {nisn}</div>
                {className && <div className="text-xs text-slate-400">Kelas {className}</div>}
            </div>

            {errors.photo && <p className="mt-3 text-sm text-rose-600">{errors.photo}</p>}
            <p className="mt-3 text-xs text-slate-400">Klik ikon kamera. PNG/JPG/WEBP, maks 2MB.</p>
        </div>
    );
}

function PasswordCard() {
    const { data, setData, patch, processing, errors, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        patch(route('student.profile.password'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="mb-4 flex items-center gap-2">
                <KeyRound className="h-4 w-4 text-sky-600" />
                <h2 className="text-sm font-semibold text-slate-900">Ubah Password</h2>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <Field
                    label="Password saat ini"
                    type="password"
                    value={data.current_password}
                    autoComplete="current-password"
                    onChange={(v) => setData('current_password', v)}
                    error={errors.current_password}
                />
                <Field
                    label="Password baru"
                    type="password"
                    value={data.password}
                    autoComplete="new-password"
                    onChange={(v) => setData('password', v)}
                    error={errors.password}
                />
                <Field
                    label="Konfirmasi password baru"
                    type="password"
                    value={data.password_confirmation}
                    autoComplete="new-password"
                    onChange={(v) => setData('password_confirmation', v)}
                    error={errors.password_confirmation}
                />

                <button
                    type="submit"
                    disabled={processing}
                    className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-sky-700 disabled:opacity-60"
                >
                    {processing && <Loader2 className="h-4 w-4 animate-spin" />}
                    Simpan Password
                </button>
            </form>
        </div>
    );
}

function Field({
    label,
    type,
    value,
    autoComplete,
    onChange,
    error,
}: {
    label: string;
    type: string;
    value: string;
    autoComplete?: string;
    onChange: (v: string) => void;
    error?: string;
}) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">{label}</label>
            <input
                type={type}
                value={value}
                autoComplete={autoComplete}
                onChange={(e) => onChange(e.target.value)}
                className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-sky-500 focus:ring-sky-500"
            />
            {error && <p className="mt-1 text-sm text-rose-600">{error}</p>}
        </div>
    );
}

function StatsGrid({ stats }: { stats: ProgressStats }) {
    const items = [
        {
            label: 'Tugas Selesai',
            value: stats.assignments_completed,
            icon: CheckCircle2,
            color: 'text-emerald-600',
            bg: 'bg-emerald-50',
        },
        {
            label: 'Tugas Pending',
            value: stats.assignments_pending,
            icon: ClipboardList,
            color: 'text-amber-600',
            bg: 'bg-amber-50',
        },
        {
            label: 'Ujian Selesai',
            value: stats.exams_completed,
            icon: BookOpen,
            color: 'text-sky-600',
            bg: 'bg-sky-50',
        },
        {
            label: 'Rata-rata Nilai',
            value: stats.avg_score ?? '-',
            icon: Award,
            color: 'text-violet-600',
            bg: 'bg-violet-50',
        },
    ];

    return (
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
            {items.map((item) => (
                <div key={item.label} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className={`inline-flex h-9 w-9 items-center justify-center rounded-lg ${item.bg}`}>
                        <item.icon className={`h-4 w-4 ${item.color}`} />
                    </div>
                    <div className="mt-3 text-2xl font-semibold tracking-tight text-slate-900">
                        {item.value}
                    </div>
                    <div className="mt-0.5 text-xs text-slate-500">{item.label}</div>
                </div>
            ))}
        </div>
    );
}

function CourseProgressList({ courses }: { courses: CourseProgress[] }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 className="mb-1 text-sm font-semibold text-slate-900">Progres per Mata Pelajaran</h2>
            <p className="mb-4 text-xs text-slate-500">
                Persentase dihitung dari tugas & ujian yang sudah kamu selesaikan.
            </p>

            {courses.length === 0 ? (
                <p className="py-6 text-center text-sm text-slate-400">
                    Belum ada mata pelajaran untuk ditampilkan.
                </p>
            ) : (
                <div className="space-y-5">
                    {courses.map((course) => (
                        <div key={course.id}>
                            <div className="mb-1.5 flex items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <div className="truncate text-sm font-medium text-slate-900">
                                        {course.subject_name ?? 'Mata Pelajaran'}
                                    </div>
                                    <div className="truncate text-xs text-slate-500">
                                        {course.classroom_name}
                                        {course.teacher_name ? ` · ${course.teacher_name}` : ''}
                                    </div>
                                </div>
                                <span className="shrink-0 text-sm font-semibold text-slate-900">
                                    {course.progress_percent}%
                                </span>
                            </div>
                            <div className="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                <div
                                    className="h-full rounded-full bg-sky-600 transition-all"
                                    style={{ width: `${course.progress_percent}%` }}
                                />
                            </div>
                            <div className="mt-1 flex gap-4 text-xs text-slate-400">
                                <span>
                                    Tugas {course.assignments_completed}/{course.assignments_total}
                                </span>
                                <span>
                                    Ujian {course.exams_completed}/{course.exams_total}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
