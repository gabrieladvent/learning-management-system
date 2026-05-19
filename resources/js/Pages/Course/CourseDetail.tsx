import { Head, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { BookOpen, FileText } from 'lucide-react';
import { EmptyState } from '@/Components';
import { CourseHeader, MaterialListCard, type CourseDetailPageProps } from '@/Components/Course';
import { StudentLayout } from '@/Layouts';
import { staggerContainer } from '@/lib';
import { PageProps } from '@/types';

export default function CourseDetail() {
    const { props } = usePage<PageProps<CourseDetailPageProps>>();
    const { course, materials } = props;

    return (
        <StudentLayout title={course.subject_name ?? 'Mata Pelajaran'}>
            <Head title={course.subject_name ?? 'Mata Pelajaran'} />

            <CourseHeader course={course} backHref={route('student.dashboard')} backLabel="Dashboard" />

            <section>
                <SectionTitle
                    icon={BookOpen}
                    title="Materi Pembelajaran"
                    subtitle={
                        materials.length > 0
                            ? `${materials.length} blok pembelajaran — urut sesuai pengajaran guru`
                            : 'Belum ada materi yang dipublikasikan'
                    }
                />

                {materials.length === 0 ? (
                    <EmptyState
                        icon={FileText}
                        title="Belum ada materi"
                        description="Guru belum mempublikasikan materi pembelajaran untuk mata pelajaran ini."
                    />
                ) : (
                    <motion.div
                        initial="hidden"
                        animate="visible"
                        variants={staggerContainer}
                        className="grid grid-cols-1 gap-3"
                    >
                        {materials.map((material) => (
                            <MaterialListCard key={material.id} courseId={course.id} material={material} />
                        ))}
                    </motion.div>
                )}
            </section>
        </StudentLayout>
    );
}

function SectionTitle({
    icon: Icon,
    title,
    subtitle,
}: {
    icon: typeof BookOpen;
    title: string;
    subtitle: string;
}) {
    return (
        <div className="mb-4 flex items-end justify-between">
            <div className="flex items-center gap-3">
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                    <Icon className="h-4 w-4" />
                </div>
                <div>
                    <h2 className="text-base font-semibold tracking-tight text-slate-900">{title}</h2>
                    <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p>
                </div>
            </div>
        </div>
    );
}
