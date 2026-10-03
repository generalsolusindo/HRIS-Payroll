import { Head, router } from '@inertiajs/react';
import { Camera, CheckCircle2, ScanFace } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import FaceProfileController from '@/actions/App/Http/Controllers/Settings/FaceProfileController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import {
    capturePhotoFromVideo,
    startCameraStream,
    stopCameraStream,
} from '@/lib/camera';

type Props = {
    hasFaceEnrolled: boolean;
    faceEnrolledAt: string | null;
};

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString('id-ID', {
        timeZone: 'Asia/Jakarta',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function EnrollDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const [cameraReady, setCameraReady] = useState(false);
    const [cameraError, setCameraError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        let cancelled = false;
        setCameraReady(false);
        setCameraError(null);

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

        return () => {
            cancelled = true;
            if (streamRef.current) {
                stopCameraStream(streamRef.current);
                streamRef.current = null;
            }
        };
    }, [open]);

    function handleCapture() {
        if (!videoRef.current || submitting) {
            return;
        }

        setSubmitting(true);

        capturePhotoFromVideo(videoRef.current)
            .then((photo) => {
                const formData = new FormData();
                formData.append('photo', photo);

                router.post(FaceProfileController.store.url(), formData, {
                    forceFormData: true,
                    onSuccess: () => {
                        onOpenChange(false);
                    },
                    onError: (errors) => {
                        const message = Object.values(errors)[0];
                        toast.error(message ?? 'Gagal mendaftarkan wajah.');
                    },
                    onFinish: () => setSubmitting(false),
                });
            })
            .catch((error: Error) => {
                setCameraError(error.message);
                setSubmitting(false);
            });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Daftarkan Wajah</DialogTitle>
                    <DialogDescription>
                        Posisikan wajah Anda di tengah bingkai dengan
                        pencahayaan yang cukup, lalu ambil foto.
                    </DialogDescription>
                </DialogHeader>

                <div className="relative mx-auto aspect-square w-full max-w-xs overflow-hidden rounded-3xl bg-brand-navy">
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
                        <div className="pointer-events-none absolute inset-8 rounded-full border-2 border-dashed border-white/70" />
                    )}
                </div>

                <DialogFooter>
                    <Button
                        onClick={handleCapture}
                        disabled={!cameraReady || submitting}
                        className="rounded-full"
                    >
                        {submitting ? (
                            <>
                                <Spinner className="size-4" />
                                Mengirim…
                            </>
                        ) : (
                            <>
                                <Camera className="size-4" />
                                Ambil Foto
                            </>
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function Face({ hasFaceEnrolled, faceEnrolledAt }: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);

    return (
        <>
            <Head title="Verifikasi wajah" />

            <h1 className="sr-only">Verifikasi wajah</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Verifikasi Wajah"
                    description="Dipakai untuk mencocokkan wajah Anda saat absen. Foto wajah Anda tidak disimpan — hanya data numerik hasil pemrosesan yang tersimpan terenkripsi."
                />

                <div className="flex items-center gap-4 rounded-2xl border p-4">
                    <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-muted">
                        {hasFaceEnrolled ? (
                            <CheckCircle2 className="size-5 text-emerald-600" />
                        ) : (
                            <ScanFace className="size-5 text-muted-foreground" />
                        )}
                    </span>
                    <div className="min-w-0 flex-1">
                        <div className="text-sm font-medium">
                            {hasFaceEnrolled
                                ? 'Wajah sudah terdaftar'
                                : 'Wajah belum didaftarkan'}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {hasFaceEnrolled && faceEnrolledAt
                                ? `Terdaftar pada ${formatDateTime(faceEnrolledAt)}`
                                : 'Daftarkan wajah Anda agar dapat diverifikasi otomatis saat absen.'}
                        </div>
                    </div>
                    <Button
                        variant={hasFaceEnrolled ? 'outline' : 'default'}
                        className="rounded-full"
                        onClick={() => setDialogOpen(true)}
                    >
                        {hasFaceEnrolled ? 'Daftar Ulang' : 'Daftar Wajah'}
                    </Button>
                </div>
            </div>

            <EnrollDialog open={dialogOpen} onOpenChange={setDialogOpen} />
        </>
    );
}
