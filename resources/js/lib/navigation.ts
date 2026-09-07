// Shim tipis menggantikan `next/navigation`. Ada supaya halaman & komponen
// yang diadopsi dari aplikasi Next tidak perlu diubah isinya — cuma jalur
// import-nya. Di balik layar semuanya router Inertia.

import { router, usePage } from "@inertiajs/react";

/** Path halaman saat ini, tanpa query string / hash. */
export function usePathname(): string {
  const { url } = usePage();
  return url.split("?")[0].split("#")[0] || "/";
}

export interface AppRouter {
  push: (href: string) => void;
  replace: (href: string) => void;
  back: () => void;
  refresh: () => void;
}

export function useRouter(): AppRouter {
  return {
    push: (href) => router.visit(href),
    // `replace` di Next tidak menambah entri history; padanannya di Inertia
    // adalah opsi `replace`, bukan method tersendiri.
    replace: (href) => router.visit(href, { replace: true }),
    back: () => window.history.back(),
    refresh: () => router.reload(),
  };
}
