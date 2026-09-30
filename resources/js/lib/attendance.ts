const GEOLOCATION_ERROR_MESSAGES: Record<number, string> = {
    1: 'Izin lokasi ditolak. Aktifkan izin lokasi di browser untuk absen.',
    2: 'Lokasi tidak dapat dideteksi. Coba lagi di area terbuka.',
    3: 'Waktu permintaan lokasi habis. Coba lagi.',
};

export function getCurrentPosition(): Promise<GeolocationPosition> {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('Perangkat Anda tidak mendukung geolokasi.'));
            return;
        }

        navigator.geolocation.getCurrentPosition(
            resolve,
            (error) =>
                reject(
                    new Error(
                        GEOLOCATION_ERROR_MESSAGES[error.code] ??
                            'Gagal mendapatkan lokasi.',
                    ),
                ),
            { enableHighAccuracy: true, timeout: 15000 },
        );
    });
}

/**
 * Opens the device camera directly (not the photo gallery) via a
 * throwaway file input, so a selfie can't be swapped for an old photo.
 * Must be called synchronously from a user gesture (e.g. a button's
 * onClick) or some browsers will silently refuse to open the camera.
 */
export function captureSelfie(): Promise<File> {
    return new Promise((resolve, reject) => {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.capture = 'user';

        input.onchange = () => {
            const file = input.files?.[0];

            if (file) {
                resolve(file);
            } else {
                reject(new Error('Foto tidak diambil.'));
            }
        };

        input.click();
    });
}

export function buildAttendanceFormData(
    photo: File,
    position: GeolocationPosition,
): FormData {
    const formData = new FormData();

    formData.append('latitude', String(position.coords.latitude));
    formData.append('longitude', String(position.coords.longitude));
    formData.append('accuracy', String(position.coords.accuracy));
    formData.append('photo', photo);

    return formData;
}
