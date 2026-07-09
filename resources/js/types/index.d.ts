export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export interface Student {
    id: string;
    nisn: string;
    full_name: string;
    class: string | null;
    avatar_url: string | null;
    tracking_opt_out: boolean;
    tracking_disclosure_seen_at: string | null;
}

export interface FlashMessages {
    success?: string | null;
    error?: string | null;
}

export interface StudentNotification {
    id: string;
    data: {
        type: string;
        title: string;
        body?: string | null;
        url?: string | null;
        [key: string]: unknown;
    };
    read_at: string | null;
    created_at: string | null;
}

export interface NotificationsPayload {
    unread_count: number;
    recent: StudentNotification[];
}

export interface SharedTodoItem {
    kind: 'assignment' | 'exam';
    state: 'pending' | 'overdue' | 'upcoming' | 'available';
    id: string;
    title: string;
    subject_name: string | null;
    deadline: string | null;
    starts_at: string | null;
    available_from: string | null;
    available_until: string | null;
    is_today: boolean;
    is_within_week: boolean;
    url: string | null;
}

export interface SharedTodoPayload {
    today: SharedTodoItem[];
    this_week: SharedTodoItem[];
    later: SharedTodoItem[];
    count_this_week: number;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        student: Student | null;
    };
    flash: FlashMessages;
    notifications: NotificationsPayload | null;
    todo: SharedTodoPayload | null;
};
