/**
 * Opens the front camera as a live in-page preview. Must be called from a
 * user gesture (e.g. a button's onClick) or some browsers will refuse it.
 */
export async function startCameraStream(): Promise<MediaStream> {
    if (!navigator.mediaDevices?.getUserMedia) {
        throw new Error('Perangkat/browser Anda tidak mendukung kamera.');
    }

    try {
        return await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'user',
                width: { ideal: 720 },
                height: { ideal: 960 },
            },
            audio: false,
        });
    } catch {
        throw new Error(
            'Tidak dapat mengakses kamera. Periksa izin kamera di browser.',
        );
    }
}

export function stopCameraStream(stream: MediaStream): void {
    stream.getTracks().forEach((track) => track.stop());
}

/** Captures the current frame of a live camera preview as a JPEG file. */
export function capturePhotoFromVideo(video: HTMLVideoElement): Promise<File> {
    return new Promise((resolve, reject) => {
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const context = canvas.getContext('2d');

        if (!context) {
            reject(new Error('Gagal memproses foto.'));
            return;
        }

        // Mirror horizontally so the saved photo matches what the user saw
        // in the front-camera preview.
        context.translate(canvas.width, 0);
        context.scale(-1, 1);
        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        canvas.toBlob(
            (blob) => {
                if (!blob) {
                    reject(new Error('Gagal memproses foto.'));
                    return;
                }
                resolve(new File([blob], 'selfie.jpg', { type: 'image/jpeg' }));
            },
            'image/jpeg',
            0.9,
        );
    });
}
