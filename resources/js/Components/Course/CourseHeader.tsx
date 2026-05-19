import { Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { ArrowLeft, User } from 'lucide-react';
import { getSubjectStyle } from '@/Components/Dashboard/subjects';
import type { CourseSummary } from './course.type';

interface Props {
    course: CourseSummary;
    backHref?: string;
    backLabel?: string;
}

export default function CourseHeader({ course, backHref, backLabel = 'Kembali' }: Props) {
    const style = getSubjectStyle(course.subject_code);
    const Icon = style.icon;

    return (
        <motion.header
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.3, ease: [0.22, 1, 0.36, 1] }}
            className="mb-8"
        >
            {backHref && (
                <Link
                    href={backHref}
                    className="mb-4 inline-flex items-center gap-1.5 text-sm text-slate-500 transition-colors hover:text-slate-900"
                >
                    <ArrowLeft className="h-4 w-4" />
                    {backLabel}
                </Link>
            )}

            <div className="flex items-start gap-4">
                <div className={`flex h-12 w-12 items-center justify-center rounded-2xl ${style.surface} ${style.accent}`}>
                    <Icon className="h-6 w-6" />
                </div>
                <div className="min-w-0 flex-1">
                    <h1 className="text-2xl font-semibold tracking-tight text-slate-900">
                        {course.subject_name ?? 'Tanpa Mata Pelajaran'}
                    </h1>
                    <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500">
                        {course.classroom_name && (
                            <span className="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                {course.classroom_name}
                            </span>
                        )}
                        {course.semester != null && (
                            <span className="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                Semester {course.semester}
                            </span>
                        )}
                        {course.academic_year && (
                            <span className="text-xs text-slate-400">{course.academic_year}</span>
                        )}
                    </div>
                    {course.teacher_name && (
                        <div className="mt-2 inline-flex items-center gap-1.5 text-sm text-slate-600">
                            <User className="h-3.5 w-3.5 text-slate-400" />
                            {course.teacher_name}
                        </div>
                    )}
                </div>
            </div>
        </motion.header>
    );
}
