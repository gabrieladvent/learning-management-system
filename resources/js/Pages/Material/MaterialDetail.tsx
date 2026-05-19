import { Head, Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    ArrowLeft,
    Calendar,
    ClipboardList,
    ExternalLink,
    FileSpreadsheet,
    FileText,
    LucideIcon,
    Paperclip,
} from 'lucide-react';
import { AssignmentListCard } from '@/Components/Assignment';
import { ExamListCard } from '@/Components/Exam';
import { FileCard, MathContent } from '@/Components';
import type { MaterialDetailPageProps } from '@/Components/Course';
import { StudentLayout } from '@/Layouts';
import { fadeUp, staggerContainer } from '@/lib';
import { PageProps } from '@/types';

function formatDate(iso: string | null) {
    if (!iso) return null;
    return new Date(iso).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

export default function MaterialDetail() {
    const { props } = usePage<PageProps<MaterialDetailPageProps>>();
    const { course, material } = props;

    const publishedAt = formatDate(material.available_from ?? material.created_at);
    const hasContent = !!material.content?.trim();
    const hasFiles = material.files.length > 0;
    const hasLink = !!material.link_url;
    const assignments = material.assignments ?? [];
    const hasAssignments = assignments.length > 0;
    const exams = material.exams ?? [];
    const hasExams = exams.length > 0;
    const hasAnyContent = hasContent || hasFiles || hasLink || hasAssignments || hasExams;

    return (
        <StudentLayout title={material.title}>
            <Head title={material.title} />

            <motion.div
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3, ease: [0.22, 1, 0.36, 1] }}
                className="mb-8"
            >
                <Link
                    href={route('student.courses.show', { course: course.id })}
                    className="mb-4 inline-flex items-center gap-1.5 text-sm text-slate-500 transition-colors hover:text-slate-900"
                >
                    <ArrowLeft className="h-4 w-4" />
                    {course.subject_name ?? 'Mata Pelajaran'}
                </Link>

                {material.topic && (
                    <div className="text-xs font-medium uppercase tracking-wide text-sky-600">
                        {material.topic}
                    </div>
                )}
                <h1 className="mt-1 text-3xl font-semibold tracking-tight text-slate-900">
                    {material.title}
                </h1>
                {material.description && (
                    <p className="mt-2 max-w-3xl text-base text-slate-600">{material.description}</p>
                )}

                <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                    {course.classroom_name && <span>{course.classroom_name}</span>}
                    {course.teacher_name && (
                        <>
                            <span aria-hidden>•</span>
                            <span>{course.teacher_name}</span>
                        </>
                    )}
                    {publishedAt && (
                        <>
                            <span aria-hidden>•</span>
                            <span className="inline-flex items-center gap-1">
                                <Calendar className="h-3 w-3" />
                                {publishedAt}
                            </span>
                        </>
                    )}
                </div>
            </motion.div>

            {hasContent && (
                <motion.section
                    initial={{ opacity: 0, y: 8 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.3, delay: 0.05, ease: [0.22, 1, 0.36, 1] }}
                    className="mb-8 rounded-2xl border border-slate-200 bg-white p-6 sm:p-8"
                >
                    <MathContent
                        html={material.content ?? ''}
                        className="prose prose-slate max-w-none prose-headings:font-semibold prose-headings:tracking-tight prose-a:text-sky-600 prose-img:rounded-xl"
                    />
                </motion.section>
            )}

            {hasFiles && (
                <section className="mb-8">
                    <SectionTitle icon={Paperclip} title="Lampiran" count={material.files.length} />
                    <motion.div
                        initial="hidden"
                        animate="visible"
                        variants={staggerContainer}
                        className="grid grid-cols-1 gap-3 sm:grid-cols-2"
                    >
                        {material.files.map((file) => (
                            <motion.div key={file.id} variants={fadeUp}>
                                <FileCard file={file} />
                            </motion.div>
                        ))}
                    </motion.div>
                </section>
            )}

            {hasLink && (
                <section className="mb-8">
                    <SectionTitle icon={ExternalLink} title="Tautan" />
                    <motion.a
                        href={material.link_url ?? '#'}
                        target="_blank"
                        rel="noopener noreferrer"
                        whileHover={{ y: -2 }}
                        transition={{ duration: 0.2, ease: [0.22, 1, 0.36, 1] }}
                        className="group flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 transition-colors hover:border-sky-300"
                    >
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
                            <ExternalLink className="h-5 w-5" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <div className="text-sm font-medium text-slate-900">Buka tautan</div>
                            <div className="truncate text-xs text-slate-500">{material.link_url}</div>
                        </div>
                    </motion.a>
                </section>
            )}

            {hasAssignments && (
                <section className="mb-8">
                    <SectionTitle icon={ClipboardList} title="Tugas" count={assignments.length} />
                    <motion.div
                        initial="hidden"
                        animate="visible"
                        variants={staggerContainer}
                        className="grid grid-cols-1 gap-3"
                    >
                        {assignments.map((a) => (
                            <AssignmentListCard key={a.id} materialId={material.id} assignment={a} />
                        ))}
                    </motion.div>
                </section>
            )}

            {hasExams && (
                <section className="mb-8">
                    <SectionTitle icon={FileSpreadsheet} title="Ujian" count={exams.length} />
                    <motion.div
                        initial="hidden"
                        animate="visible"
                        variants={staggerContainer}
                        className="grid grid-cols-1 gap-3"
                    >
                        {exams.map((e) => (
                            <ExamListCard key={e.id} materialId={material.id} exam={e} />
                        ))}
                    </motion.div>
                </section>
            )}

            {!hasAnyContent && (
                <motion.div
                    initial={{ opacity: 0, y: 8 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.3, ease: [0.22, 1, 0.36, 1] }}
                    className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-16 text-center"
                >
                    <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-50 text-slate-400">
                        <FileText className="h-5 w-5" />
                    </div>
                    <h3 className="text-sm font-semibold text-slate-700">Materi belum berisi konten</h3>
                    <p className="mt-1 max-w-sm text-sm text-slate-500">
                        Guru belum menambahkan konten, lampiran, atau tautan untuk materi ini.
                    </p>
                </motion.div>
            )}
        </StudentLayout>
    );
}

function SectionTitle({
    icon: Icon,
    title,
    count,
}: {
    icon: LucideIcon;
    title: string;
    count?: number;
}) {
    return (
        <div className="mb-3 flex items-center gap-2">
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
    );
}
