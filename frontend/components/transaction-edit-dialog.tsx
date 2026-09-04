"use client";

// Dialog ubah transaksi. Berdiri sendiri di luar `CrudDialog` generik karena
// field wajibnya bergantung pada `type` (lihat CLAUDE.md "Constraint
// transaksi") dan opsi select-nya datang dari entitas lain (wallet/akun/
// sumber/target) — bukan sesuatu yang cocok dengan bentuk statis
// `ENTITY_FORMS`.

import { useEffect, useState } from "react";

import { ApiError } from "@/lib/api/client";
import {
  useAccounts,
  useCreateTransaction,
  useIncomeSources,
  useSavingsGoals,
  useUpdateTransaction,
  useWallets,
  type Transaction,
} from "@/lib/api/hooks";

type TransactionType = NonNullable<Transaction["type"]>;

const TYPE_OPTIONS: { value: TransactionType; label: string }[] = [
  { value: "expense", label: "Pengeluaran" },
  { value: "income", label: "Pemasukan" },
  { value: "transfer", label: "Transfer" },
  { value: "savings", label: "Tabungan" },
];

interface Draft {
  type: TransactionType;
  amount: number;
  transaction_date: string;
  account_id: string;
  wallet_id: string;
  source_id: string;
  to_account_id: string;
  goal_id: string;
  note: string;
}

function toDraft(t?: Transaction | null): Draft {
  return {
    type: t?.type ?? "expense",
    amount: t?.amount ?? 0,
    transaction_date: t?.transaction_date ?? new Date().toISOString().slice(0, 10),
    account_id: t?.account_id ?? "",
    wallet_id: t?.wallet_id ?? "",
    source_id: t?.source_id ?? "",
    to_account_id: t?.to_account_id ?? "",
    goal_id: t?.goal_id ?? "",
    note: t?.note ?? "",
  };
}

export function TransactionEditDialog({
  transaction,
  open,
  onClose,
}: {
  transaction: Transaction | null;
  open?: boolean;
  onClose: () => void;
}) {
  if (!(open ?? Boolean(transaction))) return null;
  // Key per transaksi: draft & error ikut ter-reset saat transaksi berganti.
  return (
    <TransactionEditDialogInner
      key={transaction?.id ?? "new"}
      transaction={transaction}
      onClose={onClose}
    />
  );
}

function TransactionEditDialogInner({
  transaction,
  onClose,
}: {
  transaction: Transaction | null;
  onClose: () => void;
}) {
  const accounts = useAccounts();
  const wallets = useWallets();
  const incomeSources = useIncomeSources();
  const goals = useSavingsGoals();
  const update = useUpdateTransaction();
  const create = useCreateTransaction();

  const [draft, setDraft] = useState<Draft>(() => toDraft(transaction));
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onClose]);

  const set = <K extends keyof Draft>(key: K, value: Draft[K]) =>
    setDraft((prev) => ({ ...prev, [key]: value }));

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setFieldErrors({});
    setFormError(null);

    const body: Record<string, unknown> = {
      type: draft.type,
      amount: draft.amount,
      transaction_date: draft.transaction_date,
      account_id: draft.account_id,
      note: draft.note || null,
    };
    if (draft.type === "expense") body.wallet_id = draft.wallet_id;
    if (draft.type === "income") body.source_id = draft.source_id;
    if (draft.type === "transfer") body.to_account_id = draft.to_account_id;
    if (draft.type === "savings") body.goal_id = draft.goal_id;

    try {
      if (transaction?.id) {
        await update.mutateAsync({ id: transaction.id, body });
      } else {
        await create.mutateAsync(body);
      }
      onClose();
    } catch (error) {
      if (error instanceof ApiError) {
        const perField: Record<string, string> = {};
        for (const key of Object.keys(error.fieldErrors)) {
          const message = error.fieldMessage(key);
          if (message) perField[key] = message;
        }
        setFieldErrors(perField);
        if (Object.keys(perField).length === 0) setFormError(error.message);
      } else {
        setFormError("Terjadi kesalahan tak terduga.");
      }
    }
  };

  return (
    <div
      className="dialog-backdrop"
      style={{ zIndex: 30 }}
      onClick={onClose}
      role="presentation"
    >
      <form
        className="dialog"
        onClick={(e) => e.stopPropagation()}
        onSubmit={submit}
        role="dialog"
        aria-modal="true"
        aria-label={transaction ? "Ubah Transaksi" : "Tambah Transaksi"}
      >
        <div className="dialog-title">
          {transaction ? "Ubah Transaksi" : "Tambah Transaksi"}
        </div>

        <div className="field">
          <label htmlFor="tx-type">Jenis</label>
          <select
            id="tx-type"
            className="input"
            value={draft.type}
            onChange={(e) => set("type", e.target.value as TransactionType)}
          >
            {TYPE_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </select>
          {fieldErrors.type && <p className="field-error">{fieldErrors.type}</p>}
        </div>

        <div className="field">
          <label htmlFor="tx-amount">Nominal (Rp)</label>
          <input
            id="tx-amount"
            className="input"
            type="number"
            value={draft.amount}
            onChange={(e) => set("amount", Number(e.target.value))}
          />
          {fieldErrors.amount && <p className="field-error">{fieldErrors.amount}</p>}
        </div>

        <div className="field">
          <label htmlFor="tx-date">Tanggal</label>
          <input
            id="tx-date"
            className="input"
            type="date"
            value={draft.transaction_date}
            onChange={(e) => set("transaction_date", e.target.value)}
          />
          {fieldErrors.transaction_date && (
            <p className="field-error">{fieldErrors.transaction_date}</p>
          )}
        </div>

        <div className="field">
          <label htmlFor="tx-account">
            {draft.type === "transfer" ? "Dari Akun" : "Akun"}
          </label>
          <select
            id="tx-account"
            className="input"
            value={draft.account_id}
            onChange={(e) => set("account_id", e.target.value)}
          >
            <option value="">Pilih akun</option>
            {(accounts.data ?? []).map((a) => (
              <option key={a.id} value={a.id}>
                {a.name}
              </option>
            ))}
          </select>
          {fieldErrors.account_id && (
            <p className="field-error">{fieldErrors.account_id}</p>
          )}
        </div>

        {draft.type === "expense" && (
          <div className="field">
            <label htmlFor="tx-wallet">Wallet</label>
            <select
              id="tx-wallet"
              className="input"
              value={draft.wallet_id}
              onChange={(e) => set("wallet_id", e.target.value)}
            >
              <option value="">Pilih wallet</option>
              {(wallets.data ?? []).map((w) => (
                <option key={w.id} value={w.id}>
                  {w.name}
                </option>
              ))}
            </select>
            {fieldErrors.wallet_id && (
              <p className="field-error">{fieldErrors.wallet_id}</p>
            )}
          </div>
        )}

        {draft.type === "income" && (
          <div className="field">
            <label htmlFor="tx-source">Sumber Pemasukan</label>
            <select
              id="tx-source"
              className="input"
              value={draft.source_id}
              onChange={(e) => set("source_id", e.target.value)}
            >
              <option value="">Pilih sumber</option>
              {(incomeSources.data ?? []).map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </select>
            {fieldErrors.source_id && (
              <p className="field-error">{fieldErrors.source_id}</p>
            )}
          </div>
        )}

        {draft.type === "transfer" && (
          <div className="field">
            <label htmlFor="tx-to-account">Ke Akun</label>
            <select
              id="tx-to-account"
              className="input"
              value={draft.to_account_id}
              onChange={(e) => set("to_account_id", e.target.value)}
            >
              <option value="">Pilih akun tujuan</option>
              {(accounts.data ?? []).map((a) => (
                <option key={a.id} value={a.id}>
                  {a.name}
                </option>
              ))}
            </select>
            {fieldErrors.to_account_id && (
              <p className="field-error">{fieldErrors.to_account_id}</p>
            )}
          </div>
        )}

        {draft.type === "savings" && (
          <div className="field">
            <label htmlFor="tx-goal">Target Tabungan</label>
            <select
              id="tx-goal"
              className="input"
              value={draft.goal_id}
              onChange={(e) => set("goal_id", e.target.value)}
            >
              <option value="">Pilih target</option>
              {(goals.data ?? []).map((g) => (
                <option key={g.id} value={g.id}>
                  {g.target_name}
                </option>
              ))}
            </select>
            {fieldErrors.goal_id && <p className="field-error">{fieldErrors.goal_id}</p>}
          </div>
        )}

        <div className="field">
          <label htmlFor="tx-note">Catatan</label>
          <input
            id="tx-note"
            className="input"
            type="text"
            value={draft.note}
            onChange={(e) => set("note", e.target.value)}
          />
          {fieldErrors.note && <p className="field-error">{fieldErrors.note}</p>}
        </div>

        {formError && <p className="field-error">{formError}</p>}

        <div className="dialog-actions">
          <button type="button" className="btn btn-secondary" onClick={onClose}>
            Batal
          </button>
          <button
            type="submit"
            className="btn btn-primary"
            disabled={update.isPending || create.isPending}
          >
            {update.isPending || create.isPending ? "Menyimpan…" : "Simpan"}
          </button>
        </div>
      </form>
    </div>
  );
}
