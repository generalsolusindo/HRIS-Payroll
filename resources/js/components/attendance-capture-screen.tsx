import { ArrowLeft, Camera } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { AttendanceGeofenceMap } from '@/components/attendance-geofence-map';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useLiveFaceCheck } from '@/hooks/use-live-face-check';
import {
    MAX_ACCURACY_METERS,
    nearestOffice,
    watchBestPosition,
    type OfficeLocation,
} from '@/lib/attendance';
import {
    capturePhotoFromVideo,
    startCameraStream,
    stopCameraStream,
} from '@/lib/camera';
import { cn } from '@/lib/utils';

type Props = {
    type: 'masuk' | 'pulang';
    officeLocations: OfficeLocation[];
    submitting: boolean;
    faceVerificationEnabled: boolean;
    onCancel: () => void;
    onSubmit: (
        photo: File,
        position: GeolocationPosition,
        receipt: string | null,
    ) => void;
};

function formatClock(): string {
    return new Date().toLocaleTimeString('id-ID', {
        timeZone: 'Asia/Jakarta',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function AttendanceCaptureScreen({
    type,
    officeLocations,
    submitting,
    faceVerificationEnabled,
    onCancel,
    onSubmit,
}: Props) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const streamRef = useRef<MediaStream | null>(null);

    const [cameraReady, setCameraReady] = useState(false);
    const [cameraError, setCameraError] = useState<string | null>(null);
    const [position, setPosition] = useState<GeolocationPosition | null>(null);
    const [locationError, setLocationError] = useState<string | null>(null);
    const [clock, setClock] = useState(formatClock());

    const faceCheck = useLiveFaceCheck({
        enabled: faceVerificationEnabled && cameraReady && !cameraError,
        videoRef,
        action: type,
    });

    useEffect(() => {
        let cancelled = false;

        startCameraStream()
            .then((stream) => {
                if (cancelled) {
                    stopCameraStream(stream);
                    return;
                }
                streamRef.current = stream;
                if (videoRef.current) {
                    videoRef.current.srcObject = stream;
                }
                setCameraReady(true);
            })
            .catch((error: Error) => setCameraError(error.message));

        const stopWatch = watchBestPosition(
            (next) => setPosition(next),
            (message) => setLocationError(message),
        );

        const clockInterval = window.setInterval(
            () => setClock(formatClock()),
            1000,
        );

        return () => {
            cancelled = true;
            if (streamRef.current) {
                stopCameraStream(streamRef.current);
            }
            stopWatch();
            window.clearInterval(clockInterval);
        };
    }, []);

    function handleCancel() {
        if (streamRef.current) {
            stopCameraStream(streamRef.current);
        }
        onCancel();
    }

    function handleCapture() {
        if (
            !videoRef.current ||
            !position ||
            (faceVerificationEnabled && !faceCheck.isStable) ||
            submitting
        ) {
            return;
        }

        const receipt = faceCheck.receipt;

        capturePhotoFromVideo(videoRef.current)
            .then((photo) => onSubmit(photo, position, receipt))
            .catch((error: Error) => setCameraError(error.message));
    }

    const nearest = position
        ? nearestOffice(
              officeLocations,
              position.coords.latitude,
              position.coords.longitude,
          )
        : null;
    const accuracyReady =
        position !== null && position.coords.accuracy <= MAX_ACCURACY_METERS;
    const withinRadius =
        nearest !== null && nearest.distance <= nearest.office.radius_meter;
    const canSubmit =
        cameraReady &&
        accuracyReady &&
        (!faceVerificationEnabled || faceCheck.isStable) &&
        !submitting;

    const frameBorderClass = (() => {
        if (!faceVerificationEnabled) {
            return 'border-white/70';
        }
        switch (faceCheck.status) {
            case 'matched':
                return faceCheck.isStable
                    ? 'border-emerald-400'
                    : 'border-amber-400';
            case 'mismatch':
            case 'multiple_faces':
                return 'border-red-400';
            case 'checking':
            case 'no_face':
            case 'service_unavailable':
                return 'border-amber-400';
            default:
                return 'border-white/70';
        }
    })();

    const frameHint = (() => {
        if (!faceVerificationEnabled) {
            return 'Posisikan wajah di dalam bingkai';
        }
        switch (faceCheck.status) {
            case 'idle':
                return 'Posisikan wajah di dalam bingkai';
            case 'checking':
                return 'Memeriksa wajah…';
            case 'matched':
                return faceCheck.isStable
                    ? 'Wajah terverifikasi'
                    : 'Mengonfirmasi wajah…';
            default:
                return faceCheck.message ?? 'Memeriksa wajah…';
        }
    })();

    return (
        <div className="fixed inset-0 z-50 flex flex-col bg-background">
            <div className="flex items-center justify-between border-b px-4 py-3">
                <button
                    type="button"
                    onClick={handleCancel}
                    aria-label="Batal"
                    className="flex size-9 items-center justify-center rounded-full hover:bg-muted"
                >
                    <ArrowLeft className="size-5" />
                </button>
                <div className="font-semibold">
                    {type === 'masuk' ? 'Absen Masuk' : 'Absen Pulang'}
                </div>
                <div className="font-mono text-sm text-muted-foreground">
                    {clock} WIB
                </div>
            </div>

            <div className="flex-1 overflow-y-auto p-4">
                <div className="relative mx-auto aspect-3/4 max-w-sm overflow-hidden rounded-3xl bg-brand-navy">
                    {/* eslint-disable-next-line jsx-a11y/media-has-caption */}
                    <video
                        ref={videoRef}
                        autoPlay
                        muted
                        playsInline
                        className="h-full w-full -scale-x-100 object-cover"
                    />

                    {!cameraReady && !cameraError && (
                        <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 text-brand-navy-foreground">
                            <Spinner className="size-6" />
                            <p className="text-sm">Membuka kamera…</p>
                        </div>
                    )}

                    {cameraError && (
                        <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 px-6 text-center text-brand-navy-foreground">
                            <Camera className="size-6" />
                            <p className="text-sm">{cameraError}</p>
                        </div>
                    )}

                    {cameraReady && (
                        <>
                            <div
                                className={cn(
                                    'pointer-events-none absolute inset-x-10 inset-y-12 rounded-[50%] border-2 border-dashed transition-colors duration-300',
                                    frameBorderClass,
                                )}
                            />
                            <p className="absolute inset-x-0 bottom-4 px-6 text-center text-sm text-white/90">
                                {frameHint}
                            </p>
                        </>
                    )}
                </div>

                <div className="mx-auto mt-4 max-w-sm overflow-hidden rounded-3xl border">
                    <div className="relative">
                        <AttendanceGeofenceMap
                            office={nearest?.office ?? null}
                            userPosition={
                                position
                                    ? {
                                          latitude: position.coords.latitude,
                                          longitude: position.coords.longitude,
                                          accuracy: position.coords.accuracy,
                                      }
                                    : null
                            }
                            withinRadius={withinRadius}
                        />
                        {nearest && (
                            <span className="absolute top-2 right-2 rounded-full bg-background px-2 py-0.5 text-xs font-medium shadow">
                                ±{Math.round(nearest.distance)} m
                            </span>
                        )}
                    </div>
                    <div className="space-y-1 p-3 text-sm">
                        <div className="font-medium">
                            {nearest?.office.name ?? 'Mencari lokasi kantor…'}
                        </div>
                        {nearest && position && (
                            <div
                                className={cn(
                                    'text-xs',
                                    withinRadius
                                        ? 'text-emerald-600'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {withinRadius
                                    ? `Dalam area kantor · radius ${nearest.office.radius_meter} m`
                                    : `Di luar radius ${nearest.office.radius_meter} m`}{' '}
                                · akurasi ±
                                {Math.round(position.coords.accuracy)} m
                            </div>
                        )}
                        {!position && !locationError && (
                            <div className="text-xs text-muted-foreground">
                                Mencari lokasi…
                            </div>
                        )}
                        {locationError && (
                            <div
                                role="alert"
                                className="text-xs text-destructive"
                            >
                                {locationError}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <div className="border-t p-4">
                <Button
                    className="h-12 w-full rounded-full text-base font-semibold"
                    onClick={handleCapture}
                    disabled={!canSubmit}
                >
                    {submitting ? (
                        <>
                            <Spinner className="size-4" />
                            Mengirim…
                        </>
                    ) : (
                        <>
                            <Camera className="size-4" />
                            Ambil Foto &amp; Kirim
                        </>
                    )}
                </Button>
                {!submitting && cameraReady && !canSubmit && (
                    <p className="mt-2 text-center text-xs text-muted-foreground">
                        {!accuracyReady
                            ? 'Menunggu akurasi GPS yang cukup…'
                            : 'Menunggu wajah terverifikasi…'}
                    </p>
                )}
            </div>
        </div>
    );
}
