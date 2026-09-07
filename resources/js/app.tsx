import { createInertiaApp } from "@inertiajs/react";
import type { ComponentType, ReactNode } from "react";
import { createRoot } from "react-dom/client";

import { AdminLayout } from "@/layouts/AdminLayout";
import { AppLayout } from "@/layouts/AppLayout";
import { Providers } from "@/lib/providers";
import "../css/app.css";

type PageComponent = ComponentType<Record<string, unknown>> & {
  layout?: (page: ReactNode) => ReactNode;
};

const pages = import.meta.glob<{ default: PageComponent }>("./Pages/**/*.tsx", {
  eager: true,
});

createInertiaApp({
  title: (title) => (title ? `${title} — AmanaFinance` : "AmanaFinance"),
  resolve: (name) => {
    const page = pages[`./Pages/${name}.tsx`];
    if (!page) throw new Error(`Halaman Inertia tidak ditemukan: ${name}`);

    // Layout ditentukan dari nama halaman, bukan ditulis ulang di tiap file:
    // ini yang menggantikan layout per route-group Next (`(app)`, `admin/
    // (dashboard)`). Sebagai persistent layout Inertia, shell-nya tidak ikut
    // ter-remount saat pindah halaman — sidebar & tab bar tetap hidup.
    const component = page.default;
    if (component.layout === undefined) {
      if (name.startsWith("App/")) {
        component.layout = (p) => <AppLayout>{p}</AppLayout>;
      } else if (name.startsWith("Admin/") && name !== "Admin/Login") {
        component.layout = (p) => <AdminLayout>{p}</AdminLayout>;
      }
    }

    return component;
  },
  setup({ el, App, props }) {
    createRoot(el).render(
      <Providers>
        <App {...props} />
      </Providers>,
    );
  },
  progress: { color: "#75406f" },
});
