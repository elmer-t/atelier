import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from "@tailwindcss/vite";

// Remote fonts are resolved from fonts.bunny.net at build time. Some sandboxed
// build environments (e.g. Claude Code on the web) block that host, so allow the
// fetch to be skipped via ATELIER_SKIP_REMOTE_FONTS. Unset => normal behaviour.
const skipRemoteFonts = !! process.env.ATELIER_SKIP_REMOTE_FONTS;

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/passkeys.js',
            ],
            refresh: true,
            fonts: skipRemoteFonts ? [] : [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
