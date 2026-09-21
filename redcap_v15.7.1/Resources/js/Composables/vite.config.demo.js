// vite.config.js
import { defineConfig } from 'vite'
import { resolve } from 'path'
import { fileURLToPath, URL } from 'node:url'
import vue from '@vitejs/plugin-vue'

import path from 'path';

// Get the relative folder path where the build is run
const currentPath = path.basename(process.cwd()); // Extracts current

const fullPath = path.resolve(__dirname);
const pathParts = fullPath.split(path.sep); // Split by system's path separator
const relativePath = pathParts.slice(-4).join(path.sep); // Get the meaningful parts

const outDir = 'dist-demo'

export default defineConfig({
    root: resolve(__dirname, 'demo'),
    base: '/redcap_v999.0.0/Resources/js/Composables/dist-demo',
    build: {
        outDir: '../dist-demo', // Output relative to the demo folder
        emptyOutDir: true, // Clear the output directory before building
        manifest: true,
        cssCodeSplit: true,
    },

    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url)),
        },
    },
    plugins: [vue()],
});