import type { ActivityItem } from '@/Components/ActivityTimeline';
import type { MaterialFile } from '@/Components/FileCard';
import type { CourseSummary } from '@/Components/Course';

export type ExamMode = 'online_quiz' | 'submission';
export type ExamStatus = 'belum_mulai' | 'in_progress' | 'submitted' | 'graded';
export type QuestionType = 'multiple_choice' | 'short_answer' | 'essay';

/** Ringkasan exam di list (Section Ujian dalam Material Detail). */
export interface ExamListItem {
    id: string;
    title: string;
    description: string | null;
    mode: ExamMode;
    starts_at: string | null;
    duration_minutes: number;
    max_score: number | null;
    questions_count: number;
    status: ExamStatus;
    session_id: string | null;
    submitted_at: string | null;
    total_score: number | null;
}

/** Detail penuh exam di Exam Start / Submission page. */
export interface ExamDetail {
    id: string;
    title: string;
    description: string | null;
    mode: ExamMode;
    starts_at: string | null;
    duration_minutes: number;
    max_score: number | null;
    questions_count: number;
    shuffle_questions: boolean;
    allowed_file_types: string[];
    max_file_size_mb: number;
    available_until: string | null;
    status: ExamStatus;
}

export interface ExamSessionSummary {
    id: string;
    started_at: string | null;
    submitted_at: string | null;
    expires_at: string | null;
    total_score: number | null;
    answered_count: number;
}

export interface ExamSubmissionDetail {
    id: string;
    content: string | null;
    link_url: string | null;
    submitted_at: string | null;
    score: number | null;
    feedback: string | null;
    files: MaterialFile[];
}

export interface ExamMaterialRef {
    id: string;
    title: string;
    topic: string | null;
}

/** Soal di halaman pengerjaan. Tidak include correct_answer (anti-cheat). */
export interface ExamQuestionItem {
    id: string;
    type: QuestionType;
    question: string;
    options: Record<string, string> | string[] | [];
    score: number;
    files: MaterialFile[];
}

export type ExamStartPageProps = {
    course: CourseSummary;
    material: ExamMaterialRef;
    exam: ExamDetail;
    session: ExamSessionSummary | null;
    submission: ExamSubmissionDetail | null;
    activities: ActivityItem[];
};

export type ExamSubmissionFormPageProps = ExamStartPageProps;

export type ExamTakePageProps = {
    course: CourseSummary;
    material: ExamMaterialRef;
    exam: {
        id: string;
        title: string;
        mode: ExamMode;
        duration_minutes: number;
        max_score: number | null;
    };
    session: {
        id: string;
        started_at: string | null;
        submitted_at: string | null;
        expires_at: string | null;
    };
    questions: ExamQuestionItem[];
    answers: Record<string, string | null>;
    server_time: string;
};

export type ExamResultPageProps = ExamTakePageProps;
