import { describe, expect, it } from 'vitest';

import { compressImage, fitWithin, MAX_EDGE, renamed } from './images';

/**
 * Pictures are made smaller before they are sent from the chat. These pin
 * the arithmetic and the cases that must be left untouched; the drawing itself
 * needs a real browser and is checked there.
 */
describe('fitWithin', () => {
    it('scales a large photo down to the longest side, keeping its shape', () => {
        expect(fitWithin(4032, 3024)).toEqual({ width: MAX_EDGE, height: 1200 });
        expect(fitWithin(3024, 4032)).toEqual({ width: 1200, height: MAX_EDGE });
    });

    it('never enlarges a small picture', () => {
        expect(fitWithin(800, 600)).toEqual({ width: 800, height: 600 });
    });

    it('never rounds a thin picture away to nothing', () => {
        expect(fitWithin(10000, 2)).toEqual({ width: MAX_EDGE, height: 1 });
    });
});

describe('renamed', () => {
    it('swaps the extension for the new type', () => {
        expect(renamed('IMG_2041.JPG', 'webp')).toBe('IMG_2041.webp');
        expect(renamed('screenshot.final.png', 'jpg')).toBe('screenshot.final.jpg');
        expect(renamed('photo', 'webp')).toBe('photo.webp');
        expect(renamed('.png', 'webp')).toBe('image.webp');
    });
});

describe('compressImage', () => {
    it('leaves files that are not still pictures exactly as they are', async () => {
        const pdf = new File(['%PDF-1.4'], 'invoice.pdf', { type: 'application/pdf' });
        const gif = new File(['GIF89a'], 'spinner.gif', { type: 'image/gif' });

        expect(await compressImage(pdf)).toBe(pdf);
        expect(await compressImage(gif)).toBe(gif);
    });

    it('sends the original when the browser cannot read the picture', async () => {
        const broken = new File(['not really a jpeg'], 'broken.jpg', { type: 'image/jpeg' });

        expect(await compressImage(broken)).toBe(broken);
    });
});
