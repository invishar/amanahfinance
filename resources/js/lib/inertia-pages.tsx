// Bagian createInertiaApp yang identik di kedua entry: app.tsx (browser) dan
// ssr.tsx (Node). Dipisah ke sini supaya resolusi halaman + pemasangan layout
// tidak ditulis dua kali — kalau berbeda sedikit saja, markup hasil SSR tidak
// akan cocok dengan render pertama di klien dan hidrasi gagal.

import type { ComponentType, ReactNode } from "react";

import { AdminLayout } from "@/layouts/AdminLayout";
import { AppLayout } from "@/layouts/AppLayout";

type PageComponent = ComponentType<Record<string, unknown>> & {
  layout?: (page: ReactNode) => ReactNode;
};

// `eager: true` dipertahankan (bukan lazy import) supaya build SSR juga
// menghasilkan satu bundle Node yang tidak perlu resolve chunk saat runtime.
const pages = import.meta.glob<{ default: PageComponent }>("../Pages/**/*.tsx", {
  eager: true,
});

export function resolvePage(name: string): PageComponent {
  const page = pages[`../Pages/${name}.tsx`];
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
}

export function pageTitle(title: string): string {
  return title ? `${title} — AmanaFinance` : "AmanaFinance";
}
