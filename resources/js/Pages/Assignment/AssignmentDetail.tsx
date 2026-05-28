import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    AlertCircle,
    ArrowLeft,
    CalendarClock,
    CheckCircle2,
    ClipboardList,
    LucideIcon,
    MessageSquare,
    Paperclip,
    Target,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { ActivityTimeline, FileCard, MathContent } from '@/Components';
import {
    SubmissionForm,
    SubmissionView,
    type AssignmentDetailPageProps,
    type AssignmentStatus,
} from '@/Components/Assignment';
import { StudentLayout } from '@/Layouts';
import { fadeUp, staggerContainer, useLearningProgress } from '@/lib';
import { PageProps } from '@/types';

function formatDateTime(iso: string | null): string | null {
    if (!iso) return null;
    return new Date(iso).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const STATUS_BANNER: Record<AssignmentStatus, { label: string; tone: 'amber' | 'rose' | 'sky' | 'emerald'; icon: LucideIcon }> = {
    pending: { label: 'Belum dikumpulkan', tone: 'amber', icon: ClipboardList },
    overdue: { label: 'Sudah lewat deadline', tone: 'rose', icon: AlertCircle },
    submitted: { label: 'Menunggu penilaian guru', tone: 'sky', icon: CheckCircle2 },
    graded: { label: 'Sudah dinilai', tone: 'emerald', icon: CheckCircle2 },
};

const TONE_CLASS: Record<'amber' | 'rose' | 'sky' | 'emerald', { bg: string; border: string; text: string; icon: string }> = {
    amber: { bg: 'bg-amber-50', border: 'border-amber-200', text: 'text-amber-900', icon: 'text-amber-600' },
    rose: { bg: 'bg-rose-50', border: 'border-rose-200', text: 'text-rose-900', icon: 'text-rose-600' },
    sky: { bg: 'bg-sky-50', border: 'border-sky-200', text: 'text-sky-900', icon: 'text-sky-600' },
    emerald: { bg: 'bg-emerald-50', border: 'border-emerald-200', text: 'text-emerald-900', icon: 'text-emerald-600' },
};

export default function AssignmentDetail() {
    const { props } = usePage<PageProps<AssignmentDetailPageProps>>();
    const { course, material, assignment, submission, activities } = props;

    useLearningProgress('assignment', assignment.id, {
        enabled: !props.auth.student?.tracking_opt_out,
    });

    const [editing, setEditing] = useState(false);

    const deadline = formatDateTime(assignment.deadline);
    const submittedAt = formatDateTime(submission?.submitted_at ?? null);
    const status = STATUS_BANNER[assignment.status];
    const tone = TONE_CLASS[status.tone];
    const StatusIcon = status.icon;

    const isGraded = assignment.status === 'graded';
    const submitDisabled = assignment.is_overdue || isGraded;
    const canEdit = !!submission && !isGraded && !assignment.is_overdue;
    const showForm = !submission || editing;

    const lockReason = isGraded
        ? 'Sudah dinilai'
        : assignment.is_overdue
          ? 'Lewat deadline'
          : undefined;

    // Reset editing & refresh server state setelah submit sukses.
    const handleSubmissionSuccess = () => {
        setEditing(false);
        router.reload({ only: ['submission', 'activities', 'assignment'] });
    };

    return (
        <StudentLayout title={assignment.title}>
            <Head title={assignment.title} />

            <motion.div
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3, ease: [0.22, 1, 0.36, 1] }}
                className="mb-6"
            >
                <Link
                    href={route('student.materials.show', { course: course.id, material: material.id })}
                    className="mb-4 inline-flex items-center gap-1.5 text-sm text-slate-500 transition-colors hover:text-slate-900"
                >
                    <ArrowLeft className="h-4 w-4" />
                    {material.title}
                </Link>

                <div className="flex items-start gap-4">
                    <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                        <ClipboardList className="h-6 w-6" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="text-xs font-medium uppercase tracking-wide text-amber-600">Tugas</div>
                        <h1 className="mt-0.5 text-2xl font-semibold tracking-tight text-slate-900">
                            {assignment.title}
                        </h1>
                        <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-500">
                            {course.classroom_name && <span>{course.classroom_name}</span>}
                            {course.subject_name && (
                                <>
                                    <span aria-hidden>•</span>
                                    <span>{course.subject_name}</span>
                                </>
                            )}
                            {course.teacher_name && (
                                <>
                                    <span aria-hidden>•</span>
                                    <span>{course.teacher_name}</span>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </motion.div>

            <motion.div
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3, delay: 0.05, ease: [0.22, 1, 0.36, 1] }}
                className={`mb-6 flex items-start gap-3 rounded-2xl border p-4 ${tone.bg} ${tone.border}`}
            >
                <StatusIcon className={`mt-0.5 h-5 w-5 shrink-0 ${tone.icon}`} />
                <div className="flex-1">
                    <div className={`text-sm font-semibold ${tone.text}`}>{status.label}</div>
                    {assignment.status === 'graded' && submission?.score !== null && submission?.score !== undefined && (
                        <div className={`mt-0.5 text-sm ${tone.text}`}>
                            Nilai: <span className="font-semibold">{submission.score}</span>
                            {assignment.max_score != null && <span className="opacity-70"> / {assignment.max_score}</span>}
                        </div>
                    )}
                    {submittedAt && (
                        <div className={`mt-0.5 text-xs opacity-75 ${tone.text}`}>Dikumpulkan: {submittedAt}</div>
                    )}
                </div>
            </motion.div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    {assignment.description && (
                        <Section title="Deskripsi Tugas" icon={ClipboardList}>
                            <MathContent
                                html={assignment.description}
                                className="prose prose-slate max-w-none prose-headings:font-semibold prose-headings:tracking-tight prose-a:text-sky-600"
                            />
                        </Section>
                    )}

                    {assignment.attachments.length > 0 && (
                        <Section title="Lampiran Soal" icon={Paperclip} count={assignment.attachments.length}>
                            <motion.div
                                initial="hidden"
                                animate="visible"
                                variants={staggerContainer}
                                className="grid grid-cols-1 gap-3 sm:grid-cols-2"
                            >
                                {assignment.attachments.map((file) => (
                                    <motion.div key={file.id} variants={fadeUp}>
                                        <FileCard file={file} />
                                    </motion.div>
                                ))}
                            </motion.div>
                        </Section>
                    )}

                    <Section
                        title={showForm ? (submission ? 'Edit Submission' : 'Kerjakan Tugas') : 'Submission Kamu'}
                        icon={Target}
                        action={
                            showForm && submission ? (
                                <button
                                    type="button"
                                    onClick={() => setEditing(false)}
                                    className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:border-slate-300 hover:text-slate-900"
                                >
                                    <X className="h-3.5 w-3.5" />
                                    Batal edit
                                </button>
                            ) : undefined
                        }
                    >
                        {submitDisabled && !submission && (
                            <div className="mb-4 flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">
                                <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                                <span>Tugas sudah melewati deadline. Kamu tidak bisa mengumpulkan lagi.</span>
                            </div>
                        )}

                        {showForm ? (
                            <SubmissionForm
                                materialId={material.id}
                                assignment={assignment}
                                submission={submission}
                                disabled={submitDisabled}
                                onSuccess={handleSubmissionSuccess}
                            />
                        ) : (
                            <SubmissionView
                                submission={submission!}
                                canEdit={canEdit}
                                lockReason={lockReason}
                                onEdit={() => setEditing(true)}
                            />
                        )}
                    </Section>

                    {submission?.feedback && (
                        <Section title="Feedback Guru" icon={MessageSquare}>
                            <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-700">
                                {submission.feedback}
                            </div>
                        </Section>
                    )}
                </div>

                <aside className="space-y-4">
                    <div className="rounded-2xl border border-slate-200 bg-white p-5">
                        <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <CalendarClock className="h-3.5 w-3.5" />
                            Deadline
                        </div>
                        <div className="mt-2 text-sm font-medium text-slate-900">{deadline ?? 'Tanpa deadline'}</div>
                    </div>

                    {assignment.max_score != null && (
                        <div className="rounded-2xl border border-slate-200 bg-white p-5">
                            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <Target className="h-3.5 w-3.5" />
                                Nilai Maksimal
                            </div>
                            <div className="mt-2 text-sm font-medium text-slate-900">{assignment.max_score}</div>
                        </div>
                    )}

                    <div className="rounded-2xl border border-slate-200 bg-white p-5">
                        <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <Paperclip className="h-3.5 w-3.5" />
                            Aturan File
                        </div>
                        <div className="mt-2 space-y-1 text-sm text-slate-700">
                            <div>
                                Format: <span className="font-medium">{assignment.allowed_file_types.join(', ')}</span>
                            </div>
                            <div>
                                Ukuran maks: <span className="font-medium">{assignment.max_file_size_mb} MB</span>
                            </div>
                        </div>
                    </div>

                    <ActivityTimeline activities={activities} />
                </aside>
            </div>
        </StudentLayout>
    );
}

function Section({
    title,
    icon: Icon,
    count,
    action,
    children,
}: {
    title: string;
    icon: LucideIcon;
    count?: number;
    action?: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <section>
            <div className="mb-3 flex items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                    <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                        <Icon className="h-3.5 w-3.5" />
                    </div>
                    <h2 className="text-sm font-semibold tracking-tight text-slate-900">{title}</h2>
                    {count != null && (
                        <span className="rounded-md bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-500">
                            {count}
                        </span>
                    )}
                </div>
                {action}
            </div>
            <div className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">{children}</div>
        </section>
    );
}
