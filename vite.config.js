import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig(({ mode }) => {
    const DEV = mode === 'development';

    return {
        publicDir: './www/scripts',
        resolve: {
            alias: {
                '@': resolve(__dirname, 'assets/js'),
                '~': resolve(__dirname, 'node_modules'),
            },
        },
        base: '/dist/',
        server: {
            open: false,
            hmr: false,
        },
        css: {
            postcss: [
                "autoprefixer"
            ]
        },
        build: {
            manifest: true,
            assetsDir: '',
            outDir: './www/dist',
            emptyOutDir: true,
            minify: DEV ? false : 'esbuild',
            rollupOptions: {
                output: {
                    manualChunks: undefined,
                    chunkFileNames: '[name].js',
                    entryFileNames: '[name].js',
                    assetFileNames: '[name].[ext]',
                },
                input: {
                    datagrid: './www/scripts/datagrid.js'
                }
            }
        },
    }
});