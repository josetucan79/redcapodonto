import { fileURLToPath, URL } from 'node:url'
import { resolve } from 'path'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import externalGlobals from 'rollup-plugin-external-globals'

// https://vitejs.dev/config/
export default defineConfig({
    build: {
        sourcemap: true,
        lib: {
            // Could also be a dictionary or array of multiple entry points
            entry: resolve('./', 'src/lib.js'),
            name: 'VueComponents',
            cssFileName: 'style',
            formats: ['es', 'umd'],
            fileName(format, entryAlias) {
                // add the 'js' extension for compatibility with any webserver:
                // "Strict MIME type checking is enforced for module scripts per HTML spec"
                return `${entryAlias}.${format}.js`
            },
        },
        rollupOptions: {
            // make sure to externalize deps that shouldn't be bundled
            // into your library
            // external: ['moment'],
            output: {
                // Provide global variables to use in the UMD build
                // for externalized deps
                globals: {
                    vue: 'Vue',
                    // moment: 'moment',
                },
            },
            plugins: [
                externalGlobals({
                    bootstrap: 'bootstrap',
                }),
            ],
        },
    },
    define: {
        'process.env': {},
    },
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url)),
        },
    },
    server: {
        watch: {
            usePolling: false,
        },
        // https: true,
        proxy: {
            '/redcap': {
                target: 'http://redcap.test/',
                ws: false,
                changeOrigin: true,
                rewrite: (path) => path.replace('^/redcap', ''),
            },
            '/api': {
                target: 'http://redcap.test/API_PROXY/index.php',
                changeOrigin: true,
                ws: false,
                rewrite: (path) => path.replace('^/api', ''),
            },
        },
    },
})
