import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useEffect, useRef } from 'react';
import { type OfficeLocation } from '@/lib/attendance';

type UserPosition = {
    latitude: number;
    longitude: number;
    accuracy: number;
};

type Props = {
    office: OfficeLocation | null;
    userPosition: UserPosition | null;
    withinRadius: boolean;
};

const OFFICE_ICON = L.divIcon({
    className: '',
    html: '<div style="width:16px;height:16px;border-radius:9999px;background:#1e2a5e;border:2px solid white;box-shadow:0 1px 3px rgba(0,0,0,0.4)"></div>',
    iconSize: [16, 16],
    iconAnchor: [8, 8],
});

function userIcon(withinRadius: boolean): L.DivIcon {
    const color = withinRadius ? '#10b981' : '#f59e0b';
    return L.divIcon({
        className: '',
        html: `<div style="position:relative;width:16px;height:16px">
            <div style="position:absolute;inset:-6px;border-radius:9999px;background:${color};opacity:0.25"></div>
            <div style="width:16px;height:16px;border-radius:9999px;background:${color};border:2px solid white;box-shadow:0 1px 3px rgba(0,0,0,0.4)"></div>
        </div>`,
        iconSize: [16, 16],
        iconAnchor: [8, 8],
    });
}

/**
 * A real, non-interactive OpenStreetMap view showing the office's geofence
 * radius and the employee's live GPS position — display only. The
 * server independently re-validates radius and accuracy on submit.
 */
export function AttendanceGeofenceMap({
    office,
    userPosition,
    withinRadius,
}: Props) {
    const containerRef = useRef<HTMLDivElement>(null);
    const mapRef = useRef<L.Map | null>(null);
    const officeLayerRef = useRef<{
        marker: L.Marker;
        circle: L.Circle;
    } | null>(null);
    const userLayerRef = useRef<L.Marker | null>(null);
    const hasFitBoundsRef = useRef(false);

    useEffect(() => {
        if (!containerRef.current || mapRef.current) {
            return;
        }

        const map = L.map(containerRef.current, {
            zoomControl: false,
            dragging: false,
            scrollWheelZoom: false,
            doubleClickZoom: false,
            boxZoom: false,
            keyboard: false,
            touchZoom: false,
            attributionControl: false,
        }).setView([-2.5, 118], 4);

        L.control.attribution({ prefix: false }).addTo(map);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap',
            maxZoom: 19,
        }).addTo(map);

        mapRef.current = map;

        return () => {
            map.remove();
            mapRef.current = null;
        };
    }, []);

    useEffect(() => {
        const map = mapRef.current;
        if (!map || !office) {
            return;
        }

        if (!officeLayerRef.current) {
            const marker = L.marker([office.latitude, office.longitude], {
                icon: OFFICE_ICON,
            }).addTo(map);
            const circle = L.circle([office.latitude, office.longitude], {
                radius: office.radius_meter,
                color: '#1e2a5e',
                weight: 1.5,
                fillOpacity: 0.08,
            }).addTo(map);
            officeLayerRef.current = { marker, circle };
            map.setView([office.latitude, office.longitude], 18);
        }
    }, [office]);

    useEffect(() => {
        const map = mapRef.current;
        if (!map || !userPosition) {
            return;
        }

        const latLng: L.LatLngTuple = [
            userPosition.latitude,
            userPosition.longitude,
        ];

        if (!userLayerRef.current) {
            userLayerRef.current = L.marker(latLng, {
                icon: userIcon(withinRadius),
            }).addTo(map);
        } else {
            userLayerRef.current.setLatLng(latLng);
            userLayerRef.current.setIcon(userIcon(withinRadius));
        }

        if (!hasFitBoundsRef.current && office) {
            map.fitBounds([[office.latitude, office.longitude], latLng], {
                padding: [30, 30],
                maxZoom: 18,
            });
            hasFitBoundsRef.current = true;
        }
        // withinRadius intentionally excluded — it only recolors the
        // existing marker and shouldn't trigger a bounds re-fit.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [userPosition, office]);

    return <div ref={containerRef} className="h-32 w-full" />;
}
