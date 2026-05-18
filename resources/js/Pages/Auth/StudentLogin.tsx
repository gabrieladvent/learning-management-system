import { Head, useForm } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { BookOpen, Eye, EyeOff, IdCard, KeyRound, Loader2 } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { toast, useFlashToast } from '@/shared/lib';

type FormData = {
    nisn: string;
    password: string;
};

export default function StudentLogin() {
    const { data, setData, post, processing } = useForm<FormData>({
        nisn: '',
        password: '',
    });
    const [showPassword, setShowPassword] = useState(false);

    useFlashToast();

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('student.login.attempt'), {
            onError: (formErrors) => {
                const message = formErrors.nisn || formErrors.password;
                if (message) {
                    toast.error(message);
                }
            },
        });
    };

    return (
        <>
            <Head title="Masuk Siswa" />
            <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-50 px-6 py-12">
                <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_-10%,rgba(14,165,233,0.12),transparent_50%),radial-gradient(circle_at_85%_110%,rgba(99,102,241,0.10),transparent_45%)]" />

                <motion.div
                    initial={{ opacity: 0, y: 16 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.4, ease: [0.22, 1, 0.36, 1] }}
                    className="relative w-full max-w-md"
                >
                    <div className="mb-8 text-center">
                        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-600 text-white shadow-sm shadow-sky-200">
                            <BookOpen className="h-5 w-5" />
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight text-slate-900">
                            Masuk Portal Siswa
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Gunakan NISN dan password kamu untuk masuk.
                        </p>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <label htmlFor="nisn" className="mb-1.5 block text-sm font-medium text-slate-700">
                                    NISN
                                </label>
                                <div className="relative">
                                    <IdCard className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                    <input
                                        id="nisn"
                                        type="text"
                                        inputMode="numeric"
                                        autoComplete="username"
                                        autoFocus
                                        value={data.nisn}
                                        onChange={(e) => setData('nisn', e.target.value.trim())}
                                        placeholder="Contoh: 0012345001"
                                        className="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
                                    />
                                </div>
                            </div>

                            <div>
                                <label htmlFor="password" className="mb-1.5 block text-sm font-medium text-slate-700">
                                    Password
                                </label>
                                <div className="relative">
                                    <KeyRound className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                    <input
                                        id="password"
                                        type={showPassword ? 'text' : 'password'}
                                        autoComplete="current-password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        placeholder="Password kamu"
                                        className="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-10 pr-10 text-sm text-slate-900 placeholder:text-slate-400 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword((v) => !v)}
                                        className="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1.5 text-slate-400 hover:text-slate-600"
                                        aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
                                    >
                                        {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                    </button>
                                </div>
                            </div>

                            <motion.button
                                type="submit"
                                disabled={processing}
                                whileTap={{ scale: 0.98 }}
                                className="mt-2 flex w-full items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-70"
                            >
                                {processing ? (
                                    <>
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                        Memverifikasi…
                                    </>
                                ) : (
                                    'Masuk'
                                )}
                            </motion.button>
                        </form>
                    </div>

                    <p className="mt-6 text-center text-xs text-slate-500">
                        Lupa NISN atau password? Hubungi wali kelas atau admin sekolah.
                    </p>
                </motion.div>
            </div>
        </>
    );
}
