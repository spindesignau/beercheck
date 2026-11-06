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
