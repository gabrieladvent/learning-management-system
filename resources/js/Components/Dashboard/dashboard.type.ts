export interface Course {
    id: string;
    subject_name: string | null;
    subject_code: string | null;
    classroom_name: string;
    teacher_name: string | null;
    semester: number | null;
    academic_year: string | null;
}

export type DashboardMeta = {
    classroom_name: string | null;
    academic_year: string | null;
    inspire: string;
};

export type DashboardPageProps = {
    courses: Course[];
    meta: DashboardMeta;
};
