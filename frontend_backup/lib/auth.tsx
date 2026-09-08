"use client";

// Sesi Sanctum. Token disimpan di localStorage + memori (SPA, tanpa SSR data
// fetching). Konsekuensinya rentan XSS — pindah ke cookie httpOnly + SSR
// tercatat sebagai follow-up di TaskProject.md, bukan diputuskan diam-diam.

import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useSyncExternalStore,
  type ReactNode,
} from "react";

import { api, setUnauthorizedHandler, type Schemas } from "@/lib/api/client";
import { qk } from "@/lib/api/keys";
import { hydratedStore, tokenStore } from "@/lib/token-store";

type AuthPayload = Schemas["AuthPayload"];

export interface RegisterInput {
  full_name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

interface SessionValue {
  token: string | null;
  /** `loading` selama token belum terbaca dari localStorage (pra-hidrasi). */
  status: "loading" | "authenticated" | "anonymous";
  login: (email: string, password: string) => Promise<void>;
  register: (input: RegisterInput) => Promise<void>;
  logout: () => Promise<void>;
}

const SessionContext = createContext<SessionValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const token = useSyncExternalStore(
    tokenStore.subscribe,
    tokenStore.get,
    tokenStore.getServer,
  );
  const hydrated = useSyncExternalStore(
    hydratedStore.subscribe,
    hydratedStore.get,
    hydratedStore.getServer,
  );

  const queryClient = useQueryClient();
  const router = useRouter();

  const clearSession = useCallback(() => {
    tokenStore.set(null);
    queryClient.clear();
  }, [queryClient]);

  // 401 dari endpoint manapun = token mati: bersihkan lalu kembali ke login.
  useEffect(() => {
    setUnauthorizedHandler(() => {
      clearSession();
      router.replace("/login");
    });
    return () => setUnauthorizedHandler(null);
  }, [clearSession, router]);

  const applyToken = useCallback(
    (payload: AuthPayload) => {
      const next = payload.token ?? null;
      if (!next) throw new Error("Server tidak mengirim token.");
      tokenStore.set(next);
      queryClient.clear();
    },
    [queryClient],
  );

  const login = useCallback(
    async (email: string, password: string) => {
      applyToken(
        await api.one<AuthPayload>("POST", "/auth/login", { email, password }),
      );
    },
    [applyToken],
  );

  const register = useCallback(
    async (input: RegisterInput) => {
      applyToken(await api.one<AuthPayload>("POST", "/auth/register", input));
    },
    [applyToken],
  );

  const logout = useCallback(async () => {
    try {
      await api.request<void>("POST", "/auth/logout");
    } catch {
      // Token mungkin sudah kedaluwarsa — sesi lokal tetap dibersihkan.
    }
    clearSession();
  }, [clearSession]);

  const value = useMemo<SessionValue>(
    () => ({
      token,
      status: !hydrated ? "loading" : token ? "authenticated" : "anonymous",
      login,
      register,
      logout,
    }),
    [token, hydrated, login, register, logout],
  );

  return (
    <SessionContext.Provider value={value}>{children}</SessionContext.Provider>
  );
}

export function useSession(): SessionValue {
  const ctx = useContext(SessionContext);
  if (!ctx) throw new Error("useSession harus dipakai di dalam <AuthProvider>");
  return ctx;
}

export function useMe() {
  const { token } = useSession();
  return useQuery({
    queryKey: qk.me,
    queryFn: () => api.one<Schemas["User"]>("GET", "/auth/me"),
    enabled: Boolean(token),
  });
}
