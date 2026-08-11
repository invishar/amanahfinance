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

export function initials(name: string): string {
  return (name.trim()[0] ?? "?").toUpperCase();
}

export function firstName(name: string): string {
  return name.trim().split(" ")[0] ?? name;
}
