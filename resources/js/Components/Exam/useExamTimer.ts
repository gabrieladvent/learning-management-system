import { useEffect, useRef, useState } from 'react';

interface UseExamTimerOptions {
    /** ISO string — kapan timer habis (server time). */
    expiresAt: string | null;
    /** ISO string — server time saat halaman dimuat. Dipakai untuk hitung clock offset. */
    serverTime: string;
    /** Callback dipanggil sekali saat timer mencapai 0. */
    onExpire?: () => void;
}

/**
 * Hook countdown ujian dengan koreksi clock client.
 *
 * Idenya: server kirim `server_time` saat render, kita simpan offset `client - server`.
 * Tiap tick, "now() di waktu server" = `clientNow - offset`. Sisa = `expiresAt - serverNow`.
 *
 * Mencegah student bermain dengan clock OS lalu refresh.
 */
export function useExamTimer({ expiresAt, serverTime, onExpire }: UseExamTimerOptions) {
    const offsetMsRef = useRef(0);
    const firedRef = useRef(false);
    const [remainingMs, setRemainingMs] = useState<number>(() => initialRemaining(expiresAt, serverTime));

    useEffect(() => {
        // offset = client - server (positif kalau client lebih cepat dari server)
        offsetMsRef.current = Date.now() - new Date(serverTime).getTime();
        firedRef.current = false;

        const tick = () => {
            if (!expiresAt) {
                setRemainingMs(0);

                return;
            }
            const serverNow = Date.now() - offsetMsRef.current;
            const remaining = Math.max(0, new Date(expiresAt).getTime() - serverNow);
            setRemainingMs(remaining);

            if (remaining <= 0 && !firedRef.current) {
                firedRef.current = true;
                onExpire?.();
            }
        };

        tick();
        const id = window.setInterval(tick, 500);

        return () => window.clearInterval(id);
    }, [expiresAt, serverTime, onExpire]);

    const totalSeconds = Math.floor(remainingMs / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return {
        remainingMs,
        totalSeconds,
        minutes,
        seconds,
        formatted: `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`,
        isExpired: remainingMs <= 0,
        isCritical: totalSeconds <= 60,
    };
}

function initialRemaining(expiresAt: string | null, serverTime: string): number {
    if (!expiresAt) return 0;
    const offset = Date.now() - new Date(serverTime).getTime();
    const serverNow = Date.now() - offset;

    return Math.max(0, new Date(expiresAt).getTime() - serverNow);
}
