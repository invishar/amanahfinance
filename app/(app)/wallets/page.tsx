"use client";

import { useMemo } from "react";

import { Icon } from "@/components/icon";
import { PageHeader, ProgressBar, RowActions, SkeletonList } from "@/components/ui";
import { walletsView } from "@/lib/selectors";
import { useAmana } from "@/lib/store";

export default function WalletsPage() {
  const { ready, wallets, transactions, openModal, deleteItem } = useAmana();
  const list = useMemo(
    () => walletsView(wallets, transactions),
    [wallets, transactions],
  );

  return (
    <div className="amana-container">
      <PageHeader title="Wallets" onAdd={() => openModal("wallet")} />

      {!ready ? (
        <SkeletonList count={4} height={122} />
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
                onEdit={() => openModal("wallet", w.id)}
                onDelete={() => deleteItem("wallet", w.id)}
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
            <ProgressBar pct={w.pct} color={w.barColor} />
          </div>
        ))
      )}
    </div>
  );
}
