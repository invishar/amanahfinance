import Link from "next/link";

import { AuthHeader } from "@/components/auth-header";

export default function Home() {
  return (
    <div className="amana-auth-shell">
      <div
        style={{
          width: "100%",
          maxWidth: 380,
          display: "flex",
          flexDirection: "column",
          gap: "var(--space-4)",
        }}
      >
        <AuthHeader
          title="AmanaFinance"
          subtitle="Asisten keuangan keluarga yang ngerti obrolan sehari-hari"
        />

        <Link
          href="/login"
          className="btn btn-primary btn-block"
          style={{ height: 44, fontSize: 15 }}
        >
          Masuk
        </Link>
      </div>
    </div>
  );
}
