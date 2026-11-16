import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { getPosition, setManualPosition } from './location';

const youIcon = L.divIcon({ className: '', html: '<div class="bc-you"></div>', iconSize: [16, 16], iconAnchor: [8, 8] });
const priceIcon = (label, empty) =>
    L.divIcon({ className: '', html: `<div class="bc-marker ${empty ? 'bc-marker-empty' : ''}">${label}</div>`, iconSize: [0, 0] });

/** Home screen: nearby venues (list + map), quick size filter and an automatic Top 3. */
export function geoDiscovery(config) {
    return {
        lat: config.fallback.lat,
        lng: config.fallback.lng,
        usingFallback: false,
        locating: true,
        source: null,      // gps | manual | saved | fallback
        error: null,       // denied | timeout | unavailable | unsupported | insecure
        placeLabel: '',
        pickerOpen: false,
        placeQuery: '',
        places: [],
        radius: config.radius,
        size: '',
        view: 'list',
        loading: true,
        venues: [],
        hasMore: false,
        nextOffset: null,
        loadingMore: false,
        top: [],
        map: null,
        layer: null,
        circle: null,
        you: null,

        async init() {
            this.map = L.map(this.$refs.map, { zoomControl: false, attributionControl: false }).setView([this.lat, this.lng], 14);
            L.control.zoom({ position: 'topright' }).addTo(this.map);
            // Top-left so it never sits under the floating List/Map toggle.
            L.control.attribution({ position: 'topleft', prefix: false }).addTo(this.map);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap',
            }).addTo(this.map);
            this.layer = L.layerGroup().addTo(this.map);

            await this.locate(false);
            if (this.error) this.pickerOpen = false;
            this.$watch('radius', Alpine.debounce(() => this.refresh(), 350));
        },

        async locate(force = true) {
            this.locating = true;
            this.pickerOpen = false;
            const pos = await getPosition(config.fallback, { force });
            this.applyPosition(pos);
        },

        applyPosition(pos) {
            this.lat = pos.lat;
            this.lng = pos.lng;
            this.source = pos.source;
            this.error = pos.error ?? null;
            this.placeLabel = pos.label ?? '';
            this.usingFallback = pos.source === 'fallback';
            this.locating = false;
            if (this.you) this.you.remove();
            this.you = L.marker([this.lat, this.lng], { icon: youIcon, zIndexOffset: 1000 }).addTo(this.map);
            this.map.setView([this.lat, this.lng], 14);
            this.refresh();
        },

        locationLabel() {
            if (this.locating) return 'Finding you…';
            return {
                gps: 'Near you',
                manual: this.placeLabel || 'Chosen place',
                saved: 'Near your last location',
                fallback: 'Melbourne CBD',
            }[this.source] ?? 'Near you';
        },

        async searchPlaces() {
            const q = this.placeQuery.trim();
            if (q.length < 2) { this.places = []; return; }
            const { data } = await window.axios.get(config.placesUrl + '?' + new URLSearchParams({ q }));
            this.places = data;
        },

        pickPlace(place) {
            this.placeQuery = '';
            this.places = [];
            this.pickerOpen = false;
            this.applyPosition(setManualPosition(place));
        },

        /** iOS / Android specific help for a blocked location. */
        platform() {
            const ua = navigator.userAgent;
            if (/iPhone|iPad|iPod/.test(ua)) return 'ios';
            if (/Android/.test(ua)) return 'android';
            return 'desktop';
        },

        params(extra = {}) {
            const p = { lat: this.lat, lng: this.lng, radius: this.radius, ...extra };
            if (this.size) p.size = this.size;
            return new URLSearchParams(p);
        },

        setSize(size) {
            this.size = size;
            this.refresh();
        },

        show(view) {
            this.view = view;
            this.$nextTick(() => {
                if (view === 'map') {
                    this.map.invalidateSize();
                    this.fit();
                    this.$refs.map.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        },

        cheapestUrl() {
            return config.cheapestUrl + '?' + this.params();
        },

        async refresh() {
            this.loading = true;
            try {
                const [nearby, top] = await Promise.all([
                    window.axios.get(config.nearbyUrl + '?' + this.params()),
                    window.axios.get(config.cheapestUrl + '?' + this.params(), { headers: { Accept: 'application/json' } }),
                ]);
                this.venues = nearby.data.venues;
                this.hasMore = nearby.data.has_more;
                this.nextOffset = nearby.data.next_offset;
                this.top = top.data.results;
                this.draw();
            } finally {
                this.loading = false;
            }
        },

        async loadMore() {
            if (!this.hasMore || this.loadingMore) return;
            this.loadingMore = true;
            try {
                const { data } = await window.axios.get(config.nearbyUrl + '?' + this.params({ offset: this.nextOffset }));
                this.venues = this.venues.concat(data.venues);
                this.hasMore = data.has_more;
                this.nextOffset = data.next_offset;
                this.draw(false);
            } finally {
                this.loadingMore = false;
            }
        },

        draw(fit = true) {
            this.layer.clearLayers();
            if (this.circle) this.circle.remove();
            this.circle = L.circle([this.lat, this.lng], {
                radius: this.radius * 1000, weight: 1.5, color: '#B27800', fillColor: '#F7BB0E', fillOpacity: 0.06,
            }).addTo(this.map);
            this.venues.forEach((v) => {
                const label = v.cheapest ? v.cheapest.price_formatted : '?';
                L.marker([v.lat, v.lng], { icon: priceIcon(label, !v.cheapest) })
                    .bindPopup(`<a href="${v.url}" style="font-weight:800">${escapeHtml(v.name)}</a><br>${escapeHtml(v.suburb ?? '')}`
                        + (v.cheapest ? `<br>${escapeHtml(v.cheapest.beer)} · ${v.cheapest.size} <b>${v.cheapest.price_formatted}</b>` : ''))
                    .addTo(this.layer);
            });
            if (fit) this.fit();
        },

        fit() {
            if (this.circle) this.map.fitBounds(this.circle.getBounds(), { padding: [8, 8] });
        },

        km(m) {
            return m < 1000 ? `${m} m` : `${(m / 1000).toFixed(1)} km`;
        },
    };
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}
