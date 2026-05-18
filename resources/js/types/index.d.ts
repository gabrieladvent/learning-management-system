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
}

export interface FlashMessages {
    success?: string | null;
    error?: string | null;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        student: Student | null;
    };
    flash: FlashMessages;
};
