import { motion } from 'framer-motion';
import { ArrowUpRight, User } from 'lucide-react';
import { fadeUp } from '@/shared/lib/motion';
import { getSubjectStyle } from '@/features/Dashboard/helpers/subjects';
import { Course } from '@/features/Dashboard/types';

interface Props {
    course: Course;
    onClick?: () => void;
}

export default function CourseCard({ course, onClick }: Props) {
    const style = getSubjectStyle(course.subject_code);
    const Icon = style.icon;

    return (
        <motion.button
            type="button"
            onClick={onClick}
            variants={fadeUp}
            whileHover={{ y: -3 }}
            transition={{ duration: 0.2, ease: [0.22, 1, 0.36, 1] }}
            className={`group relative flex w-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-sm transition-colors ${style.hoverBorder}`}
        >
            <div className="flex items-start justify-between gap-3">
                <div className={`flex h-11 w-11 items-center justify-center rounded-xl ${style.surface} ${style.accent} transition-transform group-hover:scale-105`}>
                    <Icon className="h-5 w-5" />
                </div>
                <span className="flex h-8 w-8 items-center justify-center rounded-full text-slate-300 transition-all group-hover:bg-slate-100 group-hover:text-slate-600">
                    <ArrowUpRight className="h-4 w-4 transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5" />
                </span>
            </div>

            <div className="mt-5">
                <h3 className="text-base font-semibold tracking-tight text-slate-900">
                    {course.subject_name ?? 'Tanpa Mata Pelajaran'}
                </h3>
                <div className="mt-1.5 flex items-center gap-1.5 text-sm text-slate-500">
                    <User className="h-3.5 w-3.5 shrink-0" />
                    <span className="truncate">{course.teacher_name ?? 'Tanpa Guru'}</span>
                </div>
            </div>

            <div className="mt-5 flex items-center gap-2 border-t border-slate-100 pt-3 text-xs font-medium">
                <span className="rounded-md bg-slate-100 px-2 py-0.5 text-slate-600">
                    {course.classroom_name}
                </span>
                {course.semester && (
                    <span className="rounded-md bg-slate-100 px-2 py-0.5 text-slate-600">
                        Sem {course.semester}
                    </span>
                )}
                {course.academic_year && (
                    <span className="ml-auto text-slate-400">{course.academic_year}</span>
                )}
            </div>
        </motion.button>
    );
}
