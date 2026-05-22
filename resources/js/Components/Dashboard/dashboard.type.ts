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

export type DashboardPageProps = {
    courses: Course[];
    meta: DashboardMeta;
};
