/**
 * HTML5 geolocation with a graceful fallback (Melbourne CBD by default,
 * configured server-side) and a short-lived sessionStorage cache so every
 * page doesn't re-prompt.
 */
const KEY = 'beercheck:pos';

export function getPosition(fallback, { timeout = 8000 } = {}) {
    const cached = sessionStorage.getItem(KEY);
    if (cached) {
        const pos = JSON.parse(cached);
        if (Date.now() - pos.at < 5 * 60 * 1000) return Promise.resolve({ ...pos, cached: true });
    }

    return new Promise((resolve) => {
        if (!('geolocation' in navigator)) {
            resolve({ ...fallback, fallback: true });
            return;
        }
        navigator.geolocation.getCurrentPosition(
            ({ coords }) => {
                const pos = { lat: coords.latitude, lng: coords.longitude, at: Date.now() };
                sessionStorage.setItem(KEY, JSON.stringify(pos));
                resolve(pos);
            },
            () => resolve({ ...fallback, fallback: true }),
            { enableHighAccuracy: false, timeout, maximumAge: 60000 },
        );
    });
}

export function clearPosition() {
    sessionStorage.removeItem(KEY);
}

/** For server-rendered pages that need ?lat&lng: add them to the URL once and reload. */
export async function ensureLocationInUrl(fallback) {
    const url = new URL(window.location.href);
    if (url.searchParams.has('lat') && url.searchParams.has('lng')) return;
    const pos = await getPosition(fallback);
    url.searchParams.set('lat', pos.lat.toFixed(5));
    url.searchParams.set('lng', pos.lng.toFixed(5));
    window.location.replace(url.toString());
}
