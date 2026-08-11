"use client";

import { useMemo } from "react";

import { PageHeader, RowActions, SkeletonList } from "@/components/ui";
import { incomeView } from "@/lib/selectors";
import { useAmana } from "@/lib/store";

export default function IncomePage() {
  const { ready, incomeSources, transactions, openModal, deleteItem } = useAmana();
  const list = useMemo(
    () => incomeView(incomeSources, transactions),
    [incomeSources, transactions],
  );

  return (
    <div className="amana-container">
      <PageHeader title="Sumber Pemasukan" onAdd={() => openModal("income")} />

      {!ready ? (
        <SkeletonList count={2} height={74} />
      ) : list.length === 0 ? (
        <p className="text-muted" style={{ fontSize: 13 }}>
          Belum ada sumber pemasukan.
        </p>
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
              <div
                className="text-muted"
                style={{ fontSize: 13, whiteSpace: "nowrap" }}
              >
                Bulan ini: {src.totalLabel}
              </div>
              <RowActions
                label={`sumber pemasukan ${src.name}`}
                onEdit={() => openModal("income", src.id)}
                onDelete={() => deleteItem("income", src.id)}
              />
            </div>
          </div>
        ))
      )}
    </div>
  );
}
