import type { MaterialFile } from '@/Components/FileCard';

export interface CourseSummary {
    id: string;
    subject_name: string | null;
    subject_code: string | null;
    classroom_name: string | null;
    teacher_name: string | null;
    semester?: number | null;
    academic_year?: string | null;
}

export interface MaterialListItem {
    id: string;
    title: string;
    topic: string | null;
    description: string | null;
    order: number;
    available_from: string | null;
    created_at: string | null;
    has_files: boolean;
    has_link: boolean;
    has_content: boolean;
    assignment_count: number;
    exam_count: number;
}

export interface MaterialDetail {
    id: string;
    title: string;
    topic: string | null;
    description: string | null;
    content: string | null;
    link_url: string | null;
    available_from: string | null;
    available_until: string | null;
    created_at: string | null;
    files: MaterialFile[];
}

export type CourseDetailPageProps = {
    course: CourseSummary;
    materials: MaterialListItem[];
};

export type MaterialDetailPageProps = {
    course: CourseSummary;
    material: MaterialDetail;
};
