import { defineConfig } from 'vite';
import nette from '@nette/vite-plugin';

export default defineConfig({
    plugins: [
        nette({
            entry: 'app.js',
        }),
    ],

    build: {
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            output: {
                entryFileNames: `[name].js`,
                chunkFileNames: `[name].js`,
                assetFileNames: `[name].[ext]`,
            },
        },
    },
});