"use client";

// Server state = TanStack Query. Komponen tidak menyalin hasilnya ke state
// lokal (kecuali draft form), dan tidak menghitung ulang angka turunan.

import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseQueryResult,
} from "@tanstack/react-query";

import { api, type Schemas } from "@/lib/api/client";
import { qk } from "@/lib/api/keys";
import { useSession } from "@/lib/auth";

export type Account = Schemas["Account"];
export type AnalyticsSummary = Schemas["AnalyticsSummary"];
export type ChatMessage = Schemas["ChatMessage"];
export type ChatThread = Schemas["ChatThread"];
export type Family = Schemas["Family"];
export type FamilyInvite = Schemas["FamilyInvite"];
export type FamilyMember = Schemas["FamilyMember"];
export type IncomeSource = Schemas["IncomeSource"];
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
function useInvalidateAll() {
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
