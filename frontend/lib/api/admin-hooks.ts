"use client";

// Hooks platform admin: /admin/*, /llm-settings — lintas-family, tidak pernah
// butuh familyId (beda dengan lib/api/hooks.ts yang semuanya scoped ke family
// aktif). Tetap lewat api client yang sama, satu-satunya tempat fetch terjadi.

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { api, type Paginated, type Schemas } from "@/lib/api/client";
import { qk } from "@/lib/api/keys";

export type AdminUser = Schemas["AdminUser"];
export type AdminUserDetail = Schemas["AdminUserDetail"];
export type Subscription = Schemas["Subscription"];
export type LlmSetting = Schemas["LlmSetting"];

function query(params: Record<string, string | number | undefined>): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== "") search.set(key, String(value));
  }
  const qs = search.toString();
  return qs ? `?${qs}` : "";
}

/* --- Users ----------------------------------------------------------------- */

export function useAdminUsers(search: string, page: number) {
  return useQuery({
    queryKey: qk.adminUsers(search, page),
    queryFn: () =>
      api.listRaw<AdminUser>(`/admin/users${query({ search, page })}`),
    placeholderData: (previous) => previous,
  });
}

export function useAdminUser(id: string | null) {
  return useQuery({
    queryKey: id ? qk.adminUser(id) : ["pending"],
    queryFn: () => api.one<AdminUserDetail>("GET", `/admin/users/${id}`),
    enabled: Boolean(id),
  });
}

/* --- Payments (subscriptions) ---------------------------------------------- */

export function useAdminSubscriptions(status: string, page: number) {
  return useQuery({
    queryKey: qk.adminSubscriptions(status, page),
    queryFn: () =>
      api.listRaw<Subscription>(`/admin/subscriptions${query({ status, page })}`),
    placeholderData: (previous) => previous,
  });
}

function useInvalidateAdminSubscriptions() {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: ["admin-subscriptions"] });
}

export function useActivateSubscription() {
  const invalidate = useInvalidateAdminSubscriptions();
  return useMutation({
    mutationFn: (id: string) =>
      api.one<Subscription>("POST", `/admin/subscriptions/${id}/activate`),
    onSuccess: invalidate,
  });
}

export function useRejectSubscription() {
  const invalidate = useInvalidateAdminSubscriptions();
  return useMutation({
    mutationFn: ({ id, review_note }: { id: string; review_note: string }) =>
      api.one<Subscription>("POST", `/admin/subscriptions/${id}/reject`, {
        review_note,
      }),
    onSuccess: invalidate,
  });
}

/* --- LLM settings ------------------------------------------------------------ */

export function useLlmSetting() {
  return useQuery({
    queryKey: qk.llmSetting,
    queryFn: () => api.one<LlmSetting>("GET", "/llm-settings"),
  });
}

export function useUpdateLlmSetting() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (body: { key?: string; model: string; base_url?: string | null }) =>
      api.one<LlmSetting>("PUT", "/llm-settings", body),
    onSuccess: (data) => queryClient.setQueryData(qk.llmSetting, data),
  });
}

export type { Paginated };
