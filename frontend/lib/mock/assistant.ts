// DEMO ONLY — dipagari flag `NEXT_PUBLIC_MOCK_AMINA`.
//
// Backend menyimpan pesan user, tapi BELUM membalas: tidak ada pesan
// `role: assistant`, tidak ada baris `ai_actions`, tidak ada streaming.
// Sampai itu ada, layar chat memakai balasan tiruan di sini supaya alurnya
// bisa didemokan. Begitu API membalas: set `NEXT_PUBLIC_MOCK_AMINA=0`, lalu
// hapus file ini beserta pemakaiannya di `app/(app)/chat/page.tsx`.

import { formatRupiah } from "@/lib/format";

/** Demo aktif kecuali dimatikan eksplisit. */
export const DEMO_AMINA = process.env.NEXT_PUBLIC_MOCK_AMINA !== "0";

export type Scenario =
  | "transaction_text"
  | "transaction_voice"
  | "transaction_receipt"
  | "create_wallet"
  | "create_account"
  | "advice";

export interface DemoActionCard {
  action: "create_transaction" | "create_wallet" | "create_account";
  title: string;
  fields: { label: string; value: string }[];
  status?: "confirmed" | "cancelled";
}

export interface DemoReply {
  content: string;
  card?: DemoActionCard;
}

export interface DemoContext {
  /** Nama wallet & akun asli milik family, supaya kartu demo tidak mengarang. */
  walletName?: string;
  accountName?: string;
}

function guessScenario(text: string): Scenario {
  const t = text.toLowerCase();
  if (t.includes("wallet") || t.includes("dompet")) return "create_wallet";
  if (t.includes("akun") || t.includes("rekening") || t.includes("bank baru"))
    return "create_account";
  if (t.includes("saran") || t.includes("analisa") || t.includes("gimana keuangan"))
    return "advice";
  return "transaction_text";
}

export function demoReply(
  userText: string,
  scenario: Scenario | undefined,
  ctx: DemoContext = {},
): DemoReply {
  const s = scenario ?? guessScenario(userText);
  const walletName = ctx.walletName ?? "wallet pertamamu";
  const accountName = ctx.accountName ?? "akun utamamu";

  if (s === "transaction_text" || s === "transaction_voice") {
    const match = userText.match(/(\d[\d.,]*)\s*(rb|ribu|k)?/i);
    let amount = 25000;
    if (match) {
      let n = parseFloat(match[1].replace(/\./g, "").replace(",", "."));
      if (match[2]) n *= 1000;
      if (n > 0) amount = n;
    }
    return {
      content: "Oke, aku catat ya. Boleh dikonfirmasi dulu sebelum aku simpan:",
      card: {
        action: "create_transaction",
        title: "Catat Transaksi Baru",
        fields: [
          { label: "Jenis", value: "Pengeluaran" },
          { label: "Nominal", value: formatRupiah(amount) },
          { label: "Wallet", value: walletName },
          { label: "Sumber Dana", value: accountName },
          {
            label: "Catatan",
            value: userText.length > 40 ? userText.slice(0, 40) + "…" : userText,
          },
        ],
      },
    };
  }

  if (s === "transaction_receipt") {
    return {
      content:
        "Struknya sudah aku baca. Ini yang aku tangkap, cek dulu ya sebelum disimpan:",
      card: {
        action: "create_transaction",
        title: "Transaksi dari Struk",
        fields: [
          { label: "Jenis", value: "Pengeluaran" },
          { label: "Nominal", value: formatRupiah(187500) },
          { label: "Wallet", value: walletName },
          { label: "Sumber Dana", value: accountName },
          { label: "Catatan", value: "Belanja bulanan Indomaret" },
        ],
      },
    };
  }

  if (s === "create_wallet") {
    return {
      content: "Siap, aku siapkan wallet barunya. Ini rangkumannya:",
      card: {
        action: "create_wallet",
        title: "Buat Wallet Baru",
        fields: [
          { label: "Nama Wallet", value: "Kesehatan" },
          { label: "Budget Bulanan", value: formatRupiah(600000) },
        ],
      },
    };
  }

  if (s === "create_account") {
    return {
      content: "Baik, aku daftarkan akunnya dulu ya. Tolong dicek:",
      card: {
        action: "create_account",
        title: "Tambah Akun Baru",
        fields: [
          { label: "Nama Akun", value: "Dana" },
          { label: "Jenis", value: "E-Wallet" },
          { label: "Saldo Awal", value: formatRupiah(150000) },
        ],
      },
    };
  }

  return {
    content:
      "Buat saran yang beneran, aku perlu tersambung ke mesin analisa di server dulu. Sementara ini kamu bisa lihat angka aslinya di tab Analisa.",
  };
}

export const DEMO_CHIPS: {
  label: string;
  scenario: Scenario;
  demoText: string;
}[] = [
  {
    label: "Catat pengeluaran",
    scenario: "transaction_text",
    demoText: "Tadi beli kopi sama snack 45rb pakai GoPay",
  },
  {
    label: "Buat wallet baru",
    scenario: "create_wallet",
    demoText: "Aku mau bikin wallet baru buat Kesehatan",
  },
  {
    label: "Tambah akun baru",
    scenario: "create_account",
    demoText: "Tolong tambahin akun Dana sebagai e-wallet",
  },
  {
    label: "Minta saran keuangan",
    scenario: "advice",
    demoText: "Gimana kondisi keuangan bulan ini?",
  },
];

/** Naskah wawancara awal — milik server begitu endpointnya ada. */
export const DEMO_ONBOARD_QUESTIONS = [
  "Sebelum mulai, boleh kenalan dulu sama kondisi keuangan keluarga? Ada berapa anggota keluarga yang bakal ikut mencatat di sini?",
  "Penghasilan bulanan biasanya dari mana saja? Boleh sebutin semuanya, misalnya gaji, freelance, atau usaha.",
  "Pengeluaran rutin bulanan yang paling besar biasanya buat apa?",
  "Terakhir, ada target tabungan yang sedang dikejar? Misalnya dana darurat, liburan, atau renovasi rumah.",
];

export function demoGreeting(familyName: string): string {
  return `Halo! Aku Amina, asisten keuangan buat ${familyName}. Sebelum mulai, boleh aku tanya beberapa hal dasar biar bantuanku makin pas? Santai aja, kalau belum tahu jawabannya bisa dilewati dulu.`;
}
