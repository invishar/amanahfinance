"use client";

import { useRouter } from "next/navigation";
import { useEffect, type ReactNode } from "react";

import { useActiveFamily } from "@/lib/api/hooks";
import { useSession } from "@/lib/auth";

/**
 * Penjaga seluruh route di grup (app):
 * tanpa token → /login; sudah login tapi belum punya family → /onboarding.
 */
export function RequireSession({ children }: { children: ReactNode }) {
  const router = useRouter();
  const { status } = useSession();
  const { familyId, isLoading, isError } = useActiveFamily();

  useEffect(() => {
    if (status === "anonymous") router.replace("/login");
  }, [status, router]);

  useEffect(() => {
    if (status === "authenticated" && !isLoading && !isError && !familyId) {
      router.replace("/onboarding");
    }
  }, [status, isLoading, isError, familyId, router]);

  if (status !== "authenticated" || isLoading || !familyId) {
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

  return <>{children}</>;
}
