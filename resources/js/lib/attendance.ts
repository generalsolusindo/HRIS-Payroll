const LOCATION_PERMISSION_DENIED_MESSAGE =
    'Izin lokasi ditolak. Aktifkan izin lokasi di browser untuk absen.';

export const MAX_ACCURACY_METERS = 50;

/**
 * Continuously watches the device position and reports the best (lowest
 * accuracy-radius) fix seen so far — used to drive a live distance display
 * while the user is on the capture screen. The server independently
 * re-validates accuracy and radius on submit; this is for UI feedback only.
 */
export function watchBestPosition(
    onUpdate: (position: GeolocationPosition) => void,
    onError: (message: string) => void,
): () => void {
    if (!navigator.geolocation) {
        onError('Perangkat Anda tidak mendukung geolokasi.');
        return () => {};
    }

    let best: GeolocationPosition | undefined;

    const watchId = navigator.geolocation.watchPosition(
        (position) => {
            if (
                !Number.isFinite(position.coords.accuracy) ||
                position.coords.accuracy < 0
            ) {
                return;
            }
            if (!best || position.coords.accuracy <= best.coords.accuracy) {
                best = position;
                onUpdate(position);
            }
        },
        (error) => {
            // Only permission denial is fatal — position-unavailable and
            // timeout errors (2, 3) are often transient and can recover on
            // a later sample from the same watch, so stay quiet for those.
            if (error.code === 1) {
                onError(LOCATION_PERMISSION_DENIED_MESSAGE);
            }
        },
        { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 },
    );

    return () => navigator.geolocation.clearWatch(watchId);
}

/** Great-circle distance in meters, mirroring OfficeLocation::distanceInMetersFrom. */
export function distanceInMeters(
    latitude1: number,
    longitude1: number,
    latitude2: number,
    longitude2: number,
): number {
    const earthRadiusMeters = 6_371_000;
    const latitudeDelta = ((latitude2 - latitude1) * Math.PI) / 180;
    const longitudeDelta = ((longitude2 - longitude1) * Math.PI) / 180;

    const a =
        Math.sin(latitudeDelta / 2) ** 2 +
        Math.cos((latitude1 * Math.PI) / 180) *
            Math.cos((latitude2 * Math.PI) / 180) *
            Math.sin(longitudeDelta / 2) ** 2;

    return earthRadiusMeters * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

export type OfficeLocation = {
    id: number;
    name: string;
    latitude: number;
    longitude: number;
    radius_meter: number;
};

export function nearestOffice(
    officeLocations: OfficeLocation[],
    latitude: number,
    longitude: number,
): { office: OfficeLocation; distance: number } | null {
    if (officeLocations.length === 0) {
        return null;
    }

    return officeLocations
        .map((office) => ({
            office,
            distance: distanceInMeters(
                latitude,
                longitude,
                office.latitude,
                office.longitude,
            ),
        }))
        .sort((a, b) => a.distance - b.distance)[0];
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
