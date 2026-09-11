import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';

// Aset dibangun ke `public/build/` (gitignored, seperti seluruh isi public/).
// Alias `@` sengaja sama persis dengan yang dipakai aplikasi Next asalnya,
// supaya file yang diadopsi tidak perlu diubah jalur import-nya.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            // Entry kedua khusus Node: `vite build --ssr` memakai ini dan
            // menaruh hasilnya di bootstrap/ssr/ssr.js (default plugin),
            // jalur yang sama dengan yang ditunjuk config/inertia.php.
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    // Bundle SSR dibuat berdiri sendiri: dependensi ikut dibundel, bukan
    // di-`require` dari node_modules saat runtime. Di hPanel proses SSR
    // dijalankan langsung (`node bootstrap/ssr/ssr.js`) dan node_modules di
    // sana cuma sisa build — bundle yang self-contained bikin proses itu
    // tidak mati kalau node_modules dipangkas/dibersihkan setelah deploy.
    ssr: {
        noExternal: true,
    },
});
