/**
 * Pictures a visitor sends in the chat are made smaller in their own browser
 * before upload: a phone photo is often 4-12 MB and 4000 pixels wide, far more
 * than a chat needs, and slow on a mobile connection. Re-drawing the picture
 * also drops its hidden metadata, such as the GPS position phones record.
 *
 * Animated GIFs are left alone (re-drawing keeps only the first frame), and so
 * is anything the browser cannot draw. A picture that would not get smaller is
 * sent as it is.
 */

/** Longest side of a picture after compression, in pixels. */
export const MAX_EDGE = 1600;

const QUALITY = 0.82;
const COMPRESSIBLE = ['image/jpeg', 'image/png', 'image/webp'];

/** The size to draw a picture at: its own, or scaled down to fit MAX_EDGE, keeping its shape. */
export function fitWithin(width: number, height: number, max = MAX_EDGE): { width: number; height: number } {
    const scale = Math.min(1, max / Math.max(width, height));
    return { width: Math.max(1, Math.round(width * scale)), height: Math.max(1, Math.round(height * scale)) };
}

/** The file name for a re-encoded picture: same name, new type's extension. */
export function renamed(name: string, extension: string): string {
    const base = name.replace(/\.[^./\\]+$/, '') || 'image';
    return `${base}.${extension}`;
}

function encode(canvas: HTMLCanvasElement, type: string): Promise<Blob | null> {
    return new Promise((resolve) => canvas.toBlob(resolve, type, QUALITY));
}

export async function compressImage(file: File): Promise<File> {
    if (!COMPRESSIBLE.includes(file.type) || typeof createImageBitmap !== 'function') return file;

    try {
        // Browsers apply the photo's own rotation when decoding, so a portrait
        // phone picture stays upright once its metadata is gone.
        const bitmap = await createImageBitmap(file);
        const size = fitWithin(bitmap.width, bitmap.height);
        const canvas = document.createElement('canvas');
        canvas.width = size.width;
        canvas.height = size.height;
        const context = canvas.getContext('2d');
        if (!context) return file;
        context.drawImage(bitmap, 0, 0, size.width, size.height);
        bitmap.close();

        // WebP keeps transparency and is smallest; a browser that cannot write
        // it hands back PNG instead, so fall back to JPEG on white.
        let blob = await encode(canvas, 'image/webp');
        let extension = 'webp';
        if (!blob || blob.type !== 'image/webp') {
            const flat = document.createElement('canvas');
            flat.width = size.width;
            flat.height = size.height;
            const paint = flat.getContext('2d');
            if (!paint) return file;
            paint.fillStyle = '#fff';
            paint.fillRect(0, 0, size.width, size.height);
            paint.drawImage(canvas, 0, 0);
            blob = await encode(flat, 'image/jpeg');
            extension = 'jpg';
        }

        if (!blob || blob.size >= file.size) return file;
        return new File([blob], renamed(file.name, extension), { type: blob.type, lastModified: Date.now() });
    } catch {
        return file;
    }
}
