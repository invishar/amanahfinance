// Entry Node untuk server-side rendering. Dibangun oleh `vite build --ssr`
// menjadi bootstrap/ssr/ssr.js, lalu dijalankan sebagai proses HTTP kecil di
// 127.0.0.1:13714 yang dipanggil Laravel per halaman (lihat config/inertia.php).
//
// Yang dirender di sini hanya kerangka: shared prop `auth` sudah diketahui
// server, sementara seluruh data bisnis tetap datang dari /api/v1 lewat
// TanStack Query setelah hidrasi. Jadi HTML hasil SSR = shell + status login,
// persis seperti render pertama di browser — itu syarat hidrasi tidak mismatch.
//
// Konsekuensi yang harus dijaga saat menambah komponen: apa pun yang menyentuh
// `window`/`document`/`localStorage` wajib berada di dalam useEffect atau event
// handler, tidak di badan komponen — di sini tidak ada DOM.

import { createInertiaApp } from "@inertiajs/react";
import createServer from "@inertiajs/react/server";
import { renderToString } from "react-dom/server";

import { pageTitle, resolvePage } from "@/lib/inertia-pages";
import { Providers } from "@/lib/providers";

createServer((page) =>
  createInertiaApp({
    page,
    render: renderToString,
    title: pageTitle,
    resolve: resolvePage,
    setup: ({ App, props }) => (
      <Providers>
        <App {...props} />
      </Providers>
    ),
  }),
);
