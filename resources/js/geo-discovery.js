import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { getPosition } from './location';

// Leaflet's default marker images don't resolve through Vite; use simple circle markers instead.
const userIcon = L.divIcon({ className: '', html: '<div class="h-4 w-4 rounded-full bg-sky-500 ring-4 ring-sky-200"></div>', iconSize: [16, 16] });
const venueIcon = (label) =>
    L.divIcon({
        className: '',
        html: `<div class="whitespace-nowrap rounded-full bg-amber-500 px-2 py-0.5 text-xs font-bold text-white shadow">${label}</div>`,
        iconSize: null,
    });

/**
 * Home screen: map + radius slider + nearest venues list + filter engine.
 */
export function geoDiscovery(config) {
    return {
        lat: config.fallback.lat,
        lng: config.fallback.lng,
        usingFallback: false,
        radius: config.radius,
        loading: true,
        venues: [],
        top: [],
        topLoading: false,
        showFilters: false,
        filters: { brand: '', type_id: '', size: '', min_price: config.priceBounds[0], max_price: config.priceBounds[1] },
        searchMode: 'venues',
        map: null,
        layer: null,
        circle: null,
        userMarker: null,

        async init() {
            this.map = L.map(this.$refs.map, { zoomControl: true }).setView([this.lat, this.lng], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(this.map);
            this.layer = L.layerGroup().addTo(this.map);

            const pos = await getPosition(config.fallback);
            this.lat = pos.lat;
            this.lng = pos.lng;
            this.usingFallback = !!pos.fallback;
            this.userMarker = L.marker([this.lat, this.lng], { icon: userIcon }).addTo(this.map).bindPopup('You are here');
            this.refresh();

            this.$watch('radius', Alpine.debounce(() => this.refresh(), 300));
        },

        params(extra = {}) {
            return new URLSearchParams({ lat: this.lat, lng: this.lng, radius: this.radius, ...extra });
        },

        async refresh() {
            this.loading = true;
            try {
                const { data } = await window.axios.get(config.nearbyUrl + '?' + this.params({ size: this.filters.size }));
                this.venues = data.venues;
                this.draw();
            } finally {
                this.loading = false;
            }
        },

        draw() {
            this.layer.clearLayers();
            if (this.circle) this.circle.remove();
            this.circle = L.circle([this.lat, this.lng], { radius: this.radius * 1000, weight: 1, fillOpacity: 0.05 }).addTo(this.map);
            this.venues.forEach((v) => {
                const label = v.cheapest ? v.cheapest.price_formatted : '🍺';
                L.marker([v.lat, v.lng], { icon: venueIcon(label) })
                    .bindPopup(`<a href="${v.url}" class="font-semibold">${escapeHtml(v.name)}</a><br>${escapeHtml(v.suburb ?? '')}`)
                    .addTo(this.layer);
            });
            this.map.fitBounds(this.circle.getBounds(), { padding: [10, 10] });
        },

        async runFilter() {
            this.topLoading = true;
            try {
                const clean = Object.fromEntries(Object.entries(this.filters).filter(([, v]) => v !== '' && v !== null));
                const { data } = await window.axios.get(config.cheapestUrl + '?' + this.params(clean), {
                    headers: { Accept: 'application/json' },
                });
                this.top = data.results;
                this.refresh();
            } finally {
                this.topLoading = false;
            }
        },

        searchAction() {
            return this.searchMode === 'venues' ? config.venueSearchUrl : config.cheapestUrl;
        },

        km(m) {
            return m < 1000 ? `${m} m` : `${(m / 1000).toFixed(1)} km`;
        },
    };
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}
