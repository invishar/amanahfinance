// AmanaFinance — mock data layer + helpers (frontend-only, mirrors future Supabase shape)

export const family = { id: 'fam1', name: 'Keluarga Pratama' };
export const currentUser = { id: 'u1', name: 'Rizki Pratama', role: 'admin' };

export const wallets = [
  { id: 'w1', name: 'Belanja Harian', monthly_budget: 2000000, icon: 'shopping-cart' },
  { id: 'w2', name: 'Transportasi', monthly_budget: 800000, icon: 'car' },
  { id: 'w3', name: 'Hiburan', monthly_budget: 500000, icon: 'film' },
  { id: 'w4', name: 'Tagihan & Utilitas', monthly_budget: 1500000, icon: 'file-text' },
];

export const bankAccounts = [
  { id: 'a1', name: 'BCA', account_type: 'bank', current_balance: 12500000 },
  { id: 'a2', name: 'GoPay', account_type: 'ewallet', current_balance: 850000 },
  { id: 'a3', name: 'Tunai', account_type: 'cash', current_balance: 300000 },
];

export const incomeSources = [
  { id: 's1', name: 'Gaji Bulanan' },
  { id: 's2', name: 'Freelance Desain' },
];

export const transactions = [
  { id: 't1', type: 'income', amount: 14500000, wallet_id: null, account_id: 'a1', source_id: 's1', note: 'Gaji Agustus', transaction_date: '2026-08-01' },
  { id: 't2', type: 'expense', amount: 350000, wallet_id: 'w1', account_id: 'a2', source_id: null, note: 'Belanja Indomaret', transaction_date: '2026-08-02' },
  { id: 't3', type: 'expense', amount: 45000, wallet_id: 'w2', account_id: 'a2', source_id: null, note: 'Gojek ke kantor', transaction_date: '2026-08-02' },
  { id: 't4', type: 'expense', amount: 120000, wallet_id: 'w3', account_id: 'a3', source_id: null, note: 'Nonton bioskop', transaction_date: '2026-08-03' },
  { id: 't5', type: 'expense', amount: 480000, wallet_id: 'w4', account_id: 'a1', source_id: null, note: 'Token listrik', transaction_date: '2026-08-04' },
  { id: 't6', type: 'expense', amount: 275000, wallet_id: 'w1', account_id: 'a1', source_id: null, note: 'Belanja mingguan', transaction_date: '2026-08-05' },
  { id: 't7', type: 'income', amount: 2200000, wallet_id: null, account_id: 'a2', source_id: 's2', note: 'Proyek logo klien', transaction_date: '2026-08-06' },
  { id: 't8', type: 'expense', amount: 65000, wallet_id: 'w2', account_id: 'a2', source_id: null, note: 'Parkir & tol', transaction_date: '2026-08-06' },
  { id: 't9', type: 'expense', amount: 187500, wallet_id: 'w1', account_id: 'a2', source_id: null, note: 'Belanja bulanan Indomaret', transaction_date: '2026-08-07' },
  { id: 't10', type: 'expense', amount: 210000, wallet_id: 'w4', account_id: 'a1', source_id: null, note: 'Internet rumah', transaction_date: '2026-08-08' },
];

export const savingsGoals = [
  { id: 'g1', target_name: 'Dana Darurat', target_amount: 30000000, current_amount: 18500000, deadline: '2026-12-31', avg_monthly_contribution: 1500000 },
  { id: 'g2', target_name: 'Liburan Keluarga', target_amount: 15000000, current_amount: 6200000, deadline: '2027-06-01', avg_monthly_contribution: 900000 },
  { id: 'g3', target_name: 'Renovasi Dapur', target_amount: 25000000, current_amount: 4000000, deadline: '2027-03-01', avg_monthly_contribution: 700000 },
];

export const walletIconMap = { 'shopping-cart': 'shopping-cart', car: 'car', film: 'film', 'file-text': 'file-text', target: 'target', home: 'home' };
export const accountTypeIcon = { bank: 'landmark', ewallet: 'smartphone', cash: 'banknote' };
export const accountTypeLabel = { bank: 'Bank', ewallet: 'E-Wallet', cash: 'Tunai' };

export function formatRupiah(n) {
  const v = Math.round(Math.abs(n || 0));
  return 'Rp ' + v.toLocaleString('id-ID');
}

export function formatDateID(dateStr) {
  const d = new Date(dateStr);
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

export function monthsUntil(deadline) {
  const now = new Date('2026-08-08');
  const d = new Date(deadline);
  return Math.max(1, Math.round((d - now) / (1000 * 60 * 60 * 24 * 30)));
}

export function walletSpent(walletId, txs) {
  return txs.filter((t) => t.wallet_id === walletId && t.type === 'expense').reduce((s, t) => s + t.amount, 0);
}

export function estimateGoalCompletion(goal) {
  const remaining = Math.max(0, goal.target_amount - goal.current_amount);
  const monthsNeeded = Math.ceil(remaining / (goal.avg_monthly_contribution || 1));
  const now = new Date('2026-08-08');
  now.setMonth(now.getMonth() + monthsNeeded);
  return now.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
}

export function newId(prefix) {
  return prefix + '_' + Math.random().toString(36).slice(2, 8);
}

const walletNameGuess = (text) => {
  const t = text.toLowerCase();
  if (t.includes('makan') || t.includes('warteg') || t.includes('belanja')) return 'w1';
  if (t.includes('grab') || t.includes('gojek') || t.includes('bensin') || t.includes('parkir')) return 'w2';
  if (t.includes('nonton') || t.includes('game') || t.includes('hiburan')) return 'w3';
  return 'w1';
};

// Mock AI reply generator — mirrors the shape sendChatMessage(text, familyId) will have once
// wired to the real backend. Returns { role: 'assistant', content, actionCard? }.
export function mockAssistantReply(userText, scenario) {
  const s = scenario || guessScenario(userText);
  if (s === 'transaction_text' || s === 'transaction_voice') {
    const amountMatch = userText.match(/(\d[\d.,]*)\s*(rb|ribu|k)?/i);
    let amount = 25000;
    if (amountMatch) {
      let n = parseFloat(amountMatch[1].replace(/\./g, '').replace(',', '.'));
      if (amountMatch[2]) n *= 1000;
      if (n > 0) amount = n;
    }
    return {
      role: 'assistant',
      content: 'Oke, aku catat ya. Boleh dikonfirmasi dulu sebelum aku simpan:',
      actionCard: {
        type: 'action_confirm',
        action: 'create_transaction',
        title: 'Catat Transaksi Baru',
        fields: [
          { label: 'Jenis', value: 'Pengeluaran' },
          { label: 'Nominal', value: formatRupiah(amount) },
          { label: 'Wallet', value: wallets.find((w) => w.id === walletNameGuess(userText))?.name || 'Belanja Harian' },
          { label: 'Sumber Dana', value: 'GoPay' },
          { label: 'Catatan', value: userText.length > 40 ? userText.slice(0, 40) + '…' : userText },
        ],
        payload: { type: 'expense', amount, wallet_id: walletNameGuess(userText), account_id: 'a2', note: userText.slice(0, 60) },
      },
    };
  }
  if (s === 'transaction_receipt') {
    return {
      role: 'assistant',
      content: 'Struknya sudah aku baca. Ini yang aku tangkap, cek dulu ya sebelum disimpan:',
      actionCard: {
        type: 'action_confirm',
        action: 'create_transaction',
        title: 'Transaksi dari Struk',
        fields: [
          { label: 'Jenis', value: 'Pengeluaran' },
          { label: 'Nominal', value: formatRupiah(187500) },
          { label: 'Wallet', value: 'Belanja Harian' },
          { label: 'Sumber Dana', value: 'BCA' },
          { label: 'Catatan', value: 'Belanja bulanan Indomaret' },
        ],
        payload: { type: 'expense', amount: 187500, wallet_id: 'w1', account_id: 'a1', note: 'Belanja bulanan Indomaret' },
      },
    };
  }
  if (s === 'create_wallet') {
    return {
      role: 'assistant',
      content: 'Siap, aku siapkan wallet barunya. Ini rangkumannya:',
      actionCard: {
        type: 'action_confirm',
        action: 'create_wallet',
        title: 'Buat Wallet Baru',
        fields: [
          { label: 'Nama Wallet', value: 'Kesehatan' },
          { label: 'Budget Bulanan', value: formatRupiah(600000) },
        ],
        payload: { name: 'Kesehatan', monthly_budget: 600000, icon: 'target' },
      },
    };
  }
  if (s === 'create_account') {
    return {
      role: 'assistant',
      content: 'Baik, aku daftarkan akunnya dulu ya. Tolong dicek:',
      actionCard: {
        type: 'action_confirm',
        action: 'create_account',
        title: 'Tambah Akun Baru',
        fields: [
          { label: 'Nama Akun', value: 'Dana' },
          { label: 'Jenis', value: 'E-Wallet' },
          { label: 'Saldo Awal', value: formatRupiah(150000) },
        ],
        payload: { name: 'Dana', account_type: 'ewallet', current_balance: 150000 },
      },
    };
  }
  // advice
  return {
    role: 'assistant',
    content:
      'Bulan ini pengeluaran di wallet Belanja Harian sudah kepakai sekitar 60% dari budget — masih aman. Yang perlu diperhatikan: Hiburan sudah lewat separuh budget padahal baru tanggal 8. Kalau mau, aku bisa bantu geser sedikit alokasi dari Transportasi bulan ini supaya Dana Darurat tetap jalan sesuai target.',
  };
}

function guessScenario(text) {
  const t = text.toLowerCase();
  if (t.includes('wallet') || t.includes('dompet')) return 'create_wallet';
  if (t.includes('akun') || t.includes('rekening') || t.includes('bank baru')) return 'create_account';
  if (t.includes('saran') || t.includes('analisa') || t.includes('gimana keuangan')) return 'advice';
  return 'transaction_text';
}
