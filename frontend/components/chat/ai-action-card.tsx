"use client";

import { useState } from "react";

import { Icon } from "@/components/icon";
import {
  describeAiAction,
  editableFieldsForAiAction,
  isEditableAction,
  type AiActionEditField,
} from "@/lib/ai-action-view";
import { ApiError } from "@/lib/api/client";
import { useSaveEntity, type Account, type AiAction, type EntityKind, type IncomeSource, type SavingsGoal, type Wallet } from "@/lib/api/hooks";

const ACCOUNT_TYPE_OPTIONS = [
  { value: "bank", label: "Bank" },
  { value: "ewallet", label: "E-Wallet" },
  { value: "cash", label: "Tunai" },
  { value: "other", label: "Lainnya" },
];

const ADD_NEW_VALUE = "__add_new__";

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
  // Muncul saat entitas yang diinginkan belum ada di daftar -- entri baru
  // langsung dibuat lewat useSaveEntity(kind), lalu id-nya dipakai sebagai
  // value field ini. Entitas hasil buatan disimpan lokal supaya langsung
  // muncul di <select> tanpa menunggu refetch entities dari parent.
  const [addingKey, setAddingKey] = useState<string | null>(null);
  const [justCreated, setJustCreated] = useState<Record<string, { id: string; name: string }>>({});

  const saveWallet = useSaveEntity("wallet");
  const saveAccount = useSaveEntity("account");
  const saveIncome = useSaveEntity("income");
  const saveGoal = useSaveEntity("goal");
  const saveByKind: Record<EntityKind, ReturnType<typeof useSaveEntity>> = {
    wallet: saveWallet,
    account: saveAccount,
    income: saveIncome,
    goal: saveGoal,
  };

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
      {fields.map((f) => {
        const id = `ai-action-edit-${f.key}`;
        const value = draft[f.key] ?? "";
        const extraEntity = justCreated[f.key];
        const entityOptions =
          extraEntity && !f.entities?.some((e) => e.id === extraEntity.id)
            ? [...(f.entities ?? []), extraEntity]
            : f.entities;

        if (f.type === "entity-select" && addingKey === f.key && f.entityKind) {
          return (
            <NewEntityInlineForm
              key={f.key}
              label={f.label}
              entityKind={f.entityKind}
              mutation={saveByKind[f.entityKind]}
              onCancel={() => setAddingKey(null)}
              onCreated={(entity) => {
                setJustCreated((prev) => ({ ...prev, [f.key]: entity }));
                onChange(f.key, entity.id);
                setAddingKey(null);
              }}
            />
          );
        }

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
                onChange={(e) => {
                  if (e.target.value === ADD_NEW_VALUE) {
                    setAddingKey(f.key);
                    return;
                  }
                  onChange(f.key, e.target.value);
                }}
              >
                <option value="">— pilih —</option>
                {f.type === "select"
                  ? f.options?.map((o) => (
                      <option key={o.value} value={o.value}>
                        {o.label}
                      </option>
                    ))
                  : entityOptions?.map((entity) => (
                      <option key={entity.id} value={entity.id}>
                        {entity.name}
                      </option>
                    ))}
                {f.type === "entity-select" && f.entityKind && (
                  <option value={ADD_NEW_VALUE}>+ Tambah baru…</option>
                )}
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

/** Mini-form "Tambah baru" untuk satu entity-select -- field minimal per jenis
 * entitas (lihat API-v1.md: hanya `name`/`target_name` yang benar-benar wajib,
 * `account_type` tambahan wajib khusus akun, `target_amount` wajib khusus target). */
function NewEntityInlineForm({
  label,
  entityKind,
  mutation,
  onCancel,
  onCreated,
}: {
  label: string;
  entityKind: EntityKind;
  mutation: ReturnType<typeof useSaveEntity>;
  onCancel: () => void;
  onCreated: (entity: { id: string; name: string }) => void;
}) {
  const [name, setName] = useState("");
  const [accountType, setAccountType] = useState("bank");
  const [targetAmount, setTargetAmount] = useState("");
  const [error, setError] = useState<string | null>(null);

  const nameLabel = entityKind === "goal" ? "Nama target baru" : `${label} baru`;
  const canSubmit =
    name.trim().length > 0 && (entityKind !== "goal" || Number(targetAmount) > 0);

  const submit = async () => {
    setError(null);
    const trimmed = name.trim();
    const body: Record<string, unknown> =
      entityKind === "goal"
        ? { target_name: trimmed, target_amount: Number(targetAmount) }
        : { name: trimmed };
    if (entityKind === "account") body.account_type = accountType;

    try {
      const created = (await mutation.mutateAsync({ body })) as {
        id: string;
        name?: string;
        target_name?: string;
      };
      onCreated({ id: created.id, name: created.name ?? created.target_name ?? trimmed });
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Gagal menambah opsi baru.");
    }
  };

  return (
    <div
      style={{
        display: "flex",
        flexDirection: "column",
        gap: 6,
        padding: 8,
        border: "1px dashed var(--color-divider)",
        borderRadius: "var(--radius-sm)",
      }}
    >
      <span className="text-muted" style={{ fontSize: 12 }}>
        {nameLabel}
      </span>
      <input
        className="input"
        style={{ fontSize: 13 }}
        autoFocus
        value={name}
        onChange={(e) => setName(e.target.value)}
        placeholder={nameLabel}
      />
      {entityKind === "account" && (
        <select
          className="input"
          style={{ fontSize: 13 }}
          value={accountType}
          onChange={(e) => setAccountType(e.target.value)}
        >
          {ACCOUNT_TYPE_OPTIONS.map((o) => (
            <option key={o.value} value={o.value}>
              {o.label}
            </option>
          ))}
        </select>
      )}
      {entityKind === "goal" && (
        <input
          className="input"
          style={{ fontSize: 13 }}
          type="number"
          value={targetAmount}
          onChange={(e) => setTargetAmount(e.target.value)}
          placeholder="Nominal Target (Rp)"
        />
      )}
      {error && (
        <p className="field-error" style={{ margin: 0 }}>
          {error}
        </p>
      )}
      <div style={{ display: "flex", gap: 6 }}>
        <button
          type="button"
          className="btn btn-primary"
          style={{ flex: 1, fontSize: 12 }}
          onClick={submit}
          disabled={!canSubmit || mutation.isPending}
        >
          {mutation.isPending ? "Menyimpan…" : "Simpan"}
        </button>
        <button
          type="button"
          className="btn btn-ghost"
          style={{ fontSize: 12 }}
          onClick={onCancel}
          disabled={mutation.isPending}
        >
          Batal
        </button>
      </div>
    </div>
  );
}
