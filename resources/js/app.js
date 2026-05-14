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

    element._tripMap = map;

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 18,
    }).addTo(map);

    const bounds = [];

    points.forEach((point) => {
        const latLng = [point.lat, point.lng];

        bounds.push(latLng);

        L.marker(latLng)
            .addTo(map)
            .bindPopup(`<strong>${point.name}</strong><br>${point.category}`);
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
