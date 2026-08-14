// Definisi form CRUD per entitas — satu tempat pemetaan "field di desain" →
// "field di API". Perhatikan: beberapa field hanya ada saat create karena
// PUT-nya memang tidak menerimanya (lihat TaskProject.md §2).

import type { EntityKind } from "@/lib/api/hooks";

export type DraftValue = string | number;
export type Draft = Record<string, DraftValue>;

export interface FieldDef {
  name: string;
  label: string;
  type: "text" | "number" | "date" | "select";
  options?: { value: string; label: string }[];
  /** Tidak dikirim (dan tidak ditampilkan) saat mengubah data. */
  createOnly?: boolean;
  hint?: string;
}

export interface EntityForm {
  title: string;
  fields: FieldDef[];
  /** Nilai default + prefill dari entitas yang sedang diubah. */
  toDraft: (entity?: Record<string, unknown>) => Draft;
  /** Field tambahan yang tidak muncul di form tapi wajib dikirim. */
  extras?: Draft;
}

const str = (v: unknown, fallback = "") =>
  typeof v === "string" ? v : fallback;
const num = (v: unknown, fallback = 0) =>
  typeof v === "number" ? v : fallback;

export const ENTITY_FORMS: Record<EntityKind, EntityForm> = {
  wallet: {
    title: "Wallet",
    fields: [
      { name: "name", label: "Nama wallet", type: "text" },
      { name: "monthly_budget", label: "Budget bulanan (Rp)", type: "number" },
    ],
    toDraft: (e) => ({
      name: str(e?.name),
      monthly_budget: num(e?.monthly_budget),
    }),
    extras: { icon: "shopping-cart" },
  },
  account: {
    title: "Akun",
    fields: [
      { name: "name", label: "Nama akun", type: "text" },
      {
        name: "account_type",
        label: "Jenis",
        type: "select",
        options: [
          { value: "bank", label: "Bank" },
          { value: "ewallet", label: "E-Wallet" },
          { value: "cash", label: "Tunai" },
          { value: "other", label: "Lainnya" },
        ],
      },
      {
        name: "opening_balance",
        label: "Saldo awal (Rp)",
        type: "number",
        createOnly: true,
        hint: "Saldo berjalan dihitung server dari transaksi, jadi hanya bisa diisi saat akun dibuat.",
      },
    ],
    toDraft: (e) => ({
      name: str(e?.name),
      account_type: str(e?.account_type, "bank"),
      opening_balance: num(e?.opening_balance),
    }),
  },
  income: {
    title: "Sumber Pemasukan",
    fields: [{ name: "name", label: "Nama sumber pemasukan", type: "text" }],
    toDraft: (e) => ({ name: str(e?.name) }),
  },
  goal: {
    title: "Target Tabungan",
    fields: [
      { name: "target_name", label: "Nama target", type: "text" },
      { name: "target_amount", label: "Target nominal (Rp)", type: "number" },
      { name: "deadline", label: "Target tanggal", type: "date" },
    ],
    toDraft: (e) => ({
      target_name: str(e?.target_name),
      target_amount: num(e?.target_amount),
      deadline: str(e?.deadline),
    }),
  },
};

/** Body request: buang field createOnly saat mengubah, buang tanggal kosong. */
export function buildBody(
  kind: EntityKind,
  draft: Draft,
  isEdit: boolean,
): Record<string, unknown> {
  const form = ENTITY_FORMS[kind];
  const body: Record<string, unknown> = isEdit ? {} : { ...form.extras };

  for (const field of form.fields) {
    if (isEdit && field.createOnly) continue;
    const value = draft[field.name];
    if (field.type === "date" && !value) continue;
    body[field.name] = value;
  }
  return body;
}
