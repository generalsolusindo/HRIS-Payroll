import { useEffect, useRef, useState } from 'react';
import FaceVerificationController from '@/actions/App/Http/Controllers/FaceVerificationController';
import { capturePhotoFromVideo } from '@/lib/camera';
import { getXsrfToken } from '@/lib/csrf';

export type FaceCheckStatus =
    | 'idle'
    | 'checking'
    | 'no_face'
    | 'multiple_faces'
    | 'mismatch'
    | 'matched'
    | 'service_unavailable';

type PreviewResponse = {
    status: FaceCheckStatus;
    message: string;
    expires_at: string | null;
    receipt?: string;
};

// Prototype values from docs/face-verification-plan.md §5 — not yet
// tuned against real network/worker latency in production.
const REQUIRED_CONSECUTIVE_MATCHES = 2;
const POLL_GAP_MS = 2000;
const RETRY_GAP_MS = 1000;

function isPreviewResponse(value: unknown): value is PreviewResponse {
    return (
        typeof value === 'object' &&
        value !== null &&
        'status' in value &&
        typeof (value as { status: unknown }).status === 'string'
    );
}

export function useLiveFaceCheck({
    enabled,
    videoRef,
    action,
}: {
    enabled: boolean;
    videoRef: React.RefObject<HTMLVideoElement | null>;
    action: 'masuk' | 'pulang';
}): {
    status: FaceCheckStatus;
    message: string | null;
    isStable: boolean;
    receipt: string | null;
} {
    const [status, setStatus] = useState<FaceCheckStatus>('idle');
    const [message, setMessage] = useState<string | null>(null);
    const [receipt, setReceipt] = useState<string | null>(null);
    const [expiresAt, setExpiresAt] = useState<number | null>(null);
    const [consecutiveMatches, setConsecutiveMatches] = useState(0);

    const attemptIdRef = useRef(crypto.randomUUID());
    const sequenceRef = useRef(0);
    const stoppedRef = useRef(true);
    const timeoutRef = useRef<number | null>(null);
    const controllerRef = useRef<AbortController | null>(null);

    useEffect(() => {
        stoppedRef.current = false;
        attemptIdRef.current = crypto.randomUUID();
        sequenceRef.current = 0;
        setStatus('idle');
        setMessage(null);
        setReceipt(null);
        setExpiresAt(null);
        setConsecutiveMatches(0);

        if (!enabled) {
            return;
        }

        function scheduleNext(delayMs: number) {
            if (stoppedRef.current) {
                return;
            }
            timeoutRef.current = window.setTimeout(
                () => void runCheck(),
                delayMs,
            );
        }

        async function runCheck() {
            if (stoppedRef.current) {
                return;
            }

            // The video not having a frame yet, or the tab being hidden,
            // are both transient — keep retrying on a short gap rather
            // than surfacing an error for either.
            if (!videoRef.current || videoRef.current.readyState < 2) {
                scheduleNext(RETRY_GAP_MS);
                return;
            }
            if (document.hidden) {
                setConsecutiveMatches(0);
                setReceipt(null);
                setExpiresAt(null);
                scheduleNext(RETRY_GAP_MS);
                return;
            }

            setStatus('checking');

            const controller = new AbortController();
            controllerRef.current = controller;

            try {
                const photo = await capturePhotoFromVideo(videoRef.current);
                const formData = new FormData();
                formData.append('photo', photo);
                formData.append('action', action);
                formData.append('attempt_id', attemptIdRef.current);
                formData.append('sequence', String(++sequenceRef.current));

                const response = await fetch(
                    FaceVerificationController.check.url(),
                    {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-XSRF-TOKEN': getXsrfToken() ?? '',
                        },
                        signal: controller.signal,
                    },
                );

                if (stoppedRef.current) {
                    return;
                }

                const data: unknown = await response.json();

                if (!isPreviewResponse(data)) {
                    setStatus('service_unavailable');
                    setMessage('Respons verifikasi wajah tidak valid.');
                    setReceipt(null);
                    setExpiresAt(null);
                    setConsecutiveMatches(0);
                    return;
                }

                setMessage(data.message);

                if (data.status === 'matched') {
                    setStatus('matched');
                    setReceipt(data.receipt ?? null);
                    setExpiresAt(
                        data.expires_at
                            ? new Date(data.expires_at).getTime()
                            : null,
                    );
                    setConsecutiveMatches((count) => count + 1);
                } else {
                    setStatus(data.status);
                    setReceipt(null);
                    setExpiresAt(null);
                    setConsecutiveMatches(0);
                }
            } catch (error) {
                if (stoppedRef.current) {
                    return;
                }
                if (
                    error instanceof DOMException &&
                    error.name === 'AbortError'
                ) {
                    return;
                }

                setStatus('service_unavailable');
                setMessage(
                    'Tidak dapat terhubung ke layanan verifikasi wajah.',
                );
                setReceipt(null);
                setExpiresAt(null);
                setConsecutiveMatches(0);
            } finally {
                scheduleNext(POLL_GAP_MS);
            }
        }

        void runCheck();

        return () => {
            stoppedRef.current = true;
            if (timeoutRef.current !== null) {
                window.clearTimeout(timeoutRef.current);
            }
            controllerRef.current?.abort();
        };
        // videoRef is a stable ref object — intentionally excluded.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [enabled, action]);

    // A hidden tab means the person stepped away from the camera — don't
    // let a stale "matched" state silently stay valid while they're gone.
    useEffect(() => {
        function handleVisibilityChange() {
            if (document.hidden) {
                setConsecutiveMatches(0);
                setReceipt(null);
                setExpiresAt(null);
            }
        }

        document.addEventListener('visibilitychange', handleVisibilityChange);
        return () =>
            document.removeEventListener(
                'visibilitychange',
                handleVisibilityChange,
            );
    }, []);

    // Local safety net so the "green" state doesn't outlive the receipt
    // even if, for whatever reason, the next poll is delayed — the
    // server independently re-checks expiry regardless either way.
    useEffect(() => {
        if (expiresAt === null) {
            return;
        }
        const delay = expiresAt - Date.now();
        if (delay <= 0) {
            setReceipt(null);
            return;
        }
        const timer = window.setTimeout(() => setReceipt(null), delay);
        return () => window.clearTimeout(timer);
    }, [expiresAt]);

    return {
        status,
        message,
        isStable:
            consecutiveMatches >= REQUIRED_CONSECUTIVE_MATCHES &&
            receipt !== null,
        receipt,
    };
}
