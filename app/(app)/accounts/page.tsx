"use client";

import { useMemo } from "react";

import { Icon } from "@/components/icon";
import { PageHeader, RowActions, SkeletonList } from "@/components/ui";
import { accountsView } from "@/lib/selectors";
import { useAmana } from "@/lib/store";

export default function AccountsPage() {
  const { ready, accounts, openModal, deleteItem } = useAmana();
  const list = useMemo(() => accountsView(accounts), [accounts]);

  return (
    <div className="amana-container">
      <PageHeader title="Akun Bank & E-Wallet" onAdd={() => openModal("account")} />

      {!ready ? (
        <SkeletonList count={3} height={82} />
      ) : list.length === 0 ? (
        <p className="text-muted" style={{ fontSize: 13 }}>
          Belum ada akun. Tambah tempat uangmu berada.
        </p>
      ) : (
        list.map((a) => (
          <div
            key={a.id}
            className="card elev-sm"
            style={{
              flexDirection: "row",
              alignItems: "center",
              justifyContent: "space-between",
              gap: "var(--space-3)",
            }}
          >
            <div style={{ display: "flex", alignItems: "center", gap: 12, minWidth: 0 }}>
              <Icon name={a.typeIcon} size={22} color="var(--color-accent)" />
              <div style={{ minWidth: 0 }}>
                <div className="card-title" style={{ fontSize: 15 }}>
                  {a.name}
                </div>
                <span className="tag tag-neutral">{a.typeLabel}</span>
              </div>
            </div>
            <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
              <div
                style={{
                  fontFamily: "var(--font-heading)",
                  fontSize: 15,
                  whiteSpace: "nowrap",
                }}
              >
                {a.balanceLabel}
              </div>
              <RowActions
                label={`akun ${a.name}`}
                onEdit={() => openModal("account", a.id)}
                onDelete={() => deleteItem("account", a.id)}
              />
            </div>
          </div>
        ))
      )}
    </div>
  );
}
