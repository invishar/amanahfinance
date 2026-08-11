// MOCK ONLY — pengganti sementara sampai `lib/api.ts` (klien amanafinance-api)
// tersedia. Bentuknya sengaja menyerupai response API supaya mudah ditukar.
// Tidak ada satupun komponen yang boleh meng-import file ini langsung;
// semuanya lewat store (lib/store.tsx).

import type {
  Account,
  AccountType,
  Family,
  IncomeSource,
  SavingsGoal,
  Transaction,
  User,
  Wallet,
} from "@/lib/types";

/** Tanggal "hari ini" dipatok supaya data mock tetap koheren & SSR deterministik. */
export const MOCK_TODAY = "2026-08-08";

export const family: Family = { id: "fam1", name: "Keluarga Pratama" };
export const currentUser: User = {
  id: "u1",
  name: "Rizki Pratama",
  role: "admin",
};

export const wallets: Wallet[] = [
  { id: "w1", name: "Belanja Harian", monthly_budget: 2000000, icon: "shopping-cart" },
  { id: "w2", name: "Transportasi", monthly_budget: 800000, icon: "car" },
  { id: "w3", name: "Hiburan", monthly_budget: 500000, icon: "film" },
  { id: "w4", name: "Tagihan & Utilitas", monthly_budget: 1500000, icon: "file-text" },
];

export const bankAccounts: Account[] = [
  { id: "a1", name: "BCA", account_type: "bank", current_balance: 12500000 },
  { id: "a2", name: "GoPay", account_type: "ewallet", current_balance: 850000 },
  { id: "a3", name: "Tunai", account_type: "cash", current_balance: 300000 },
];

export const incomeSources: IncomeSource[] = [
  { id: "s1", name: "Gaji Bulanan" },
  { id: "s2", name: "Freelance Desain" },
];

export const transactions: Transaction[] = [
  { id: "t1", type: "income", amount: 14500000, wallet_id: null, account_id: "a1", source_id: "s1", note: "Gaji Agustus", transaction_date: "2026-08-01" },
  { id: "t2", type: "expense", amount: 350000, wallet_id: "w1", account_id: "a2", source_id: null, note: "Belanja Indomaret", transaction_date: "2026-08-02" },
  { id: "t3", type: "expense", amount: 45000, wallet_id: "w2", account_id: "a2", source_id: null, note: "Gojek ke kantor", transaction_date: "2026-08-02" },
  { id: "t4", type: "expense", amount: 120000, wallet_id: "w3", account_id: "a3", source_id: null, note: "Nonton bioskop", transaction_date: "2026-08-03" },
  { id: "t5", type: "expense", amount: 480000, wallet_id: "w4", account_id: "a1", source_id: null, note: "Token listrik", transaction_date: "2026-08-04" },
  { id: "t6", type: "expense", amount: 275000, wallet_id: "w1", account_id: "a1", source_id: null, note: "Belanja mingguan", transaction_date: "2026-08-05" },
  { id: "t7", type: "income", amount: 2200000, wallet_id: null, account_id: "a2", source_id: "s2", note: "Proyek logo klien", transaction_date: "2026-08-06" },
  { id: "t8", type: "expense", amount: 65000, wallet_id: "w2", account_id: "a2", source_id: null, note: "Parkir & tol", transaction_date: "2026-08-06" },
  { id: "t9", type: "expense", amount: 187500, wallet_id: "w1", account_id: "a2", source_id: null, note: "Belanja bulanan Indomaret", transaction_date: "2026-08-07" },
  { id: "t10", type: "expense", amount: 210000, wallet_id: "w4", account_id: "a1", source_id: null, note: "Internet rumah", transaction_date: "2026-08-08" },
];

export const savingsGoals: SavingsGoal[] = [
  { id: "g1", target_name: "Dana Darurat", target_amount: 30000000, current_amount: 18500000, deadline: "2026-12-31", avg_monthly_contribution: 1500000 },
  { id: "g2", target_name: "Liburan Keluarga", target_amount: 15000000, current_amount: 6200000, deadline: "2027-06-01", avg_monthly_contribution: 900000 },
  { id: "g3", target_name: "Renovasi Dapur", target_amount: 25000000, current_amount: 4000000, deadline: "2027-03-01", avg_monthly_contribution: 700000 },
];

/** Wawasan Amina — di produksi datang dari `GET /analytics/summary`. */
export const insights: string[] = [
  "Wallet Hiburan sudah kepakai lebih dari separuh budget padahal baru tanggal 8 — mungkin perlu direm sedikit sisa bulan ini.",
  "Pemasukan bulan ini lebih besar dari pengeluaran, jadi ada ruang buat nambah kontribusi ke Dana Darurat kalau mau.",
  "Belanja Harian polanya konsisten tiap minggu — coba pertimbangkan belanja bulanan sekali biar lebih hemat ongkos kirim/transport.",
];

export const accountTypeIcon: Record<AccountType, string> = {
  bank: "landmark",
  ewallet: "smartphone",
  cash: "banknote",
};

export const accountTypeLabel: Record<AccountType, string> = {
  bank: "Bank",
  ewallet: "E-Wallet",
  cash: "Tunai",
};

export function newId(prefix: string): string {
  return prefix + "_" + Math.random().toString(36).slice(2, 8);
}
