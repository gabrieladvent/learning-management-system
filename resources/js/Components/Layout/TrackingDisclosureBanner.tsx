import { router } from '@inertiajs/react';
import { Info, X } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Props {
    seenAt: string | null;
}

/**
 * One-time banner — tampil sekali per akun siswa sampai `tracking_disclosure_seen_at` ter-isi.
 * Sesuai docs/11 §8.1: disclosure (bukan consent — consent dikumpulkan offline oleh sekolah).
 */
export default function TrackingDisclosureBanner({ seenAt }: Props) {
    const [visible, setVisible] = useState<boolean>(false);

    useEffect(() => {
        setVisible(!seenAt);
    }, [seenAt]);

    if (!visible) return null;

    const handleDismiss = () => {
        setVisible(false);
        router.post(
            route('student.progress.disclosure-seen'),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                only: ['auth'],
            },
        );
    };

    return (
        <div className="border-b border-sky-200 bg-sky-50">
            <div className="mx-auto flex max-w-screen-2xl items-start gap-3 px-6 py-3 text-sm text-sky-900">
                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-700">
                    <Info className="h-4 w-4" />
                </span>
                <div className="flex-1">
                    <p className="font-medium">Aktivitas belajar Anda dicatat untuk evaluasi dan penelitian.</p>
                    <p className="mt-0.5 text-sky-800/80">
                        Hanya durasi & ringkasan aktivitas yang disimpan. Tidak ada isi jawaban atau materi yang
                        di-track. Hubungi admin sekolah jika ada pertanyaan.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={handleDismiss}
                    className="inline-flex h-7 items-center gap-1 rounded-md px-2 text-sky-700 transition hover:bg-sky-100"
                    aria-label="Saya mengerti"
                >
                    <span className="text-xs font-medium">Mengerti</span>
                    <X className="h-3.5 w-3.5" />
                </button>
            </div>
        </div>
    );
}
