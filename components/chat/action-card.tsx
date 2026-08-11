"use client";

import { Icon } from "@/components/icon";
import { useAmana } from "@/lib/store";
import type { ActionCard as ActionCardData } from "@/lib/types";

const CARD_ICON = {
  create_transaction: "receipt",
  create_wallet: "wallet",
  create_account: "landmark",
} as const;

export function ActionCard({
  messageId,
  card,
}: {
  messageId: string;
  card: ActionCardData;
}) {
  const { resolveAction } = useAmana();

  return (
    <div
      className="elev-sm"
      style={{
        border: "1px solid var(--color-divider)",
        borderRadius: "var(--radius-md)",
        padding: "var(--space-3)",
        background: "var(--color-bg)",
        display: "flex",
        flexDirection: "column",
        gap: 8,
      }}
    >
      <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
        <Icon name={CARD_ICON[card.action]} size={16} color="var(--color-accent)" />
        <div className="card-title" style={{ fontSize: 14 }}>
          {card.title}
        </div>
      </div>

      {card.fields.map((f) => (
        <div
          key={f.label}
          style={{ display: "flex", justifyContent: "space-between", fontSize: 13, gap: 12 }}
        >
          <span className="text-muted">{f.label}</span>
          <span style={{ textAlign: "right" }}>{f.value}</span>
        </div>
      ))}

      {!card.status && (
        <div style={{ display: "flex", gap: 8, marginTop: 4 }}>
          <button
            type="button"
            className="btn btn-primary"
            style={{ flex: 1, fontSize: 13, whiteSpace: "nowrap" }}
            onClick={() => resolveAction(messageId, "confirmed")}
          >
            <Icon name="check" size={14} />
            Ya, lanjutkan
          </button>
          {/* TODO: buka modal prefilled lalu confirm dengan payload hasil edit
              (POST /ai-actions/{id}/confirm) — menunggu API. */}
          <button type="button" className="btn btn-secondary" style={{ fontSize: 13 }}>
            Edit
          </button>
          <button
            type="button"
            className="btn btn-ghost"
            style={{ fontSize: 13 }}
            onClick={() => resolveAction(messageId, "cancelled")}
          >
            <Icon name="x" size={14} />
            Batal
          </button>
        </div>
      )}

      {card.status === "confirmed" && (
        <div
          style={{
            display: "flex",
            alignItems: "center",
            gap: 6,
            fontSize: 12,
            color: "var(--color-accent-700)",
          }}
        >
          <Icon name="check" size={13} />
          Sudah disimpan
        </div>
      )}

      {card.status === "cancelled" && (
        <div className="text-muted" style={{ fontSize: 12 }}>
          Dibatalkan
        </div>
      )}
    </div>
  );
}
