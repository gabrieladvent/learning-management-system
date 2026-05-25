export interface Course {
    id: string;
    subject_name: string | null;
    subject_code: string | null;
    classroom_name: string;
    teacher_name: string | null;
    semester: number | null;
    academic_year: string | null;
    is_pinned: boolean;
}

export type DashboardMeta = {
    classroom_name: string | null;
    academic_year: string | null;
    homeroom_teacher_name: string | null;
    semester: number | null;
    inspire: string;
};

export interface UpcomingExam {
    id: string;
    title: string;
    subject_name: string | null;
    starts_at: string | null;
    duration_minutes: number | null;
    url: string | null;
}

export interface StudentStats {
    assignments_pending: number;
    assignments_completed: number;
    exams_completed: number;
    avg_score: number | null;
    upcoming_exam: UpcomingExam | null;
}

export type DashboardPageProps = {
    courses: Course[];
    stats: StudentStats;
    meta: DashboardMeta;
};
