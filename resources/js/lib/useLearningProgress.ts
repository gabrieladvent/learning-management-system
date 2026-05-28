import { useEffect, useRef } from 'react';

/**
 * Frontend probe untuk learning-progress-tracking Fase B.
 *
 * Tugas:
 * - Generate session_id UUID v4 per-tab (sessionStorage).
 * - Listen visibilitychange / pagehide → emit focus/blur/close.
 * - Heartbeat tiap HEARTBEAT_INTERVAL_MS saat tab aktif.
 * - Idle detector: tanpa interaksi selama IDLE_THRESHOLD_MS → emit 'idle'; interaksi setelahnya → emit 'focus'.
 * - Buffer event lokal, flush ke /student/progress/heartbeat. Pakai sendBeacon di pagehide.
 *
 * Catatan keselarasan dengan backend (docs/11 §5):
 * - Endpoint menerima max 50 event/request → kita auto-flush kalau buffer ≥ FLUSH_THRESHOLD_EVENTS.
 * - Payload max 32KB → kita split kalau ukuran serialized melebihi PAYLOAD_MAX_BYTES.
 * - Drift ≤ 10 menit → occurred_at di-generate dari clock browser saat event terjadi.
 * - Tidak retry pada 4xx (data opsional, hilang sebagian OK).
 */

export type TrackableType = 'material' | 'assignment' | 'exam';

type EventName = 'open' | 'focus' | 'blur' | 'heartbeat' | 'idle' | 'close';

interface BufferedEvent {
    event: EventName;
    occurred_at: string;
}

interface HeartbeatPayload {
    session_id: string;
    trackable_type: TrackableType;
    trackable_id: string;
    events: BufferedEvent[];
}

interface Options {
    enabled?: boolean;
}

const HEARTBEAT_INTERVAL_MS = 20_000;
const IDLE_THRESHOLD_MS = 60_000;
const FLUSH_THRESHOLD_EVENTS = 40; // jaga jarak aman dari server cap (50/request)
const PAYLOAD_MAX_BYTES = 30 * 1024; // server cap 32 KB; buffer kecil di sini

function generateUuidV4(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    // Fallback non-cryptographic; cukup untuk identifikasi sesi (bukan keamanan).
    const bytes = new Uint8Array(16);
    if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
        crypto.getRandomValues(bytes);
    } else {
        for (let i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
    }
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

function getSessionId(trackableType: TrackableType, trackableId: string): string {
    if (typeof window === 'undefined') return generateUuidV4();
    const key = `lp:session:${trackableType}:${trackableId}`;
    try {
        const existing = window.sessionStorage.getItem(key);
        if (existing) return existing;
        const fresh = generateUuidV4();
        window.sessionStorage.setItem(key, fresh);
        return fresh;
    } catch {
        // sessionStorage diblokir (mode privat dll) — fallback ke fresh tiap mount.
        return generateUuidV4();
    }
}

export function useLearningProgress(
    trackableType: TrackableType | null,
    trackableId: string | null | undefined,
    options: Options = {},
): void {
    const enabled = options.enabled !== false;

    // Refs supaya callback yang dipasang di window listener gak ke-stale-closure.
    const bufferRef = useRef<BufferedEvent[]>([]);
    const sessionIdRef = useRef<string>('');
    const lastInteractionRef = useRef<number>(Date.now());
    const isIdleRef = useRef<boolean>(false);
    const isVisibleRef = useRef<boolean>(true);
    const heartbeatTimerRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const idleTimerRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const csrfTokenRef = useRef<string>('');

    useEffect(() => {
        if (!enabled || !trackableType || !trackableId) return;
        if (typeof window === 'undefined' || typeof document === 'undefined') return;

        sessionIdRef.current = getSessionId(trackableType, trackableId);

        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        csrfTokenRef.current = tokenMeta?.getAttribute('content') ?? '';

        const endpoint = (() => {
            try {
                return route('student.progress.heartbeat');
            } catch {
                return '/student/progress/heartbeat';
            }
        })();

        const enqueue = (event: EventName) => {
            bufferRef.current.push({
                event,
                occurred_at: new Date().toISOString(),
            });

            if (bufferRef.current.length >= FLUSH_THRESHOLD_EVENTS) {
                void flush({ useBeacon: false });
            }
        };

        const drainBuffer = (): BufferedEvent[] => {
            const events = bufferRef.current;
            bufferRef.current = [];
            return events;
        };

        const buildPayload = (events: BufferedEvent[]): HeartbeatPayload => ({
            session_id: sessionIdRef.current,
            trackable_type: trackableType,
            trackable_id: trackableId,
            events,
        });

        const flush = async ({ useBeacon }: { useBeacon: boolean }) => {
            const events = drainBuffer();
            if (events.length === 0) return;

            // Pecah jadi chunk biar payload tetap ≤ ~30KB. Untuk event lifecycle sederhana
            // (~80 byte serialized) ini hampir selalu masuk dalam 1 chunk, tapi guard tetap ada.
            const chunks = chunkEvents(events);

            for (const chunk of chunks) {
                const payload = buildPayload(chunk);
                const body = JSON.stringify(payload);

                if (useBeacon && typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function') {
                    const blob = new Blob([body], { type: 'application/json' });
                    const ok = navigator.sendBeacon(endpoint, blob);
                    if (ok) continue;
                    // sendBeacon fail (mis. quota) — fallback fetch keepalive.
                }

                try {
                    await fetch(endpoint, {
                        method: 'POST',
                        credentials: 'same-origin',
                        keepalive: useBeacon, // pagehide path: keepalive supaya request gak dibatalkan
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...(csrfTokenRef.current ? { 'X-CSRF-TOKEN': csrfTokenRef.current } : {}),
                        },
                        body,
                    });
                } catch {
                    // Network drop → data tracking opsional, drop event chunk ini. (Spec §5.1)
                }
            }
        };

        // First event saat mount.
        enqueue('open');

        const handleVisibility = () => {
            const visible = document.visibilityState === 'visible';
            if (visible === isVisibleRef.current) return;

            isVisibleRef.current = visible;
            if (visible) {
                lastInteractionRef.current = Date.now();
                isIdleRef.current = false;
                enqueue('focus');
            } else {
                enqueue('blur');
                void flush({ useBeacon: false });
            }
        };

        const handlePageHide = () => {
            enqueue('close');
            void flush({ useBeacon: true });
        };

        const handleInteraction = () => {
            lastInteractionRef.current = Date.now();
            if (isIdleRef.current && isVisibleRef.current) {
                isIdleRef.current = false;
                enqueue('focus');
            }
        };

        document.addEventListener('visibilitychange', handleVisibility);
        window.addEventListener('pagehide', handlePageHide);
        window.addEventListener('beforeunload', handlePageHide);

        const interactionEvents: Array<keyof WindowEventMap> = ['pointermove', 'keydown', 'scroll', 'wheel', 'touchstart'];
        interactionEvents.forEach((name) => {
            window.addEventListener(name, handleInteraction, { passive: true });
        });

        // Heartbeat interval saat tab visible.
        heartbeatTimerRef.current = setInterval(() => {
            if (!isVisibleRef.current) return;
            if (isIdleRef.current) return;
            enqueue('heartbeat');
            void flush({ useBeacon: false });
        }, HEARTBEAT_INTERVAL_MS);

        // Idle detector — cek tiap 5 detik.
        idleTimerRef.current = setInterval(() => {
            if (!isVisibleRef.current || isIdleRef.current) return;
            if (Date.now() - lastInteractionRef.current >= IDLE_THRESHOLD_MS) {
                isIdleRef.current = true;
                enqueue('idle');
            }
        }, 5_000);

        return () => {
            // Unmount = navigasi internal Inertia atau page change. Treat sebagai "close" sesi probe ini.
            enqueue('close');
            void flush({ useBeacon: true });

            document.removeEventListener('visibilitychange', handleVisibility);
            window.removeEventListener('pagehide', handlePageHide);
            window.removeEventListener('beforeunload', handlePageHide);
            interactionEvents.forEach((name) => {
                window.removeEventListener(name, handleInteraction);
            });

            if (heartbeatTimerRef.current !== null) {
                clearInterval(heartbeatTimerRef.current);
                heartbeatTimerRef.current = null;
            }
            if (idleTimerRef.current !== null) {
                clearInterval(idleTimerRef.current);
                idleTimerRef.current = null;
            }
        };
    }, [enabled, trackableType, trackableId]);
}

function chunkEvents(events: BufferedEvent[]): BufferedEvent[][] {
    const chunks: BufferedEvent[][] = [];
    let current: BufferedEvent[] = [];
    let currentSize = 2; // accounting for outer braces/keys overhead approx

    for (const e of events) {
        const eventSize = JSON.stringify(e).length + 1;
        if (current.length >= FLUSH_THRESHOLD_EVENTS || currentSize + eventSize > PAYLOAD_MAX_BYTES) {
            chunks.push(current);
            current = [];
            currentSize = 2;
        }
        current.push(e);
        currentSize += eventSize;
    }

    if (current.length > 0) chunks.push(current);
    return chunks;
}
