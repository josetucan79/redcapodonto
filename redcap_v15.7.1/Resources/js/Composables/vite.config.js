// vite.config.js
import { defineConfig } from 'vite'
import { resolve } from 'path'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
    build: {
        manifest: true,
        cssCodeSplit: true,
        lib: {
            // Could also be a dictionary or array of multiple entry points
            entry: resolve('./', 'src/libs.js'),
            name: 'composables',
            formats: ['es'],
            fileName(format, entryAlias) {
                // add the 'js' extension for compatibility with any webserver:
                // "Strict MIME type checking is enforced for module scripts per HTML spec"
                // return `${entryAlias}-[hash].${format}.js` // this version for hashing the file
                return `${entryAlias}.${format}.js`
            },
        },
        rollupOptions: {
            input: {
                libs: resolve(__dirname, 'src/libs.js'), // Entry point for Modal composable
                // Add more entry points for other composables as needed
            },
            // output: {
            //     dir: 'dist',
            //     entryFileNames: 'js/[name]-[hash].js',      // Output JavaScript files
            //     chunkFileNames: 'js/[name]-[hash].js',
            //     assetFileNames: (assetInfo) => {
            //         const assets = []
            //         assetInfo.names.forEach(name => {
            //             if (name.endsWith('.css')) {
            //                 assets.push('css/[name]-[hash][extname]'); // Output CSS files
            //             }
            //             else assets.push('assets/[name]-[hash][extname]');
            //         })
            //         return assets.join('-')
            //     },
            // },
        },
    },
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url)),
        },
    },
});