import type { ReactNode } from "react";

import { AdminShell } from "@/components/admin-shell";
import { RequireAdmin } from "@/components/require-admin";

/** Padanan `app/admin/(dashboard)/layout.tsx` di aplikasi Next. */
export function AdminLayout({ children }: { children: ReactNode }) {
  return (
    <RequireAdmin>
      <AdminShell>{children}</AdminShell>
    </RequireAdmin>
  );
}
