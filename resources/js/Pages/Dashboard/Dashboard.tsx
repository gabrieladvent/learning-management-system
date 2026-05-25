import { Head, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { BookMarked, Pin } from 'lucide-react';
import {
    CourseCard,
    DashboardPageProps,
    HeroGreeting,
    StatsRow,
    TodoSection,
    UpcomingExamCard,
} from '@/Components/Dashboard';
import { EmptyState } from '@/Components';
import { StudentLayout } from '@/Layouts';
import { staggerContainer } from '@/lib';
import { PageProps } from '@/types';
import type { Course } from '@/Components/Dashboard';

export default function Dashboard() {
    const { props } = usePage<PageProps<DashboardPageProps>>();
    const { courses, meta, auth, todo, stats } = props;
    const student = auth.student;
    const todayItems = todo?.today ?? [];

    const pinnedCourses = courses.filter((c) => c.is_pinned);
    const otherCourses = courses.filter((c) => !c.is_pinned);

    const firstName = student?.full_name.split(' ')[0] ?? 'Siswa';

    const handleCourseClick = (course: Course) =>
        router.visit(route('student.courses.show', { course: course.id }));

    return (
        <StudentLayout title="Dashboard">
            <Head title="Dashboard" />

            <HeroGreeting
                name={firstName}
                fullName={student?.full_name ?? 'Siswa'}
                classroomName={meta.classroom_name}
                academicYear={meta.academic_year}
                courseCount={courses.length}
                homeroomTeacherName={meta.homeroom_teacher_name}
                semester={meta.semester}
                inspire={meta.inspire}
            />

            <StatsRow stats={stats} />

            {stats.upcoming_exam && <UpcomingExamCard exam={stats.upcoming_exam} />}

            {pinnedCourses.length > 0 && (
                <section className="mb-10">
                    <SectionHeader
                        icon={<Pin className="h-4 w-4 fill-current text-sky-600" />}
                        title="Mata Pelajaran Tersemat"
                        subtitle={`${pinnedCourses.length} mata pelajaran tersemat`}
                    />
                    <CourseGrid courses={pinnedCourses} onCourseClick={handleCourseClick} />
                </section>
            )}

            <section className="mb-10">
                <SectionHeader
                    title="Untuk Hari Ini"
                    subtitle={
                        todayItems.length > 0
                            ? `${todayItems.length} tugas/ujian dengan batas hari ini`
                            : 'Tidak ada tugas atau ujian yang batasnya hari ini'
                    }
                />
                <TodoSection
                    items={todayItems}
                    emptyMessage="Tidak ada tugas atau ujian dengan batas hari ini. Cek tombol To-Do di header untuk daftar lengkap."
                />
            </section>

            <section>
                <SectionHeader
                    title="Mata Pelajaran"
                    subtitle={
                        otherCourses.length > 0
                            ? `${otherCourses.length} mata pelajaran${pinnedCourses.length > 0 ? ' lainnya' : ' semester ini'}`
                            : pinnedCourses.length > 0
                              ? 'Semua mata pelajaran sudah tersemat di atas'
                              : 'Daftar mata pelajaran yang kamu ikuti'
                    }
                />

                {courses.length === 0 ? (
                    <EmptyState
                        icon={BookMarked}
                        title="Belum ada mata pelajaran"
                        description="Mata pelajaran akan muncul di sini setelah wali kelas menambahkanmu ke kelas."
                    />
                ) : otherCourses.length === 0 ? null : (
                    <CourseGrid courses={otherCourses} onCourseClick={handleCourseClick} />
                )}
            </section>
        </StudentLayout>
    );
}

function CourseGrid({ courses, onCourseClick }: { courses: Course[]; onCourseClick: (c: Course) => void }) {
    return (
        <motion.div
            initial="hidden"
            animate="visible"
            variants={staggerContainer}
            className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
        >
            {courses.map((course) => (
                <CourseCard key={course.id} course={course} onClick={() => onCourseClick(course)} />
            ))}
        </motion.div>
    );
}

function SectionHeader({
    title,
    subtitle,
    icon,
}: {
    title: string;
    subtitle: string;
    icon?: React.ReactNode;
}) {
    return (
        <div className="mb-4 flex items-end justify-between">
            <div>
                <h2 className="flex items-center gap-2 text-base font-semibold tracking-tight text-slate-900">
                    {icon}
                    {title}
                </h2>
                <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p>
            </div>
        </div>
    );
}
