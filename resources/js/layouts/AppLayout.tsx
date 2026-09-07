import type { ReactNode } from "react";

import { AppShell } from "@/components/app-shell";
import { RequireSession } from "@/components/require-session";

/**
 * Padanan `app/(app)/layout.tsx` di aplikasi Next. Dipasang sekali sebagai
 * persistent layout Inertia (lihat resources/js/app.tsx), jadi sidebar dan
 * tab bar tidak ikut ter-remount tiap pindah halaman.
 */
export function AppLayout({ children }: { children: ReactNode }) {
  return (
    <RequireSession>
      <AppShell>{children}</AppShell>
    </RequireSession>
  );
}
