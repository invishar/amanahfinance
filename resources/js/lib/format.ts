// Format tampilan. Mata uang tanpa desimal, tanggal locale id-ID (Asia/Jakarta).

export function formatRupiah(n: number): string {
  const v = Math.round(Math.abs(n || 0));
  return "Rp " + v.toLocaleString("id-ID");
}

export function formatDateID(dateStr: string): string {
  const d = new Date(dateStr);
  return d.toLocaleDateString("id-ID", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

export function formatDayDateID(dateStr: string): string {
  const d = new Date(dateStr);
  return d.toLocaleDateString("id-ID", {
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
  if (diffDays === 0) return "Hari ini";
  if (diffDays === 1) return "Kemarin";
  return d.toLocaleDateString("id-ID", {
    day: "numeric",
    month: "long",
    year: "numeric",
  });
}

export function formatChatTimeLabel(ts: number): string {
  return new Date(ts).toLocaleTimeString("id-ID", {
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
