"use client";

// Server state = TanStack Query. Komponen tidak menyalin hasilnya ke state
// lokal (kecuali draft form), dan tidak menghitung ulang angka turunan.

import { useEffect, useState } from "react";
import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseQueryResult,
} from "@tanstack/react-query";

import { API_BASE_URL, api, type Schemas } from "@/lib/api/client";
import { qk } from "@/lib/api/keys";
import { useSession } from "@/lib/auth";
import { tokenStore } from "@/lib/token-store";

export type Account = Schemas["Account"];
export type AiAction = Schemas["AiAction"];
export type AnalyticsSummary = Schemas["AnalyticsSummary"];
export type ChatMessage = Schemas["ChatMessage"];
export type ChatThread = Schemas["ChatThread"];
export type Family = Schemas["Family"];
export type FamilyInvite = Schemas["FamilyInvite"];
export type FamilyMember = Schemas["FamilyMember"];
export type IncomeSource = Schemas["IncomeSource"];
export type OnboardingAnswer = Schemas["OnboardingAnswer"];
export type SavingsGoal = Schemas["SavingsGoal"];
export type Transaction = Schemas["Transaction"];
export type Wallet = Schemas["Wallet"];

/** Bulan berjalan dalam format `YYYY-MM` (zona tampilan Asia/Jakarta). */
export function currentMonth(): string {
  return new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Jakarta",
    year: "numeric",
    month: "2-digit",
  })
    .format(new Date())
    .slice(0, 7);
}

/* --- Family --------------------------------------------------------------
   API tidak punya header X-Family-Id: scope ikut token. Family aktif =
   entri pertama `GET /families`. Pemilih multi-family = follow-up. */

export function useFamilies(): UseQueryResult<Family[]> {
  const { token } = useSession();
  return useQuery({
    queryKey: qk.families,
    queryFn: () => api.list<Family>("/families"),
    enabled: Boolean(token),
  });
}

export function useActiveFamily() {
  const query = useFamilies();
  const family = query.data?.[0] ?? null;
  return {
    family,
    familyId: family?.id ?? null,
    isLoading: query.isPending,
    isError: query.isError,
  };
}

/** Query yang butuh family: mati sampai familyId diketahui. */
function useFamilyQuery<T>(
  key: (familyId: string) => readonly unknown[],
  path: string,
) {
  const { familyId } = useActiveFamily();
  return useQuery({
    queryKey: familyId ? key(familyId) : ["pending"],
    queryFn: () => api.list<T>(path),
    enabled: Boolean(familyId),
  });
}

/* --- Read ---------------------------------------------------------------- */

export const useWallets = () => useFamilyQuery<Wallet>(qk.wallets, "/wallets");
export const useAccounts = () =>
  useFamilyQuery<Account>(qk.accounts, "/accounts");
export const useIncomeSources = () =>
  useFamilyQuery<IncomeSource>(qk.incomeSources, "/income-sources");
export const useSavingsGoals = () =>
  useFamilyQuery<SavingsGoal>(qk.savingsGoals, "/savings-goals");
export const useTransactions = () =>
  useFamilyQuery<Transaction>(qk.transactions, "/transactions");
export const useFamilyMembers = () =>
  useFamilyQuery<FamilyMember>(qk.members, "/family-members");

export function useAnalytics(month = currentMonth()) {
  const { familyId } = useActiveFamily();
  return useQuery({
    queryKey: familyId ? qk.analytics(familyId, month) : ["pending"],
    queryFn: () =>
      api.one<AnalyticsSummary>("GET", `/analytics/summary?month=${month}`),
    enabled: Boolean(familyId),
  });
}

/* --- Mutations ----------------------------------------------------------- */

/** Semua data turunan ikut berubah setiap kali entitas ditulis. */
export function useInvalidateAll() {
  const queryClient = useQueryClient();
  const { familyId } = useActiveFamily();
  return () => {
    if (!familyId) return;
    for (const key of [
      qk.wallets(familyId),
      qk.accounts(familyId),
      qk.incomeSources(familyId),
      qk.savingsGoals(familyId),
      qk.transactions(familyId),
    ]) {
      queryClient.invalidateQueries({ queryKey: key });
    }
    queryClient.invalidateQueries({ queryKey: ["analytics", familyId] });
  };
}

interface EntityConfig {
  path: string;
}

const ENTITY: Record<string, EntityConfig> = {
  wallet: { path: "/wallets" },
  account: { path: "/accounts" },
  income: { path: "/income-sources" },
  goal: { path: "/savings-goals" },
};

export type EntityKind = keyof typeof ENTITY;

export function useSaveEntity(kind: EntityKind) {
  const invalidate = useInvalidateAll();
  return useMutation({
    mutationFn: ({ id, body }: { id?: string; body: Record<string, unknown> }) =>
      id
        ? api.one("PUT", `${ENTITY[kind].path}/${id}`, body)
        : api.one("POST", ENTITY[kind].path, body),
    onSuccess: invalidate,
  });
}

export function useDeleteEntity(kind: EntityKind) {
  const invalidate = useInvalidateAll();
  return useMutation({
    mutationFn: (id: string) =>
      api.request<void>("DELETE", `${ENTITY[kind].path}/${id}`),
    onSuccess: invalidate,
  });
}

/* --- Family & undangan ---------------------------------------------------- */

export function useCreateFamily() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (name: string) => api.one<Family>("POST", "/families", { name }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: qk.families }),
  });
}

export function useAcceptInvite() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (token: string) =>
      api.one<FamilyMember>("POST", "/family-invites/accept", { token }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: qk.families }),
  });
}

export function useCreateInvite() {
  return useMutation({
    mutationFn: (body: { email?: string; phone?: string; role: string }) =>
      api.one<FamilyInvite>("POST", "/family-invites", body),
  });
}

/* --- Chat ---------------------------------------------------------------- */

export function useChatThreads() {
  return useFamilyQuery<ChatThread>(qk.chatThreads, "/chat-threads");
}

export function useCreateThread() {
  const queryClient = useQueryClient();
  const { familyId } = useActiveFamily();
  return useMutation({
    mutationFn: (kind: "general" | "onboarding" = "general") =>
      api.one<ChatThread>("POST", "/chat-threads", { kind }),
    onSuccess: () => {
      if (familyId)
        queryClient.invalidateQueries({ queryKey: qk.chatThreads(familyId) });
    },
  });
}

export function useMessages(threadId: string | null) {
  return useQuery({
    queryKey: threadId ? qk.messages(threadId) : ["pending"],
    queryFn: () =>
      api.list<ChatMessage>(`/chat-threads/${threadId}/messages`),
    enabled: Boolean(threadId),
  });
}

export function useSendMessage(threadId: string | null) {
  const queryClient = useQueryClient();
  const key = threadId ? qk.messages(threadId) : ["pending"];

  return useMutation({
    mutationFn: (body: {
      content: string;
      input_mode?: "text" | "voice" | "image";
    }) =>
      api.one<ChatMessage>("POST", `/chat-threads/${threadId}/messages`, body),

    // Optimistic UI: pesan langsung tampil, dan dikembalikan bila API gagal.
    onMutate: async (body) => {
      if (!threadId) return { previous: undefined };
      await queryClient.cancelQueries({ queryKey: key });
      const previous = queryClient.getQueryData<ChatMessage[]>(key);
      const optimistic: ChatMessage = {
        id: `optimistic-${Date.now()}`,
        thread_id: threadId,
        role: "user",
        content: body.content,
        input_mode: body.input_mode ?? "text",
        created_at: new Date().toISOString(),
      };
      queryClient.setQueryData<ChatMessage[]>(key, [
        ...(previous ?? []),
        optimistic,
      ]);
      return { previous };
    },
    onError: (_error, _body, context) => {
      if (context?.previous) queryClient.setQueryData(key, context.previous);
    },
    onSettled: () => {
      if (threadId) queryClient.invalidateQueries({ queryKey: key });
    },
  });
}

/**
 * `GET /onboarding-answers` (API-v1.md "Onboarding Answers") -- sumber
 * kebenaran untuk bubble jawaban user selama wawancara awal. Dipakai supaya
 * bubble itu tidak hilang saat user pindah halaman lalu balik lagi ke
 * `/chat`: jawaban onboarding sengaja TIDAK ikut tersimpan sebagai
 * `ChatMessage` (lihat `useCreateOnboardingAnswer`), jadi kalau cuma
 * mengandalkan state lokal komponen, bubble-nya lenyap begitu komponen
 * unmount.
 */
export const useOnboardingAnswers = () =>
  useFamilyQuery<OnboardingAnswer>(qk.onboardingAnswers, "/onboarding-answers");

/**
 * Wawancara awal (CLAUDE.md "Alur AI"): naskah & urutan pertanyaan hidup di
 * server, klien cuma tahu `question_key` yang sedang aktif lewat
 * `ChatThread.onboarding.question_key` (lihat API-v1.md "Onboarding Answers").
 * Setiap POST yang berhasil (jawaban asli maupun `skipped=true`) membuat
 * server menyisipkan pertanyaan berikutnya ke thread yang sama -- invalidasi
 * chat-threads (progres), messages (pertanyaan baru), & onboarding-answers
 * (bubble jawaban) supaya ketiganya segar.
 */
export function useCreateOnboardingAnswer(threadId: string | null) {
  const queryClient = useQueryClient();
  const { familyId } = useActiveFamily();

  return useMutation({
    mutationFn: (body: {
      question_key: string;
      answer?: Record<string, unknown> | null;
      skipped?: boolean;
    }) => api.one<OnboardingAnswer>("POST", "/onboarding-answers", body),
    onSuccess: () => {
      if (familyId) {
        queryClient.invalidateQueries({ queryKey: qk.chatThreads(familyId) });
        queryClient.invalidateQueries({
          queryKey: qk.onboardingAnswers(familyId),
        });
      }
      if (threadId)
        queryClient.invalidateQueries({ queryKey: qk.messages(threadId) });
    },
  });
}

/**
 * Balasan Amina, kartu aksi, dan error datang lewat SSE (`ChatStreamController`,
 * lihat CLAUDE.md "Alur AI"), bukan polling. Native `EventSource` tidak bisa
 * kirim header `Authorization`, jadi ini baca stream lewat `fetch` + reader
 * manual. Server sengaja menutup koneksi sendiri tiap ~20-25 detik (batas
 * `max_execution_time` shared hosting) dan mengirim event `retry` berisi
 * cursor -- loop di sini menyambung ulang pakai cursor itu selama komponen
 * masih mount, supaya dari sisi user terasa seperti satu koneksi panjang.
 */
function parseSseFrame(frame: string): { event: string; data: unknown } | null {
  const lines = frame.split("\n");
  const eventLine = lines.find((l) => l.startsWith("event: "));
  const dataLine = lines.find((l) => l.startsWith("data: "));
  if (!eventLine || !dataLine) return null;
  try {
    return {
      event: eventLine.slice("event: ".length).trim(),
      data: JSON.parse(dataLine.slice("data: ".length)),
    };
  } catch {
    return null;
  }
}

export function useChatStream(threadId: string | null, familyId: string | null) {
  const queryClient = useQueryClient();
  const [isThinking, setIsThinking] = useState(false);
  const [streamError, setStreamError] = useState<ChatMessage | null>(null);

  useEffect(() => {
    if (!threadId) return;

    let cancelled = false;
    let after: string | null = null;

    const appendMessage = (message: ChatMessage) => {
      // queryClient dari useQueryClient() stabil sepanjang hidup provider,
      // aman dipakai di sini tanpa masuk dependency array effect.
      queryClient.setQueryData<ChatMessage[]>(
        qk.messages(threadId),
        (prev) => {
          const list = prev ?? [];
          return list.some((m) => m.id === message.id)
            ? list
            : [...list, message];
        },
      );
    };

    // Payload SSE cuma { id, action, payload, created_at } (lihat
    // ChatStreamController::emitNewActionCards) -- server sudah menyaring
    // begitu pending & milik thread ini, jadi status pending diasumsikan di
    // sini, bukan dikirim ulang.
    const appendAiAction = (data: {
      id: string;
      action: AiAction["action"];
      payload: AiAction["payload"];
      created_at: string;
    }) => {
      if (!familyId) return;
      queryClient.setQueryData<AiAction[]>(qk.aiActions(familyId), (prev) => {
        const list = prev ?? [];
        if (list.some((a) => a.id === data.id)) return list;
        return [...list, { ...data, status: "pending" }];
      });
    };

    const connect = async () => {
      while (!cancelled) {
        const token = tokenStore.get();
        if (!token) return;

        let response: Response;
        try {
          const url = `${API_BASE_URL}/chat-threads/${threadId}/stream${after ? `?after=${encodeURIComponent(after)}` : ""}`;
          response = await fetch(url, {
            headers: { Authorization: `Bearer ${token}`, Accept: "text/event-stream" },
          });
        } catch {
          if (cancelled) return;
          await new Promise((r) => setTimeout(r, 3000));
          continue;
        }

        if (!response.ok || !response.body) {
          if (cancelled) return;
          await new Promise((r) => setTimeout(r, 3000));
          continue;
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = "";

        try {
          while (!cancelled) {
            const { done, value } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });
            const frames = buffer.split("\n\n");
            buffer = frames.pop() ?? "";

            for (const raw of frames) {
              const frame = parseSseFrame(raw);
              if (!frame) continue;

              if (frame.event === "thinking") {
                setIsThinking(true);
              } else if (frame.event === "message") {
                setIsThinking(false);
                appendMessage(frame.data as ChatMessage);
              } else if (frame.event === "error") {
                const data = frame.data as { id: string; content: string; created_at: string };
                setIsThinking(false);
                const errorMessage: ChatMessage = {
                  id: data.id,
                  thread_id: threadId,
                  role: "system",
                  content: data.content,
                  created_at: data.created_at,
                };
                appendMessage(errorMessage);
                setStreamError(errorMessage);
              } else if (frame.event === "retry") {
                after = (frame.data as { after: string }).after;
              } else if (frame.event === "action_card") {
                appendAiAction(
                  frame.data as {
                    id: string;
                    action: AiAction["action"];
                    payload: AiAction["payload"];
                    created_at: string;
                  },
                );
              }
            }
          }
        } catch {
          // Koneksi putus di tengah jalan -- reconnect pakai cursor terakhir.
        }
      }
    };

    connect();

    return () => {
      cancelled = true;
    };
  }, [threadId, familyId, queryClient]);

  return { isThinking, streamError };
}

/* --- Ai actions (kartu aksi) ----------------------------------------------
   Draft dari AssistantService (create_transaction, dst), status pending
   sampai user confirm/reject -- lihat CLAUDE.md aturan #5. Tidak ada endpoint
   thread-scoped, jadi konsumen (chat/page.tsx) menyaring sendiri lewat
   message_id yang ada di thread yang sedang dibuka. */

export function usePendingAiActions() {
  return useFamilyQuery<AiAction>(qk.aiActions, "/ai-actions");
}

export function useConfirmAiAction() {
  const queryClient = useQueryClient();
  const { familyId } = useActiveFamily();
  const invalidateAll = useInvalidateAll();

  return useMutation({
    mutationFn: ({ id, edits }: { id: string; edits?: Record<string, unknown> }) =>
      api.one<AiAction>("POST", `/ai-actions/${id}/confirm`, edits ?? {}),
    onSuccess: (updated) => {
      if (familyId) {
        queryClient.setQueryData<AiAction[]>(qk.aiActions(familyId), (prev) =>
          (prev ?? []).map((a) => (a.id === updated.id ? updated : a)),
        );
      }
      invalidateAll();
    },
  });
}

export function useRejectAiAction() {
  const queryClient = useQueryClient();
  const { familyId } = useActiveFamily();

  return useMutation({
    mutationFn: (id: string) => api.one<AiAction>("POST", `/ai-actions/${id}/reject`),
    onSuccess: (updated) => {
      if (familyId) {
        queryClient.setQueryData<AiAction[]>(qk.aiActions(familyId), (prev) =>
          (prev ?? []).map((a) => (a.id === updated.id ? updated : a)),
        );
      }
    },
  });
}
