"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";

import { CrudDialog } from "@/components/crud-dialog";
import { Icon } from "@/components/icon";
import {
  CHAT_NAV,
  MOBILE_TABS_LEFT,
  MOBILE_TABS_RIGHT,
  MORE_ITEMS,
  SIDEBAR_NAV,
  type NavItem,
} from "@/lib/nav";
import { useSession } from "@/lib/auth";
import { useUi } from "@/lib/ui-store";

export function AppShell({ children }: { children: React.ReactNode }) {
  return (
    <div className="amana-shell">
      <Sidebar />
      <div className="amana-content">{children}</div>
      <MobileTabBar />
      <MoreSheet />
      <CrudDialog />
    </div>
  );
}

function useIsActive() {
  const pathname = usePathname();
  return (item: NavItem) => pathname === item.href;
}

function useLogout() {
  const { logout } = useSession();
  const router = useRouter();
  return async () => {
    await logout();
    router.replace("/login");
  };
}

/* --- Desktop (>= 900px) -------------------------------------------------- */

function Sidebar() {
  const isActive = useIsActive();
  const onLogout = useLogout();

  return (
    <aside className="amana-sidebar">
      <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
        <div
          className="amana-brand-circle"
          style={{ width: 34, height: 34, fontSize: 14 }}
        >
          AF
        </div>
        <div
          style={{
            fontFamily: "var(--font-heading)",
            fontWeight: "var(--font-heading-weight)",
            fontSize: 16,
          }}
        >
          <div>AmanaFinance</div>
          <div style={{ fontFamily: "var(--font-body)", fontSize: 10, fontWeight: 500, opacity: 0.65 }}>
            Keuangan keluarga
          </div>
        </div>
      </div>

      <nav
        style={{
          display: "flex",
          flexDirection: "column",
          gap: 4,
          flex: 1,
        }}
      >
        <Link
          href={CHAT_NAV.href}
          className="amana-navlink-chat"
          data-active={isActive(CHAT_NAV)}
        >
          <Icon name={CHAT_NAV.icon} size={20} />
          Chat dengan Amina
        </Link>
        {SIDEBAR_NAV.map((item) => (
          <Link
            key={item.href}
            href={item.href}
            className="amana-navlink"
            data-active={isActive(item)}
          >
            <Icon name={item.icon} size={18} />
            {item.label}
          </Link>
        ))}
      </nav>

      <button
        type="button"
        onClick={onLogout}
        style={{
          display: "flex",
          alignItems: "center",
          gap: 10,
          padding: "9px 10px",
          fontSize: 13,
          background: "none",
          border: "none",
          cursor: "pointer",
          font: "inherit",
          color: "rgba(255,255,255,.7)",
        }}
      >
        <Icon name="log-out" size={16} />
        Keluar
      </button>
    </aside>
  );
}

/* --- Mobile (< 900px) ---------------------------------------------------- */

function MobileTabBar() {
  const isActive = useIsActive();
  const { moreSheetOpen, setMoreSheetOpen } = useUi();

  return (
    <div className="amana-tabbar">
      <Link
        href={CHAT_NAV.href}
        className="amana-tab-chat"
        data-active={isActive(CHAT_NAV)}
        aria-label="Chat dengan Amina"
      >
        <Icon name={CHAT_NAV.icon} size={24} />
      </Link>

      {MOBILE_TABS_LEFT.map((item) => (
        <MobileTab key={item.href} item={item} active={isActive(item)} />
      ))}

      <div style={{ width: 64, flex: "none" }} />

      {MOBILE_TABS_RIGHT.map((item) => (
        <MobileTab key={item.href} item={item} active={isActive(item)} />
      ))}

      <button
        type="button"
        className="amana-tab"
        data-active={moreSheetOpen}
        onClick={() => setMoreSheetOpen(!moreSheetOpen)}
      >
        <Icon name="grid-2x2" size={20} />
        <span className="amana-tab-label">Lainnya</span>
      </button>
    </div>
  );
}

function MobileTab({ item, active }: { item: NavItem; active: boolean }) {
  const { setMoreSheetOpen } = useUi();
  return (
    <Link
      href={item.href}
      className="amana-tab"
      data-active={active}
      onClick={() => setMoreSheetOpen(false)}
    >
      <Icon name={item.icon} size={20} />
      <span className="amana-tab-label">{item.label}</span>
    </Link>
  );
}

function MoreSheet() {
  const { moreSheetOpen, setMoreSheetOpen } = useUi();
  const onLogout = useLogout();
  if (!moreSheetOpen) return null;

  return (
    <div
      className="amana-more-sheet"
      style={{
        position: "fixed",
        inset: 0,
        background: "color-mix(in srgb, var(--color-neutral-900) 50%, transparent)",
        display: "flex",
        alignItems: "flex-end",
        zIndex: 20,
      }}
      onClick={() => setMoreSheetOpen(false)}
    >
      <div
        style={{
          width: "100%",
          background: "var(--color-surface)",
          borderRadius: "var(--radius-lg) var(--radius-lg) 0 0",
          padding: "var(--space-4)",
          display: "flex",
          flexDirection: "column",
          gap: 4,
          boxShadow: "var(--shadow-lg)",
        }}
        onClick={(e) => e.stopPropagation()}
      >
        {MORE_ITEMS.map((item) => (
          <Link
            key={item.href}
            href={item.href}
            onClick={() => setMoreSheetOpen(false)}
            style={{
              display: "flex",
              alignItems: "center",
              gap: 12,
              padding: "12px 6px",
              textAlign: "left",
              fontSize: 15,
              color: "var(--color-text)",
              textDecoration: "none",
              borderBottom: "1px solid var(--color-divider)",
            }}
          >
            <Icon name={item.icon} size={18} color="var(--color-accent)" />
            {item.label}
          </Link>
        ))}

        <button
          type="button"
          onClick={() => {
            setMoreSheetOpen(false);
            onLogout();
          }}
          className="text-muted"
          style={{
            display: "flex",
            alignItems: "center",
            gap: 12,
            padding: "12px 6px",
            textAlign: "left",
            fontSize: 15,
            background: "none",
            border: "none",
            cursor: "pointer",
            font: "inherit",
          }}
        >
          <Icon name="log-out" size={18} />
          Keluar
        </button>
      </div>
    </div>
  );
}
