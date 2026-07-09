import { Link, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { BookOpen, LogOut } from 'lucide-react';
import { PropsWithChildren } from 'react';
import NotificationsDropdown from '@/Components/Layout/NotificationsDropdown';
import TodoSidePanelButton from '@/Components/Layout/TodoSidePanelButton';
import TrackingDisclosureBanner from '@/Components/Layout/TrackingDisclosureBanner';
import { PageProps } from '@/types';
import { pageTransition, useFlashToast } from '@/lib';

interface Props {
    title?: string;
}

export default function StudentLayout({ children, title }: PropsWithChildren<Props>) {
    const { auth } = usePage<PageProps>().props;
    const student = auth.student;

    useFlashToast();

    const handleLogout = () => {
        router.post(route('student.logout'));
    };

    return (
        <div className="min-h-screen bg-slate-50 text-slate-900">
            {student && <TrackingDisclosureBanner seenAt={student.tracking_disclosure_seen_at} />}
            <header className="sticky top-0 z-10 border-b border-slate-200 bg-white/80 backdrop-blur">
                <div className="mx-auto flex h-16 max-w-screen-2xl items-center justify-between px-6">
                    <Link
                        href={student ? route('student.dashboard') : route('student.login')}
                        className="flex items-center gap-2 text-slate-900"
                    >
                        <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-600 text-white">
                            <BookOpen className="h-4 w-4" />
                        </span>
                        <span className="text-base font-semibold tracking-tight">LMS Siswa</span>
                    </Link>

                    {student && (
                        <div className="flex items-center gap-3">
                            <Link
                                href={route('student.profile.edit')}
                                className="flex items-center gap-2 rounded-lg px-1.5 py-1 transition hover:bg-slate-100"
                                aria-label="Profil"
                            >
                                <div className="hidden text-right sm:block">
                                    <div className="text-sm font-medium leading-tight text-slate-900">
                                        {student.full_name}
                                    </div>
                                    <div className="text-xs text-slate-500">NISN {student.nisn}</div>
                                </div>
                                {student.avatar_url ? (
                                    <img
                                        src={student.avatar_url}
                                        alt={student.full_name}
                                        className="h-9 w-9 rounded-full object-cover ring-2 ring-slate-100"
                                    />
                                ) : (
                                    <span className="flex h-9 w-9 items-center justify-center rounded-full bg-sky-600 text-sm font-semibold text-white">
                                        {student.full_name.charAt(0).toUpperCase()}
                                    </span>
                                )}
                            </Link>
                            <TodoSidePanelButton />
                            <NotificationsDropdown />
                            <button
                                type="button"
                                onClick={handleLogout}
                                className="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:text-slate-900"
                                aria-label="Logout"
                            >
                                <LogOut className="h-4 w-4" />
                                <span className="hidden sm:inline">Keluar</span>
                            </button>
                        </div>
                    )}
                </div>
            </header>

            <motion.main
                key={title ?? 'page'}
                initial={pageTransition.initial}
                animate={pageTransition.animate}
                transition={pageTransition.transition}
                className="mx-auto max-w-screen-2xl px-6 py-10"
            >
                {children}
            </motion.main>
        </div>
    );
}
