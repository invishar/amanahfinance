"use client";

import { useMemo } from "react";

import { EmptyState, PageHeader, RowActions, SkeletonList } from "@/components/ui";
import { useDeleteFlow } from "@/components/use-delete-flow";
import { useIncomeSources } from "@/lib/api/hooks";
import { incomeSourcesView } from "@/lib/selectors";
import { useUi } from "@/lib/ui-store";

export default function IncomePage() {
  const sources = useIncomeSources();
  const { openModal } = useUi();
  const { askDelete, dialog } = useDeleteFlow("income");

  const list = useMemo(
    () => incomeSourcesView(sources.data ?? []),
    [sources.data],
  );

  return (
    <div className="amana-container">
      <PageHeader eyebrow="Arus masuk" title="Sumber pemasukan" description="Catat sumber penghasilan rutin maupun tambahan milik keluarga." addLabel="Tambah sumber" onAdd={() => openModal("income")} />

      {sources.isPending ? (
        <SkeletonList count={2} height={74} />
      ) : sources.isError ? (
        <p className="field-error">
          Gagal memuat sumber pemasukan. Coba muat ulang halaman.
        </p>
      ) : list.length === 0 ? (
        <EmptyState title="Belum ada sumber pemasukan" message="Tambahkan gaji, usaha, atau sumber pendapatan lain secara manual." actionLabel="Tambah sumber" onAction={() => openModal("income")} />
      ) : (
        list.map((src) => (
          <div
            key={src.id}
            className="card elev-sm"
            style={{
              flexDirection: "row",
              alignItems: "center",
              justifyContent: "space-between",
              gap: "var(--space-3)",
            }}
          >
            <div className="card-title" style={{ fontSize: 15 }}>
              {src.name}
            </div>
            <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
              {/* API belum menyediakan realisasi per sumber; yang ada perkiraannya. */}
              {src.expectedLabel && (
                <div
                  className="text-muted"
                  style={{ fontSize: 13, whiteSpace: "nowrap" }}
                >
                  Perkiraan: {src.expectedLabel}
                </div>
              )}
              <RowActions
                label={`sumber pemasukan ${src.name}`}
                onEdit={() => openModal("income", src.raw)}
                onDelete={() => askDelete(src.id, `sumber pemasukan ${src.name}`)}
              />
            </div>
          </div>
        ))
      )}

      {dialog}
    </div>
  );
}
