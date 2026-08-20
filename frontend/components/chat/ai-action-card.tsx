"use client";

import { Icon } from "@/components/icon";
import { describeAiAction } from "@/lib/ai-action-view";
import type { Account, AiAction, IncomeSource, SavingsGoal, Wallet } from "@/lib/api/hooks";

// Kartu untuk AiAction sungguhan (bukan skenario demo -- lihat ActionCard di
// action-card.tsx untuk itu). Sengaja belum ada tombol "Edit": itu butuh form
// per jenis action yang belum dibangun sesi ini (lihat SESI-AKTIVASI-AI-CHAT
// -2026-08-20.md) -- field yang gagal diresolusi server cuma disorot di sini,
// user perlu kirim ulang lewat chat kalau mau ganti.
export function AiActionCard({
  aiAction,
  entities,
  onConfirm,
  onReject,
  isConfirming,
  isRejecting,
  errorMessage,
}: {
  aiAction: AiAction;
  entities: {
    accounts: Account[];
    wallets: Wallet[];
    incomeSources: IncomeSource[];
    savingsGoals: SavingsGoal[];
  };
  onConfirm: () => void;
  onReject: () => void;
  isConfirming: boolean;
  isRejecting: boolean;
  errorMessage?: string | null;
}) {
  const view = describeAiAction(aiAction, entities);
  const status = aiAction.status ?? "pending";

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
        <Icon name={view.icon} size={16} color="var(--color-accent)" />
        <div className="card-title" style={{ fontSize: 14 }}>
          {view.title}
        </div>
      </div>

      {view.fields.map((f) => (
        <div
          key={f.label}
          style={{ display: "flex", justifyContent: "space-between", fontSize: 13, gap: 12 }}
        >
          <span className="text-muted">{f.label}</span>
          <span
            style={{
              textAlign: "right",
              color: f.missing ? "var(--color-accent-800)" : undefined,
              fontStyle: f.missing ? "italic" : undefined,
            }}
          >
            {f.value}
          </span>
        </div>
      ))}

      {status === "pending" && (
        <div style={{ display: "flex", gap: 8, marginTop: 4 }}>
          <button
            type="button"
            className="btn btn-primary"
            style={{ flex: 1, fontSize: 13, whiteSpace: "nowrap" }}
            onClick={onConfirm}
            disabled={isConfirming || isRejecting}
          >
            <Icon name="check" size={14} />
            {isConfirming ? "Menyimpan…" : "Ya, lanjutkan"}
          </button>
          <button
            type="button"
            className="btn btn-ghost"
            style={{ fontSize: 13 }}
            onClick={onReject}
            disabled={isConfirming || isRejecting}
          >
            <Icon name="x" size={14} />
            Batal
          </button>
        </div>
      )}

      {(status === "confirmed" || status === "edited") && (
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

      {status === "rejected" && (
        <div className="text-muted" style={{ fontSize: 12 }}>
          Dibatalkan
        </div>
      )}

      {status === "expired" && (
        <div className="text-muted" style={{ fontSize: 12 }}>
          Kedaluwarsa
        </div>
      )}

      {errorMessage && (
        <p className="field-error" style={{ margin: 0 }}>
          {errorMessage}
        </p>
      )}
    </div>
  );
}
