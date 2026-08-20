"use client";

import { useState } from "react";

import { Icon } from "@/components/icon";
import {
  describeAiAction,
  editableFieldsForAiAction,
  isEditableAction,
  type AiActionEditField,
} from "@/lib/ai-action-view";
import type { Account, AiAction, IncomeSource, SavingsGoal, Wallet } from "@/lib/api/hooks";

type Entities = {
  accounts: Account[];
  wallets: Wallet[];
  incomeSources: IncomeSource[];
  savingsGoals: SavingsGoal[];
};

// Kartu untuk AiAction sungguhan (bukan skenario demo -- lihat ActionCard di
// action-card.tsx untuk itu). Mode edit menimpa field yang gagal diresolusi
// server (atau field mana pun) sebelum confirm -- draft-nya cuma state lokal
// sampai "Simpan & Konfirmasi" ditekan, tidak ada PATCH parsial ke server.
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
  entities: Entities;
  onConfirm: (edits?: Record<string, unknown>) => void;
  onReject: () => void;
  isConfirming: boolean;
  isRejecting: boolean;
  errorMessage?: string | null;
}) {
  const view = describeAiAction(aiAction, entities);
  const status = aiAction.status ?? "pending";
  const [isEditing, setIsEditing] = useState(false);
  const [draft, setDraft] = useState<Record<string, string>>({});

  const startEdit = () => {
    const payload = (aiAction.payload ?? {}) as Record<string, unknown>;
    const next: Record<string, string> = {};
    for (const [key, value] of Object.entries(payload)) {
      if (value !== null && value !== undefined) next[key] = String(value);
    }
    setDraft(next);
    setIsEditing(true);
  };

  const editFields = isEditing ? editableFieldsForAiAction(aiAction.action, draft, entities) : [];
  const missingRequired = editFields.some((f) => f.required && !draft[f.key]);

  const submitEdit = () => {
    const edits: Record<string, unknown> = {};
    for (const f of editFields) {
      const raw = draft[f.key];
      if (raw === undefined || raw === "") continue;
      edits[f.key] = f.type === "number" ? Number(raw) : raw;
    }
    onConfirm(edits);
    setIsEditing(false);
  };

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
        minWidth: isEditing ? 260 : undefined,
      }}
    >
      <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
        <Icon name={view.icon} size={16} color="var(--color-accent)" />
        <div className="card-title" style={{ fontSize: 14 }}>
          {view.title}
        </div>
      </div>

      {isEditing ? (
        <EditForm fields={editFields} draft={draft} onChange={(k, v) => setDraft((prev) => ({ ...prev, [k]: v }))} />
      ) : (
        view.fields.map((f) => (
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
        ))
      )}

      {status === "pending" && isEditing && (
        <div style={{ display: "flex", gap: 8, marginTop: 4 }}>
          <button
            type="button"
            className="btn btn-primary"
            style={{ flex: 1, fontSize: 13, whiteSpace: "nowrap" }}
            onClick={submitEdit}
            disabled={isConfirming || missingRequired}
          >
            <Icon name="check" size={14} />
            {isConfirming ? "Menyimpan…" : "Simpan & Konfirmasi"}
          </button>
          <button
            type="button"
            className="btn btn-ghost"
            style={{ fontSize: 13 }}
            onClick={() => setIsEditing(false)}
            disabled={isConfirming}
          >
            Batal edit
          </button>
        </div>
      )}

      {status === "pending" && !isEditing && (
        <div style={{ display: "flex", gap: 8, marginTop: 4 }}>
          <button
            type="button"
            className="btn btn-primary"
            style={{ flex: 1, fontSize: 13, whiteSpace: "nowrap" }}
            onClick={() => onConfirm()}
            disabled={isConfirming || isRejecting}
          >
            <Icon name="check" size={14} />
            {isConfirming ? "Menyimpan…" : "Ya, lanjutkan"}
          </button>
          {isEditableAction(aiAction.action) && (
            <button
              type="button"
              className="btn btn-secondary"
              style={{ fontSize: 13 }}
              onClick={startEdit}
              disabled={isConfirming || isRejecting}
            >
              <Icon name="pencil" size={14} />
              Edit
            </button>
          )}
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

function EditForm({
  fields,
  draft,
  onChange,
}: {
  fields: AiActionEditField[];
  draft: Record<string, string>;
  onChange: (key: string, value: string) => void;
}) {
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
      {fields.map((f) => {
        const id = `ai-action-edit-${f.key}`;
        const value = draft[f.key] ?? "";
        return (
          <div key={f.key} style={{ display: "flex", flexDirection: "column", gap: 2 }}>
            <label htmlFor={id} className="text-muted" style={{ fontSize: 12 }}>
              {f.label}
              {f.required && !value && <span style={{ color: "var(--color-accent-800)" }}> *</span>}
            </label>
            {f.type === "select" || f.type === "entity-select" ? (
              <select
                id={id}
                className="input"
                style={{ fontSize: 13 }}
                value={value}
                onChange={(e) => onChange(f.key, e.target.value)}
              >
                <option value="">— pilih —</option>
                {f.type === "select"
                  ? f.options?.map((o) => (
                      <option key={o.value} value={o.value}>
                        {o.label}
                      </option>
                    ))
                  : f.entities?.map((entity) => (
                      <option key={entity.id} value={entity.id}>
                        {entity.name}
                      </option>
                    ))}
              </select>
            ) : (
              <input
                id={id}
                className="input"
                style={{ fontSize: 13 }}
                type={f.type === "number" ? "number" : f.type === "date" ? "date" : "text"}
                value={value}
                onChange={(e) => onChange(f.key, e.target.value)}
              />
            )}
          </div>
        );
      })}
    </div>
  );
}
