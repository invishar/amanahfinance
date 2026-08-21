"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";

import { Icon } from "@/components/icon";
import { useSession } from "@/lib/auth";

interface AdminNavItem {
  href: "/admin/users" | "/admin/payments" | "/admin/llm-settings" | "/admin/ai-errors";
  label: string;
  icon: string;
}

const ADMIN_NAV: AdminNavItem[] = [
  { href: "/admin/users", label: "User", icon: "users" },
  { href: "/admin/payments", label: "Pembayaran", icon: "credit-card" },
  { href: "/admin/llm-settings", label: "LLM Setting", icon: "sparkles" },
  { href: "/admin/ai-errors", label: "Log AI", icon: "alert-triangle" },
];

export function AdminShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const router = useRouter();
  const { logout } = useSession();

  return (
    <div style={{ minHeight: "100vh", display: "flex", flexDirection: "column" }}>
      <header
        style={{
          display: "flex",
          alignItems: "center",
          justifyContent: "space-between",
          padding: "var(--space-3) var(--space-4)",
          borderBottom: "1px solid var(--color-divider)",
          background: "var(--color-surface)",
          gap: "var(--space-4)",
          flexWrap: "wrap",
        }}
      >
        <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
          <div className="amana-brand-circle" style={{ width: 34, height: 34, fontSize: 14 }}>
            AF
          </div>
          <div
            style={{
              fontFamily: "var(--font-heading)",
              fontWeight: "var(--font-heading-weight)",
              fontSize: 16,
            }}
          >
            Admin Platform
          </div>
        </div>

        <nav style={{ display: "flex", gap: 4, flexWrap: "wrap" }}>
          {ADMIN_NAV.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              className="amana-navlink"
              data-active={pathname === item.href}
              style={{ padding: "8px 14px" }}
            >
              <Icon name={item.icon} size={16} />
              {item.label}
            </Link>
          ))}
        </nav>

        <button
          type="button"
          className="btn btn-ghost"
          onClick={async () => {
            await logout();
            router.replace("/admin/login");
          }}
        >
          <Icon name="log-out" size={14} />
          Keluar
        </button>
      </header>

      <div className="amana-container" style={{ maxWidth: 960 }}>
        {children}
      </div>
    </div>
  );
}
