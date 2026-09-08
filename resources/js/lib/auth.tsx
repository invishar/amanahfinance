// Sesi Sanctum. Login/daftar/keluar tetap milik API (`/api/v1/auth/*`) —
// Inertia di aplikasi ini murni lapisan frontend, jadi tidak ada implementasi
// auth kedua di route web.
//
// Yang berubah dari versi Next: sesi tidak lagi dibawa Bearer token di
// localStorage, melainkan cookie httpOnly yang dibuka AuthController untuk
// request same-origin (Sanctum stateful). Konsekuensinya siapa yang login
// sudah diketahui server saat halaman dirender, jadi status sesi dibaca dari
// shared prop Inertia — tidak ada lagi fase "loading" menunggu hidrasi, dan
// tidak ada lagi permukaan XSS tempat token bisa dicuri (follow-up yang dulu
// dicatat di TaskProject.md).

import { router, usePage } from "@inertiajs/react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useCallback, useEffect, useMemo, type ReactNode } from "react";

import { api, setUnauthorizedHandler, type Schemas } from "@/lib/api/client";
import { qk } from "@/lib/api/keys";

type AuthPayload = Schemas["AuthPayload"];

export interface RegisterInput {
  full_name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface SessionUser {
  id: string;
  name: string;
  email: string | null;
}

interface SessionValue {
  user: SessionUser | null;
  status: "authenticated" | "anonymous";
  login: (email: string, password: string) => Promise<void>;
  register: (input: RegisterInput) => Promise<void>;
  logout: () => Promise<void>;
}

interface SharedProps {
  auth: { user: SessionUser | null };
  [key: string]: unknown;
}

/** Pemasang reaksi global terhadap 401; nilainya dibaca lewat `useSession`. */
export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient();

  // 401 dari endpoint manapun = sesi mati: bersihkan cache lalu kembali ke
  // login. `router.visit` (bukan location.href) supaya tetap satu SPA.
  useEffect(() => {
    setUnauthorizedHandler(() => {
      queryClient.clear();
      router.visit("/login", { replace: true });
    });
    return () => setUnauthorizedHandler(null);
  }, [queryClient]);

  return <>{children}</>;
}

export function useSession(): SessionValue {
  const { auth } = usePage<SharedProps>().props;
  const queryClient = useQueryClient();

  const login = useCallback(
    async (email: string, password: string) => {
      await api.one<AuthPayload>("POST", "/auth/login", { email, password });
      queryClient.clear();
    },
    [queryClient],
  );

  const register = useCallback(
    async (input: RegisterInput) => {
      await api.one<AuthPayload>("POST", "/auth/register", input);
      queryClient.clear();
    },
    [queryClient],
  );

  const logout = useCallback(async () => {
    try {
      await api.request<void>("POST", "/auth/logout");
    } catch {
      // Sesi mungkin sudah kedaluwarsa — cache lokal tetap dibersihkan.
    }
    queryClient.clear();
  }, [queryClient]);

  return useMemo<SessionValue>(
    () => ({
      user: auth.user,
      status: auth.user ? "authenticated" : "anonymous",
      login,
      register,
      logout,
    }),
    [auth.user, login, register, logout],
  );
}

/**
 * Profil lengkap dari `GET /auth/me` — dipakai untuk field yang tidak ikut
 * di-share per halaman (`is_admin`, `is_local`, avatar). Identitas dasar
 * cukup diambil dari `useSession()`.
 */
export function useMe() {
  const { user } = useSession();
  return useQuery({
    queryKey: qk.me,
    queryFn: () => api.one<Schemas["User"]>("GET", "/auth/me"),
    enabled: Boolean(user),
  });
}
