import { getPosition } from './location';

/**
 * Camera / upload -> client-side compression (max 1600px, JPEG q=0.7) before
 * upload, plus a live points preview. The server re-encodes and calculates
 * points itself regardless (never trust the client).
 */
export function priceForm(config) {
    return {
        preview: null,
        compressing: false,
        hasPhoto: false,
        review: config.review || '',
        lat: null,
        lng: null,

        async init() {
            const pos = await getPosition(config.fallback);
            this.lat = pos.lat;
            this.lng = pos.lng;
        },

        earn() {
            const p = config.points;
            return p.price_posted
                + (this.hasPhoto ? p.photo_uploaded : 0)
                + (this.review.trim().length >= p.review_min_chars ? p.review_written : 0);
        },

        async onPhoto(event) {
            const input = event.target;
            const file = input.files?.[0];
            if (!file || !file.type.startsWith('image/')) return;

            this.compressing = true;
            this.hasPhoto = true;
            try {
                const blob = await compress(file, config.maxWidth, config.quality);
                const out = new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' });
                const dt = new DataTransfer();
                dt.items.add(out);
                input.files = dt.files;
                this.preview = URL.createObjectURL(out);
            } catch (e) {
                // Fall back to the original file; the server will still compress it.
                this.preview = URL.createObjectURL(file);
            } finally {
                this.compressing = false;
            }
        },
    };
}

async function compress(file, maxWidth, quality) {
    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    const scale = Math.min(1, maxWidth / bitmap.width);
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    return new Promise((resolve, reject) => canvas.toBlob((b) => (b ? resolve(b) : reject()), 'image/jpeg', quality));
}
