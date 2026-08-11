"use client";

import { useMemo } from "react";

import { ProgressBar, SkeletonList } from "@/components/ui";
import { useAnalytics, useWallets } from "@/lib/api/hooks";
import { formatRupiah } from "@/lib/format";
import { sortBySpent, walletsView } from "@/lib/selectors";

export default function AnalysisPage() {
  const analytics = useAnalytics();
  const wallets = useWallets();

  const cashflow = analytics.data?.cashflow;
  const bars = useMemo(
    () => sortBySpent(walletsView(wallets.data ?? [], analytics.data)),
    [wallets.data, analytics.data],
  );

  const ready = !analytics.isPending && !wallets.isPending;

  return (
    <div className="amana-container">
      <h1 style={{ fontSize: 22, margin: 0 }}>Analisa Keuangan</h1>

      <div style={{ display: "flex", gap: "var(--space-3)" }}>
        <SummaryCard
          kicker="Pemasukan"
          value={cashflow ? formatRupiah(cashflow.total_income ?? 0) : null}
        />
        <SummaryCard
          kicker="Pengeluaran"
          value={cashflow ? formatRupiah(cashflow.total_expense ?? 0) : null}
        />
        <SummaryCard
          kicker="Selisih"
          value={cashflow ? formatRupiah(cashflow.net ?? 0) : null}
        />
      </div>

      <div className="card elev-sm">
        <div className="card-title">Breakdown per Wallet</div>
        <div
          style={{
            display: "flex",
            flexDirection: "column",
            gap: 10,
            marginTop: 6,
          }}
        >
          {!ready ? (
            <SkeletonList count={4} height={30} />
          ) : bars.length === 0 ? (
            <p className="text-muted" style={{ margin: 0, fontSize: 13 }}>
              Belum ada wallet untuk dianalisa.
            </p>
          ) : (
            bars.map((w) => (
              <div key={w.id}>
                <div
                  style={{
                    display: "flex",
                    justifyContent: "space-between",
                    fontSize: 12,
                    marginBottom: 4,
                    gap: 12,
                  }}
                >
                  <span>{w.name}</span>
                  <span className="text-muted">{w.spentLabel}</span>
                </div>
                <ProgressBar pct={w.percent} color={w.barColor} />
              </div>
            ))
          )}
        </div>
      </div>

      {/* "Wawasan dari Amina" menunggu endpoint insight di API — seksinya
          sengaja tidak dirender daripada menampilkan teks karangan klien. */}
    </div>
  );
}

function SummaryCard({ kicker, value }: { kicker: string; value: string | null }) {
  return (
    <div className="card elev-sm" style={{ flex: 1, gap: 4, minWidth: 0 }}>
      <div className="card-kicker">{kicker}</div>
      <div
        style={{
          fontFamily: "var(--font-heading)",
          fontSize: 20,
          wordBreak: "break-word",
        }}
      >
        {value ?? "—"}
      </div>
    </div>
  );
}
