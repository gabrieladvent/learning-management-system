import { Head, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { BookMarked, ClipboardList, FileSpreadsheet } from 'lucide-react';
import { CourseCard, DashboardPageProps, HeroGreeting } from '@/Components/Dashboard';
import { EmptyState } from '@/Components';
import { StudentLayout } from '@/Layouts';
import { staggerContainer } from '@/lib';
import { PageProps } from '@/types';

export default function Dashboard() {
    const { props } = usePage<PageProps<DashboardPageProps>>();
    const { courses, meta, auth } = props;
    const student = auth.student;

    const firstName = student?.full_name.split(' ')[0] ?? 'Siswa';

    return (
        <StudentLayout title="Dashboard">
            <Head title="Dashboard" />

            <HeroGreeting
                name={firstName}
                classroomName={meta.classroom_name}
                academicYear={meta.academic_year}
                courseCount={courses.length}
            />

            <section className="mb-10">
                <SectionHeader
                    title="Mata Pelajaran"
                    subtitle={
                        courses.length > 0
                            ? `${courses.length} mata pelajaran semester ini`
                            : 'Daftar mata pelajaran yang kamu ikuti'
                    }
                />

                {courses.length === 0 ? (
                    <EmptyState
                        icon={BookMarked}
                        title="Belum ada mata pelajaran"
                        description="Mata pelajaran akan muncul di sini setelah wali kelas menambahkanmu ke kelas."
                    />
                ) : (
                    <motion.div
                        initial="hidden"
                        animate="visible"
                        variants={staggerContainer}
                        className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        {courses.map((course) => (
                            <CourseCard
                                key={course.id}
                                course={course}
                                onClick={() => router.visit(route('student.courses.show', { course: course.id }))}
                            />
                        ))}
                    </motion.div>
                )}
            </section>

            <section>
                <SectionHeader
                    title="Aktivitas Mendatang"
                    subtitle="Tugas dan ujian akan tampil di sini setelah fitur tersedia"
                />
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <PlaceholderCard icon={ClipboardList} title="Tugas" description="Belum ada tugas yang ditugaskan." />
                    <PlaceholderCard icon={FileSpreadsheet} title="Ujian" description="Belum ada ujian terjadwal." />
                </div>
            </section>
        </StudentLayout>
    );
}

function SectionHeader({ title, subtitle }: { title: string; subtitle: string }) {
    return (
        <div className="mb-4 flex items-end justify-between">
            <div>
                <h2 className="text-base font-semibold tracking-tight text-slate-900">{title}</h2>
                <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p>
            </div>
        </div>
    );
}

function PlaceholderCard({
    icon: Icon,
    title,
    description,
}: {
    icon: typeof BookMarked;
    title: string;
    description: string;
}) {
    return (
        <motion.div
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.3 }}
            className="flex items-start gap-4 rounded-2xl border border-dashed border-slate-200 bg-white p-5"
        >
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-slate-400">
                <Icon className="h-5 w-5" />
            </div>
            <div>
                <h3 className="text-sm font-semibold text-slate-700">{title}</h3>
                <p className="mt-0.5 text-sm text-slate-500">{description}</p>
            </div>
        </motion.div>
    );
}
