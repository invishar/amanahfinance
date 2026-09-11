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
import { mergeAiActions, parseSseFrame, CHAT_PROGRESS } from "@/lib/chat-state";

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
   Scope ikut sesi. Family aktif = entri pertama `GET /families`; pemilih
   multi-family (header X-Family-Id) = follow-up. */

export function useFamilies(): UseQueryResult<Family[]> {
  const { user } = useSession();
  return useQuery({
    queryKey: qk.families,
    queryFn: () => api.list<Family>("/families"),
    enabled: Boolean(user),
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

/**
 * Transaksi punya field wajib yang bergantung pada `type` (lihat CLAUDE.md
 * "Constraint transaksi") dan opsi select yang datang dari entitas lain
 * (wallet/akun/sumber/target) — tidak cocok dengan bentuk statis
 * `ENTITY_FORMS`, jadi mutasinya berdiri sendiri di luar `useSaveEntity`.
 */
export function useUpdateTransaction() {
  const invalidate = useInvalidateAll();
  return useMutation({
    mutationFn: ({ id, body }: { id: string; body: Record<string, unknown> }) =>
      api.one<Transaction>("PUT", `/transactions/${id}`, body),
    onSuccess: invalidate,
  });
}

export function useCreateTransaction() {
  const invalidate = useInvalidateAll();
  return useMutation({
    mutationFn: (body: Record<string, unknown>) =>
      api.one<Transaction>("POST", "/transactions", body),
    onSuccess: invalidate,
  });
}

export function useDeleteTransaction() {
  const invalidate = useInvalidateAll();
  return useMutation({
    mutationFn: (id: string) =>
      api.request<void>("DELETE", `/transactions/${id}`),
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
      api.list<ChatMessage>(`/chat-threads/${threadId}/messages?latest=1`),
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
      if (!threadId) return { previous: undefined, optimisticId: undefined };
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
      return { previous, optimisticId: optimistic.id };
    },
    onSuccess: (message, _body, context) => {
      queryClient.setQueryData<ChatMessage[]>(key, (previous) => (previous ?? []).map((item) => item.id === context?.optimisticId ? message : item));
    },
    onError: (_error, _body, context) => {
      queryClient.setQueryData(key, context?.previous ?? []);
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
 * Satu stream per pesan server, dimiliki ChatSessionProvider pada layout
 * persisten. Navigasi halaman tidak membatalkannya; logout/unmount layout
 * membatalkan koneksi. Hasil diselaraskan lewat API setelah seluruh frame
 * SSE diterima supaya balasan tidak memotong kartu yang menyusul.
 */
export function useChatStream(
  threadId: string | null,
  familyId: string | null,
  messageId: string | null,
) {
  const queryClient = useQueryClient();
  const [progress, setProgress] = useState("queued");
  const [streamError, setStreamError] = useState<ChatMessage | null>(null);

  useEffect(() => {
    if (!threadId || !familyId || !messageId) return;
    setProgress("queued");
    setStreamError(null);
    let cancelled = false;
    let after: string | null = null;
    const controller = new AbortController();
    const pause = () => new Promise<void>((resolve) => {
      const finish = () => { clearTimeout(timer); controller.signal.removeEventListener("abort", finish); resolve(); };
      const timer = setTimeout(finish, 2000);
      controller.signal.addEventListener("abort", finish, { once: true });
    });
    const reconcile = async () => {
      // Recover results produced before connection, including same-second
      // timestamps. Read the WHOLE stream before replacing the message list:
      // a message event may precede action_card in a separate network chunk.
      const [messages, actions] = await Promise.all([
        api.list<ChatMessage>(`/chat-threads/${threadId}/messages?latest=1`),
        api.list<AiAction>("/ai-actions"),
      ]);
      if (cancelled) return true;
      queryClient.setQueryData(qk.aiActions(familyId), (previous: AiAction[] | undefined) => mergeAiActions(previous, actions));
      queryClient.setQueryData(qk.messages(threadId), messages);
      const last = messages.at(-1);
      const complete = Boolean(last && last.role !== "user");
      if (complete) {
        if (last?.role === "system") setStreamError(last);
        queryClient.invalidateQueries({ queryKey: qk.chatThreads(familyId) });
        queryClient.invalidateQueries({ queryKey: qk.families });
      }
      return complete;
    };
    const connect = async () => {
      while (!cancelled) {
        let reader: ReadableStreamDefaultReader<Uint8Array> | undefined;
        try {
          const params = new URLSearchParams({ message_id: messageId });
          if (after) params.set("after", after);
          const response = await fetch(`${API_BASE_URL}/chat-threads/${threadId}/stream?${params}`, {
            headers: { Accept: "text/event-stream" },
            credentials: "same-origin",
            signal: controller.signal,
          });
          if (!response.ok || !response.body) {
            if ([401, 403, 404, 422].includes(response.status)) {
              setStreamError({ role: "system", content: "Percakapan tidak dapat diakses. Muat ulang halaman." });
              return;
            }
            throw new Error("Stream unavailable");
          }
          reader = response.body.getReader();
          const decoder = new TextDecoder();
          let buffer = "";
          while (!cancelled) {
            const { done, value } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });
            const frames = buffer.split(/\r?\n\r?\n/);
            buffer = frames.pop() ?? "";
            for (const raw of frames) {
              const frame = parseSseFrame(raw);
              if (!frame) continue;
              if (frame.event === "progress") {
                const data = frame.data as { message_id: string; stage: string };
                if (data.message_id === messageId && CHAT_PROGRESS[data.stage]) setProgress(data.stage);
              } else if (frame.event === "action_card") {
                const action = frame.data as AiAction;
                if (action.message_id === messageId) {
                  queryClient.setQueryData(qk.aiActions(familyId), (previous: AiAction[] | undefined) => mergeAiActions(previous, [{ ...action, status: "pending" }]));
                }
              } else if (frame.event === "retry") {
                after = (frame.data as { after: string }).after;
              }
              // message/error are reconciled after EOF, not appended without
              // their role and not allowed to abort a following action_card.
            }
          }
          if (!cancelled && await reconcile()) return;
        } catch {
          if (cancelled) return;
          setProgress("reconnecting");
          try { if (await reconcile()) return; } catch { /* Retry after a bounded pause. */ }
        } finally {
          await reader?.cancel().catch(() => {});
          reader?.releaseLock();
        }
        if (!cancelled) await pause();
      }
    };
    void connect();
    return () => { cancelled = true; controller.abort(); };
  }, [threadId, familyId, messageId, queryClient]);

  return { isThinking: Boolean(messageId), loadingText: CHAT_PROGRESS[progress], streamError };
}

/* --- Ai actions (kartu aksi) ----------------------------------------------
   Draft dari AssistantService (create_transaction, dst), status pending
   sampai user confirm/reject -- lihat CLAUDE.md aturan #5. Tidak ada endpoint
   thread-scoped, jadi konsumen (chat/page.tsx) menyaring sendiri lewat
   message_id yang ada di thread yang sedang dibuka. */

export function usePendingAiActions() {
  const { familyId } = useActiveFamily();
  return useQuery({
    queryKey: familyId ? qk.aiActions(familyId) : ["pending"],
    queryFn: () => api.list<AiAction>("/ai-actions"),
    enabled: Boolean(familyId),
    structuralSharing: (oldData, newData) => mergeAiActions(oldData as AiAction[] | undefined, newData as AiAction[]),
  });
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
          mergeAiActions(prev, [updated]),
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
          mergeAiActions(prev, [updated]),
        );
      }
    },
  });
}
