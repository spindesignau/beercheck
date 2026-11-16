/**
 * Browser location with sensible retries, memory and honest error reporting.
 *
 * Order of preference:
 *   1. a place the user picked by hand ("manual", until they tap "Use my location")
 *   2. a live fix: quick low-accuracy attempt, then a longer high-accuracy retry
 *   3. the last live fix we saw (localStorage, up to 30 days) — better than a default city
 *   4. the configured fallback (Melbourne CBD)
 *
 * Every result says where it came from (`source`) and, if the live fix failed,
 * why (`error`: denied | timeout | unavailable | unsupported | insecure), so the
 * UI can explain instead of silently showing Melbourne.
 */
const SESSION_KEY = 'beercheck:pos';     // fresh fix, reused for 5 minutes across pages
const LAST_KEY = 'beercheck:last';       // last live fix, reused for 30 days
const MANUAL_KEY = 'beercheck:manual';   // place picked by hand

const FIVE_MIN = 5 * 60 * 1000;
const THIRTY_DAYS = 30 * 24 * 60 * 60 * 1000;

const read = (store, key) => {
    try { return JSON.parse(store.getItem(key)); } catch { return null; }
};
const write = (store, key, value) => {
    try { store.setItem(key, JSON.stringify(value)); } catch { /* private mode */ }
};

function attempt(options) {
    return new Promise((resolve, reject) => navigator.geolocation.getCurrentPosition(resolve, reject, options));
}

function errorName(err) {
    if (!err || typeof err.code !== 'number') return 'unavailable';
    return { 1: 'denied', 2: 'unavailable', 3: 'timeout' }[err.code] ?? 'unavailable';
}

/** Live fix only: resolves {lat,lng,accuracy} or rejects with an error name. */
export async function liveFix() {
    if (!window.isSecureContext) throw 'insecure';
    if (!('geolocation' in navigator)) throw 'unsupported';

    try {
        // Fast path: network/Wi-Fi location, accept a fix up to 5 minutes old.
        const p = await attempt({ enableHighAccuracy: false, timeout: 10000, maximumAge: FIVE_MIN });
        return { lat: p.coords.latitude, lng: p.coords.longitude, accuracy: p.coords.accuracy };
    } catch (first) {
        if (errorName(first) === 'denied') throw 'denied';
        // Slow path: wake the GPS (first fix on a phone can take 15s+).
        try {
            const p = await attempt({ enableHighAccuracy: true, timeout: 20000, maximumAge: 0 });
            return { lat: p.coords.latitude, lng: p.coords.longitude, accuracy: p.coords.accuracy };
        } catch (second) {
            throw errorName(second);
        }
    }
}

/**
 * @param {{lat:number,lng:number}} fallback
 * @param {{force?: boolean}} opts force: ignore manual place + caches and ask the device again
 * @returns {Promise<{lat:number,lng:number,source:'gps'|'manual'|'saved'|'fallback',label?:string,error?:string}>}
 */
export async function getPosition(fallback, { force = false } = {}) {
    if (force) {
        localStorage.removeItem(MANUAL_KEY);
        sessionStorage.removeItem(SESSION_KEY);
    } else {
        const manual = read(localStorage, MANUAL_KEY);
        if (manual) return { ...manual, source: 'manual' };

        const fresh = read(sessionStorage, SESSION_KEY);
        if (fresh && Date.now() - fresh.at < FIVE_MIN) return { lat: fresh.lat, lng: fresh.lng, source: 'gps' };
    }

    try {
        const fix = await liveFix();
        const pos = { lat: fix.lat, lng: fix.lng, at: Date.now() };
        write(sessionStorage, SESSION_KEY, pos);
        write(localStorage, LAST_KEY, pos);
        return { lat: fix.lat, lng: fix.lng, source: 'gps' };
    } catch (error) {
        const last = read(localStorage, LAST_KEY);
        if (last && Date.now() - last.at < THIRTY_DAYS) {
            return { lat: last.lat, lng: last.lng, source: 'saved', error: String(error) };
        }
        return { lat: fallback.lat, lng: fallback.lng, source: 'fallback', error: String(error) };
    }
}

/** Use a place the user picked (suburb/postcode) until they ask for their live location again. */
export function setManualPosition(place) {
    const pos = { lat: place.lat, lng: place.lng, label: place.label };
    write(localStorage, MANUAL_KEY, pos);
    return { ...pos, source: 'manual' };
}

export function clearPosition() {
    sessionStorage.removeItem(SESSION_KEY);
    localStorage.removeItem(MANUAL_KEY);
}

/** For server-rendered pages that need ?lat&lng: add them to the URL once and reload. */
export async function ensureLocationInUrl(fallback) {
    const url = new URL(window.location.href);
    if (url.searchParams.has('lat') && url.searchParams.has('lng')) return;
    const pos = await getPosition(fallback);
    url.searchParams.set('lat', pos.lat.toFixed(5));
    url.searchParams.set('lng', pos.lng.toFixed(5));
    if (pos.source !== 'gps') url.searchParams.set('loc', pos.source);
    window.location.replace(url.toString());
}
