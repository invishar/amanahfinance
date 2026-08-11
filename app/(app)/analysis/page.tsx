"use client";

import { useMemo } from "react";

import { Icon } from "@/components/icon";
import { ProgressBar, SkeletonList } from "@/components/ui";
import { insights } from "@/lib/mock/data";
import { analyticsSummary, walletBars } from "@/lib/selectors";
import { useAmana } from "@/lib/store";

export default function AnalysisPage() {
  const { ready, wallets, transactions } = useAmana();

  // Di produksi: `GET /analytics/summary?period=YYYY-MM` (di-cache server).
  const summary = useMemo(() => analyticsSummary(transactions), [transactions]);
  const bars = useMemo(
    () => walletBars(wallets, transactions),
    [wallets, transactions],
  );

  return (
    <div className="amana-container">
      <h1 style={{ fontSize: 22, margin: 0 }}>Analisa Keuangan</h1>

      <div style={{ display: "flex", gap: "var(--space-3)" }}>
        <SummaryCard kicker="Pemasukan" value={summary.totalIncomeLabel} ready={ready} />
        <SummaryCard kicker="Pengeluaran" value={summary.totalExpenseLabel} ready={ready} />
        <SummaryCard kicker="Selisih" value={summary.netLabel} ready={ready} />
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
                <ProgressBar pct={w.barPct} />
              </div>
            ))
          )}
        </div>
      </div>

      <div className="card elev-sm">
        <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
          <Icon name="sparkles" size={16} color="var(--color-accent)" />
          <div className="card-title">Wawasan dari Amina</div>
        </div>
        <div
          style={{
            display: "flex",
            flexDirection: "column",
            gap: 10,
            marginTop: 6,
          }}
        >
          {!ready ? (
            <SkeletonList count={3} height={42} />
          ) : (
            insights.map((text) => (
              <p key={text} style={{ margin: 0, fontSize: 13, lineHeight: 1.6 }}>
                {text}
              </p>
            ))
          )}
        </div>
      </div>
    </div>
  );
}

function SummaryCard({
  kicker,
  value,
  ready,
}: {
  kicker: string;
  value: string;
  ready: boolean;
}) {
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
        {ready ? value : "—"}
      </div>
    </div>
  );
}
