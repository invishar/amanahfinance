import { AppShell } from "@/components/app-shell";
import { RequireSession } from "@/components/require-session";

export default function AppLayout({ children }: { children: React.ReactNode }) {
  return (
    <RequireSession>
      <AppShell>{children}</AppShell>
    </RequireSession>
  );
}
