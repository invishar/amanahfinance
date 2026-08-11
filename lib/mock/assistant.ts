// MOCK ONLY — meniru bentuk balasan `POST /chat/messages` (SSE:
// thinking -> token* -> action_card -> done). Di produksi seluruh file ini
// diganti pemanggilan API; naskah & prompt Amina tidak boleh tinggal di klien.

import { formatRupiah } from "@/lib/format";
import { wallets } from "@/lib/mock/data";
import type { ActionCard, ChatMessage } from "@/lib/types";

export type Scenario =
  | "transaction_text"
  | "transaction_voice"
  | "transaction_receipt"
  | "create_wallet"
  | "create_account"
  | "advice";

export interface AssistantReply {
  content: string;
  actionCard?: ActionCard;
}

const walletNameGuess = (text: string): string => {
  const t = text.toLowerCase();
  if (t.includes("makan") || t.includes("warteg") || t.includes("belanja")) return "w1";
  if (t.includes("grab") || t.includes("gojek") || t.includes("bensin") || t.includes("parkir")) return "w2";
  if (t.includes("nonton") || t.includes("game") || t.includes("hiburan")) return "w3";
  return "w1";
};

function guessScenario(text: string): Scenario {
  const t = text.toLowerCase();
  if (t.includes("wallet") || t.includes("dompet")) return "create_wallet";
  if (t.includes("akun") || t.includes("rekening") || t.includes("bank baru")) return "create_account";
  if (t.includes("saran") || t.includes("analisa") || t.includes("gimana keuangan")) return "advice";
  return "transaction_text";
}

export function mockAssistantReply(
  userText: string,
  scenario?: Scenario,
): AssistantReply {
  const s = scenario ?? guessScenario(userText);

  if (s === "transaction_text" || s === "transaction_voice") {
    const amountMatch = userText.match(/(\d[\d.,]*)\s*(rb|ribu|k)?/i);
    let amount = 25000;
    if (amountMatch) {
      let n = parseFloat(amountMatch[1].replace(/\./g, "").replace(",", "."));
      if (amountMatch[2]) n *= 1000;
      if (n > 0) amount = n;
    }
    const walletId = walletNameGuess(userText);
    return {
      content: "Oke, aku catat ya. Boleh dikonfirmasi dulu sebelum aku simpan:",
      actionCard: {
        type: "action_confirm",
        action: "create_transaction",
        title: "Catat Transaksi Baru",
        fields: [
          { label: "Jenis", value: "Pengeluaran" },
          { label: "Nominal", value: formatRupiah(amount) },
          { label: "Wallet", value: wallets.find((w) => w.id === walletId)?.name ?? "Belanja Harian" },
          { label: "Sumber Dana", value: "GoPay" },
          { label: "Catatan", value: userText.length > 40 ? userText.slice(0, 40) + "…" : userText },
        ],
        payload: {
          type: "expense",
          amount,
          wallet_id: walletId,
          account_id: "a2",
          note: userText.slice(0, 60),
        },
      },
    };
  }

  if (s === "transaction_receipt") {
    return {
      content: "Struknya sudah aku baca. Ini yang aku tangkap, cek dulu ya sebelum disimpan:",
      actionCard: {
        type: "action_confirm",
        action: "create_transaction",
        title: "Transaksi dari Struk",
        fields: [
          { label: "Jenis", value: "Pengeluaran" },
          { label: "Nominal", value: formatRupiah(187500) },
          { label: "Wallet", value: "Belanja Harian" },
          { label: "Sumber Dana", value: "BCA" },
          { label: "Catatan", value: "Belanja bulanan Indomaret" },
        ],
        payload: {
          type: "expense",
          amount: 187500,
          wallet_id: "w1",
          account_id: "a1",
          note: "Belanja bulanan Indomaret",
        },
      },
    };
  }

  if (s === "create_wallet") {
    return {
      content: "Siap, aku siapkan wallet barunya. Ini rangkumannya:",
      actionCard: {
        type: "action_confirm",
        action: "create_wallet",
        title: "Buat Wallet Baru",
        fields: [
          { label: "Nama Wallet", value: "Kesehatan" },
          { label: "Budget Bulanan", value: formatRupiah(600000) },
        ],
        payload: { name: "Kesehatan", monthly_budget: 600000, icon: "target" },
      },
    };
  }

  if (s === "create_account") {
    return {
      content: "Baik, aku daftarkan akunnya dulu ya. Tolong dicek:",
      actionCard: {
        type: "action_confirm",
        action: "create_account",
        title: "Tambah Akun Baru",
        fields: [
          { label: "Nama Akun", value: "Dana" },
          { label: "Jenis", value: "E-Wallet" },
          { label: "Saldo Awal", value: formatRupiah(150000) },
        ],
        payload: { name: "Dana", account_type: "ewallet", current_balance: 150000 },
      },
    };
  }

  return {
    content:
      "Bulan ini pengeluaran di wallet Belanja Harian sudah kepakai sekitar 60% dari budget — masih aman. Yang perlu diperhatikan: Hiburan sudah lewat separuh budget padahal baru tanggal 8. Kalau mau, aku bisa bantu geser sedikit alokasi dari Transportasi bulan ini supaya Dana Darurat tetap jalan sesuai target.",
  };
}

/** Pesan pembuka thread biasa (bukan onboarding). */
export function seedMessages(familyName: string): ChatMessage[] {
  return [
    {
      id: "m1",
      role: "assistant",
      content: `Halo Rizki! Aku Amina, asisten keuangan buat ${familyName}. Cerita aja kalau ada transaksi baru, mau bikin dompet, atau butuh saran keuangan.`,
    },
    { id: "m2", role: "user", content: "kemarin abis makan siang 25rb pake gopay" },
    {
      id: "m3",
      role: "assistant",
      content: "Sudah aku catat ya — pengeluaran Rp 25.000 dari GoPay untuk Belanja Harian.",
      actionCard: {
        type: "action_confirm",
        action: "create_transaction",
        title: "Transaksi Tersimpan",
        status: "confirmed",
        fields: [
          { label: "Nominal", value: "Rp 25.000" },
          { label: "Wallet", value: "Belanja Harian" },
          { label: "Sumber Dana", value: "GoPay" },
        ],
      },
    },
  ];
}

/**
 * MOCK ONLY — di produksi urutan pertanyaan datang dari API
 * (`onboarding.step` / `onboarding.total`); klien tidak menyimpan naskahnya.
 */
export const onboardQuestions = [
  { key: "members", question: "Sebelum mulai, boleh kenalan dulu sama kondisi keuangan keluarga? Ada berapa anggota keluarga yang bakal ikut mencatat di sini?" },
  { key: "income", question: "Penghasilan bulanan biasanya dari mana saja? Boleh sebutin semuanya, misalnya gaji, freelance, atau usaha." },
  { key: "expenses", question: "Pengeluaran rutin bulanan yang paling besar biasanya buat apa?" },
  { key: "goals", question: "Terakhir, ada target tabungan yang sedang dikejar? Misalnya dana darurat, liburan, atau renovasi rumah." },
] as const;
