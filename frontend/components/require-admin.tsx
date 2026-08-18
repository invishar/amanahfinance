"use client";

import { useRouter } from "next/navigation";
import { useEffect, type ReactNode } from "react";

import { useMe, useSession } from "@/lib/auth";

/**
 * Penjaga seluruh route di /admin (kecuali /admin/login itu sendiri):
 * tanpa token -> /admin/login; login tapi is_admin false -> akses ditolak,
 * BUKAN redirect diam-diam (supaya jelas kenapa, bukan loop balik ke login).
 */
export function RequireAdmin({ children }: { children: ReactNode }) {
  const router = useRouter();
  const { status, logout } = useSession();
  const me = useMe();

  useEffect(() => {
    if (status === "anonymous") router.replace("/admin/login");
  }, [status, router]);

  if (status !== "authenticated" || me.isPending) {
    return (
      <div
        style={{
          minHeight: "100vh",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          padding: "var(--space-6)",
        }}
      >
        <div
          className="amana-brand-circle"
          style={{ width: 56, height: 56, fontSize: 22, opacity: 0.5 }}
        >
          AF
        </div>
      </div>
    );
  }

  if (!me.data?.is_admin) {
    return (
      <div className="amana-auth-shell">
        <div
          className="card elev-md"
          style={{ maxWidth: 380, width: "100%", textAlign: "center", alignItems: "center" }}
        >
          <div className="card-title">Akses ditolak</div>
          <p className="text-muted" style={{ margin: 0, fontSize: 13 }}>
            Akun ini tidak punya akses admin platform.
          </p>
          <button
            type="button"
            className="btn btn-secondary"
            onClick={async () => {
              await logout();
              router.replace("/admin/login");
            }}
          >
            Keluar
          </button>
        </div>
      </div>
    );
  }

  return <>{children}</>;
}
