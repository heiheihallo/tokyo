import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

window.renderTripMap = (element, payload) => {
    if (!element || !payload) {
        return;
    }

    if (element._tripMap) {
        element._tripMap.remove();
        element._tripMap = null;
    }

    const points = payload.points ?? [];
    const routes = payload.routes ?? [];
    const map = L.map(element, { scrollWheelZoom: false });
    const colors = {
        stay: '#0f766e',
        accommodation: '#0f766e',
        move: '#0284c7',
        travel: '#0284c7',
        food: '#be123c',
        foodspot: '#be123c',
        activity: '#b45309',
        buffer: '#52525b',
        route: '#0f766e',
    };
    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    const markerIcon = (point) => {
        const color = colors[String(point.category ?? '').toLowerCase()] ?? '#0f766e';
        const size = point.selected ? 18 : 12;

        return L.divIcon({
            className: '',
            html: `<span style="display:block;width:${size}px;height:${size}px;border-radius:9999px;background:${color};border:3px solid white;box-shadow:0 1px 8px rgb(39 39 42 / 35%);"></span>`,
            iconSize: [size, size],
            iconAnchor: [size / 2, size / 2],
        });
    };

    element._tripMap = map;

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 18,
    }).addTo(map);

    const bounds = [];

    points.forEach((point) => {
        const latLng = [point.lat, point.lng];

        bounds.push(latLng);

        L.marker(latLng, { icon: markerIcon(point), zIndexOffset: point.selected ? 1000 : 0 })
            .addTo(map)
            .bindPopup(`<strong>${escapeHtml(point.name)}</strong><br>${escapeHtml(point.category)}`);
    });

    routes.forEach((route) => {
        if (route.length < 2) {
            return;
        }

        L.polyline(route, {
            color: '#0f766e',
            opacity: 0.72,
            weight: 3,
        }).addTo(map);
    });

    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [24, 24] });
    } else {
        map.setView([35.6812, 139.7671], 6);
    }
};
