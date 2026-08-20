// Payload AiAction menyimpan id yang sudah diresolusi server (NameResolver),
// bukan nama -- jadi kartu di chat harus mencocokkan id itu balik ke entitas
// family yang sudah di-fetch di layar lain, bukan menampilkan UUID mentah.

import { formatDateID, formatRupiah } from "@/lib/format";
import type { Account, AiAction, IncomeSource, SavingsGoal, Wallet } from "@/lib/api/hooks";

export interface AiActionField {
  label: string;
  value: string;
  /** Field wajib yang gagal diresolusi server (nama ambigu/tidak ketemu) -- perlu disorot. */
  missing?: boolean;
}

export interface AiActionView {
  icon: string;
  title: string;
  fields: AiActionField[];
}

const MISSING = "(belum dipilih)";

const TX_TYPE_LABEL: Record<string, string> = {
  expense: "Pengeluaran",
  income: "Pemasukan",
  transfer: "Transfer",
  savings: "Setor Tabungan",
};

const ACCOUNT_TYPE_LABEL: Record<string, string> = {
  bank: "Bank",
  ewallet: "E-Wallet",
  cash: "Tunai",
  other: "Lainnya",
};

const CADENCE_LABEL: Record<string, string> = {
  monthly: "Bulanan",
  biweekly: "Dua Mingguan",
  weekly: "Mingguan",
  irregular: "Tidak Tentu",
};

const ACTION_ICON: Record<string, string> = {
  create_transaction: "receipt",
  create_wallet: "wallet",
  create_account: "landmark",
  create_income_source: "banknote",
  create_savings_goal: "target",
  advice: "sparkles",
};

const ACTION_TITLE: Record<string, string> = {
  create_transaction: "Catat Transaksi",
  create_wallet: "Buat Wallet Baru",
  create_account: "Tambah Akun Baru",
  create_income_source: "Tambah Sumber Pemasukan",
  create_savings_goal: "Buat Target Tabungan",
  advice: "Saran dari Amina",
};

interface Entities {
  accounts: Account[];
  wallets: Wallet[];
  incomeSources: IncomeSource[];
  savingsGoals: SavingsGoal[];
}

function nameOf(id: unknown, list: { id?: string; name?: string }[]): string {
  if (typeof id !== "string") return MISSING;
  return list.find((x) => x.id === id)?.name ?? MISSING;
}

function field(label: string, value: string): AiActionField {
  return { label, value, missing: value === MISSING };
}

export function describeAiAction(aiAction: AiAction, entities: Entities): AiActionView {
  const p = (aiAction.payload ?? {}) as Record<string, unknown>;
  const action = aiAction.action ?? "advice";
  const icon = ACTION_ICON[action] ?? "sparkles";
  const title = ACTION_TITLE[action] ?? "Draft dari Amina";

  if (action === "create_transaction") {
    const type = typeof p.type === "string" ? p.type : "";
    const fields: AiActionField[] = [
      field("Jenis", TX_TYPE_LABEL[type] ?? "—"),
      field("Nominal", typeof p.amount === "number" ? formatRupiah(p.amount) : MISSING),
      field("Sumber Dana", nameOf(p.account_id, entities.accounts)),
    ];
    if (type === "expense") fields.push(field("Wallet", nameOf(p.wallet_id, entities.wallets)));
    if (type === "income") fields.push(field("Sumber Pemasukan", nameOf(p.source_id, entities.incomeSources)));
    if (type === "transfer") fields.push(field("Akun Tujuan", nameOf(p.to_account_id, entities.accounts)));
    if (type === "savings") fields.push(field("Target Tabungan", nameOf(p.goal_id, entities.savingsGoals)));
    if (typeof p.note === "string" && p.note) fields.push(field("Catatan", p.note));
    return { icon, title, fields };
  }

  if (action === "create_wallet") {
    return {
      icon,
      title,
      fields: [
        field("Nama Wallet", typeof p.name === "string" ? p.name : MISSING),
        field(
          "Budget Bulanan",
          typeof p.monthly_budget === "number" ? formatRupiah(p.monthly_budget) : "Tanpa budget",
        ),
      ],
    };
  }

  if (action === "create_account") {
    return {
      icon,
      title,
      fields: [
        field("Nama Akun", typeof p.name === "string" ? p.name : MISSING),
        field("Jenis", ACCOUNT_TYPE_LABEL[String(p.account_type)] ?? "—"),
        field("Saldo Awal", formatRupiah(typeof p.opening_balance === "number" ? p.opening_balance : 0)),
      ],
    };
  }

  if (action === "create_income_source") {
    return {
      icon,
      title,
      fields: [
        field("Nama Sumber", typeof p.name === "string" ? p.name : MISSING),
        field(
          "Perkiraan Nominal",
          typeof p.expected_amount === "number" ? formatRupiah(p.expected_amount) : "—",
        ),
        field("Frekuensi", CADENCE_LABEL[String(p.cadence)] ?? "—"),
      ],
    };
  }

  if (action === "create_savings_goal") {
    return {
      icon,
      title,
      fields: [
        field("Nama Target", typeof p.target_name === "string" ? p.target_name : MISSING),
        field("Nominal Target", typeof p.target_amount === "number" ? formatRupiah(p.target_amount) : MISSING),
        field("Tenggat", typeof p.deadline === "string" ? formatDateID(p.deadline) : "—"),
        field("Akun Penampung", nameOf(p.account_id, entities.accounts)),
      ],
    };
  }

  // advice: informational only, tidak menulis apa pun (lihat ConfirmAiAction::write()).
  return {
    icon,
    title,
    fields: [field("Saran", typeof p.message === "string" ? p.message : "—")],
  };
}
