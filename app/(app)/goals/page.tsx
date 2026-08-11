"use client";

import { useMemo } from "react";

import { Icon } from "@/components/icon";
import { PageHeader, ProgressBar, RowActions, SkeletonList } from "@/components/ui";
import { goalsView } from "@/lib/selectors";
import { useAmana } from "@/lib/store";

export default function GoalsPage() {
  const { ready, savingsGoals, openModal, deleteItem } = useAmana();
  const list = useMemo(() => goalsView(savingsGoals), [savingsGoals]);

  return (
    <div className="amana-container">
      <PageHeader title="Target Tabungan" onAdd={() => openModal("goal")} />

      {!ready ? (
        <SkeletonList count={3} height={142} />
      ) : list.length === 0 ? (
        <p className="text-muted" style={{ fontSize: 13 }}>
          Belum ada target tabungan.
        </p>
      ) : (
        list.map((g) => (
          <div key={g.id} className="card elev-sm">
            <div
              style={{
                display: "flex",
                justifyContent: "space-between",
                alignItems: "flex-start",
              }}
            >
              <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                <Icon name="target" size={20} color="var(--color-accent)" />
                <div className="card-title">{g.target_name}</div>
              </div>
              <RowActions
                label={`target ${g.target_name}`}
                onEdit={() => openModal("goal", g.id)}
                onDelete={() => deleteItem("goal", g.id)}
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
                {g.currentLabel} dari {g.targetLabel}
              </span>
              <span>{g.pct}%</span>
            </div>
            <ProgressBar pct={g.pct} />
            <div
              className="text-muted"
              style={{
                display: "flex",
                justifyContent: "space-between",
                fontSize: 12,
                gap: 12,
              }}
            >
              <span>Target: {g.deadlineLabel}</span>
              <span>Estimasi tercapai: {g.etaLabel}</span>
            </div>
          </div>
        ))
      )}
    </div>
  );
}
