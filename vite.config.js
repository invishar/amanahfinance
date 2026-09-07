import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

// Aset Inertia dibangun ke `public/build/` (gitignored, seperti seluruh isi
// public/). Ini terpisah dari static export Next.js di `frontend/` yang juga
// mendarat di public/ — lihat catatan di routes/web.php.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
});
