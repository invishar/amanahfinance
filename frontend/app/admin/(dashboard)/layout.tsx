import { AdminShell } from "@/components/admin-shell";
import { RequireAdmin } from "@/components/require-admin";

export default function AdminDashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <RequireAdmin>
      <AdminShell>{children}</AdminShell>
    </RequireAdmin>
  );
}
