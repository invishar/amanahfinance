"use client";

import { useMemo } from "react";

import { Icon } from "@/components/icon";
import { PageHeader, ProgressBar, RowActions, SkeletonList } from "@/components/ui";
import { useDeleteFlow } from "@/components/use-delete-flow";
import { useAnalytics, useWallets } from "@/lib/api/hooks";
import { walletsView } from "@/lib/selectors";
import { useUi } from "@/lib/ui-store";

export default function WalletsPage() {
  const wallets = useWallets();
  const analytics = useAnalytics();
  const { openModal } = useUi();
  const { askDelete, dialog } = useDeleteFlow("wallet");

  const list = useMemo(
    () => walletsView(wallets.data ?? [], analytics.data),
    [wallets.data, analytics.data],
  );

  return (
    <div className="amana-container">
      <PageHeader title="Wallets" onAdd={() => openModal("wallet")} />

      {wallets.isPending ? (
        <SkeletonList count={4} height={122} />
      ) : wallets.isError ? (
        <p className="field-error">Gagal memuat wallet. Coba muat ulang halaman.</p>
      ) : list.length === 0 ? (
        <p className="text-muted" style={{ fontSize: 13 }}>
          Belum ada wallet. Tambah kantong anggaran pertamamu.
        </p>
      ) : (
        list.map((w) => (
          <div key={w.id} className="card elev-sm">
            <div
              style={{
                display: "flex",
                justifyContent: "space-between",
                alignItems: "flex-start",
              }}
            >
              <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                <Icon name={w.icon} size={20} color="var(--color-accent)" />
                <div className="card-title">{w.name}</div>
              </div>
              <RowActions
                label={`wallet ${w.name}`}
                onEdit={() => openModal("wallet", w.raw)}
                onDelete={() => askDelete(w.id, `wallet ${w.name}`)}
              />
            </div>
            <div
              style={{
                display: "flex",
                justifyContent: "space-between",
                fontSize: 12,
                gap: 12,
              }}
            >
              <span className="text-muted">
                {w.spentLabel} dari {w.budgetLabel}
              </span>
              <span style={{ color: w.statusColor }}>{w.statusLabel}</span>
            </div>
            <ProgressBar pct={w.percent} color={w.barColor} />
          </div>
        ))
      )}

      {dialog}
    </div>
  );
}
