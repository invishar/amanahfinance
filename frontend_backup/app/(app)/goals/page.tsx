"use client";

import { useMemo } from "react";

import { Icon } from "@/components/icon";
import { EmptyState, PageHeader, ProgressBar, RowActions, SkeletonList } from "@/components/ui";
import { useDeleteFlow } from "@/components/use-delete-flow";
import { useSavingsGoals } from "@/lib/api/hooks";
import { goalsView } from "@/lib/selectors";
import { useUi } from "@/lib/ui-store";

export default function GoalsPage() {
  const goals = useSavingsGoals();
  const { openModal } = useUi();
  const { askDelete, dialog } = useDeleteFlow("goal");

  const list = useMemo(() => goalsView(goals.data ?? []), [goals.data]);

  return (
    <div className="amana-container">
      <PageHeader eyebrow="Rencana keluarga" title="Target tabungan" description="Ubah rencana besar menjadi target yang terasa dekat dan terukur." addLabel="Buat target" onAdd={() => openModal("goal")} />

      {goals.isPending ? (
        <SkeletonList count={3} height={128} />
      ) : goals.isError ? (
        <p className="field-error">Gagal memuat target. Coba muat ulang halaman.</p>
      ) : list.length === 0 ? (
        <EmptyState title="Belum ada target" message="Buat target seperti dana darurat, pendidikan, atau liburan keluarga." actionLabel="Buat target" onAction={() => openModal("goal")} />
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
                <div className="card-title">{g.name}</div>
              </div>
              <RowActions
                label={`target ${g.name}`}
                onEdit={() => openModal("goal", g.raw)}
                onDelete={() => askDelete(g.id, `target ${g.name}`)}
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
              <span>{g.percent}%</span>
            </div>
            <ProgressBar pct={g.percent} />
            {/* "Estimasi tercapai" belum ada di API — sengaja tidak ditampilkan. */}
            {g.deadlineLabel && (
              <div className="text-muted" style={{ fontSize: 12 }}>
                Target: {g.deadlineLabel}
              </div>
            )}
          </div>
        ))
      )}

      {dialog}
    </div>
  );
}
