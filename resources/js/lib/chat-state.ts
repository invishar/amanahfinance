import type { AiAction } from "./api/hooks";

// A delayed list response or replayed SSE event cannot reopen a settled card.
export function mergeAiActions(previous: AiAction[] = [], incoming: AiAction[] = []): AiAction[] {
  const byId = new Map(previous.map((action) => [action.id, action]));
  for (const action of incoming) {
    const old = byId.get(action.id);
    if (old?.status && old.status !== "pending" && (!action.status || action.status === "pending")) continue;
    byId.set(action.id, { ...old, ...action });
  }
  return [...byId.values()];
}

export function parseSseFrame(frame: string): { event: string; data: unknown } | null {
  const lines = frame.split(/\r?\n/);
  const event = lines.find((line) => line.startsWith("event:"))?.slice(6).trim();
  const data = lines.filter((line) => line.startsWith("data:")).map((line) => line.slice(5).trimStart()).join("\n");
  if (!event || !data) return null;
  try { return { event, data: JSON.parse(data) }; } catch { return null; }
}

export const CHAT_PROGRESS: Record<string, string> = {
  queued: "Amina sedang menunggu giliran memproses pesan...",
  context: "Amina sedang membaca konteks keuanganmu...",
  thinking: "Amina sedang memahami pesan dan menyiapkan jawaban...",
  drafting: "Amina sedang membuat formulir konfirmasi...",
  reading_data: "Amina sedang memeriksa data keuanganmu...",
  composing: "Amina sedang merangkai balasan...",
  retrying: "Amina sedang mencoba menghubungi layanan AI lagi...",
  reconnecting: "Amina sedang menyambungkan kembali percakapan...",
};
