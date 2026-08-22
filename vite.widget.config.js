import { defineConfig } from 'vite';

/**
 * Standalone build for the embeddable chat widget.
 *
 * Kept out of the main Laravel/Vite build on purpose: third-party websites load
 * this by a fixed URL and cannot read our hashed manifest, so it must have a
 * stable filename, no code-splitting and no shared app chunks. Output lands in
 * public/widget/ and is served directly.
 */
export default defineConfig({
    // The output dir lives inside public/, so publicDir must be off or Vite copies
    // the whole of public/ into it.
    publicDir: false,
    build: {
        outDir: 'public/widget',
        emptyOutDir: true,
        target: 'es2019',
        lib: {
            entry: 'resources/js/widget/embed.ts',
            name: 'PiotrackChat',
            formats: ['iife'],
            fileName: () => 'piotrack-chat.js',
        },
        rollupOptions: {
            output: { inlineDynamicImports: true },
        },
    },
});
