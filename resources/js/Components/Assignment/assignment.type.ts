import type { ActivityItem } from '@/Components/ActivityTimeline';
import type { MaterialFile } from '@/Components/FileCard';
import type { CourseSummary } from '@/Components/Course';

export type AssignmentStatus = 'pending' | 'submitted' | 'graded' | 'overdue';

/** Ringkasan assignment di list (Section Tugas dalam Material Detail). */
export interface AssignmentListItem {
    id: string;
    title: string;
    description: string | null;
    deadline: string | null;
    max_score: number | null;
    status: AssignmentStatus;
    is_overdue: boolean;
    submitted_at: string | null;
    score: number | null;
}

/** Detail penuh assignment di Assignment Detail page. */
export interface AssignmentDetail {
    id: string;
    title: string;
    description: string | null;
    deadline: string | null;
    max_score: number | null;
    allowed_file_types: string[];
    max_file_size_mb: number;
    is_overdue: boolean;
    status: AssignmentStatus;
    attachments: MaterialFile[];
}

export interface AssignmentSubmission {
    id: string;
    content: string | null;
    submitted_at: string | null;
    score: number | null;
    feedback: string | null;
    files: MaterialFile[];
}

export interface AssignmentMaterialRef {
    id: string;
    title: string;
    topic: string | null;
}

export type AssignmentDetailPageProps = {
    course: CourseSummary;
    material: AssignmentMaterialRef;
    assignment: AssignmentDetail;
    submission: AssignmentSubmission | null;
    activities: ActivityItem[];
};
