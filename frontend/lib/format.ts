// Format tampilan mengikuti bahasa yang dipilih. Mata uang tetap Rupiah karena
// currency family saat ini IDR; hanya pemisah angka dan nama tanggal yang ikut
// locale antarmuka.

function displayLocale(): string {
  if (typeof window === "undefined") return "id-ID";
  return window.localStorage.getItem("amanafinance-locale") === "en" ? "en-ID" : "id-ID";
}

export function formatRupiah(n: number): string {
  const v = Math.round(Math.abs(n || 0));
  return new Intl.NumberFormat(displayLocale(), {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(v);
}

export function formatDateID(dateStr: string): string {
  const d = new Date(dateStr);
  return d.toLocaleDateString(displayLocale(), {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

export function formatDayDateID(dateStr: string): string {
  const d = new Date(dateStr);
  return d.toLocaleDateString(displayLocale(), {
    weekday: "long",
    day: "numeric",
    month: "long",
  });
}

export function formatChatDayLabel(ts: number): string {
  const d = new Date(ts);
  const now = new Date();
  const startOfDay = (date: Date) =>
    new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
  const diffDays = Math.round((startOfDay(now) - startOfDay(d)) / 86400000);
  if (diffDays === 0) return displayLocale() === "en-ID" ? "Today" : "Hari ini";
  if (diffDays === 1) return displayLocale() === "en-ID" ? "Yesterday" : "Kemarin";
  return d.toLocaleDateString(displayLocale(), {
    day: "numeric",
    month: "long",
    year: "numeric",
  });
}

export function formatChatTimeLabel(ts: number): string {
  return new Date(ts).toLocaleTimeString(displayLocale(), {
    hour: "2-digit",
    minute: "2-digit",
  });
}

export function initials(name: string): string {
  return (name.trim()[0] ?? "?").toUpperCase();
}

export function firstName(name: string): string {
  return name.trim().split(" ")[0] ?? name;
}
