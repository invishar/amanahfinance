import { createInertiaApp } from "@inertiajs/react";
import { createRoot, hydrateRoot } from "react-dom/client";

import { pageTitle, resolvePage } from "@/lib/inertia-pages";
import { Providers } from "@/lib/providers";
import "../css/app.css";

createInertiaApp({
  title: pageTitle,
  resolve: resolvePage,
  setup({ el, App, props }) {
    const tree = (
      <Providers>
        <App {...props} />
      </Providers>
    );

    // Dua jalur, satu entry: kalau server SSR hidup, <div id="app"> sudah
    // berisi markup dan React tinggal menempel padanya (hydrateRoot). Kalau
    // SSR mati atau gagal, Inertia mengirim div kosong dan kita render dari
    // nol seperti sebelum SSR ada — fallback-nya ada di sini, bukan cuma di
    // sisi PHP (lihat config/inertia.php).
    if (el.hasChildNodes()) {
      hydrateRoot(el, tree);
    } else {
      createRoot(el).render(tree);
    }
  },
  progress: { color: "#75406f" },
});
