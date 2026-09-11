// Satu-satunya tempat `fetch` terjadi. Tidak ada komponen yang boleh
// memanggil fetch sendiri. Tipe request/response berasal dari schema.d.ts
// hasil generate `npm run api:types` — jangan diketik ulang.

import type { components } from "@/lib/api/schema";

export type Schemas = components["schemas"];

// Klien sekarang satu origin dengan API (Laravel yang melayani halamannya),
// jadi path relatif sudah cukup dan tidak ada lagi konfigurasi lintas domain.
const BASE_URL = (import.meta.env?.VITE_API_URL ?? "/api/v1").replace(/\/+$/, "");

/** Dipakai di luar `request()` sendiri, mis. SSE (`fetch` manual, bukan lewat sini). */
export const API_BASE_URL = BASE_URL;

/** Envelope API: list berhalaman vs objek tunggal. */
export interface Paginated<T> {
  data: T[];
  links: Schemas["PaginationLinks"];
  meta: Schemas["PaginationMeta"];
}

export type FieldErrors = Record<string, string[]>;

export class ApiError extends Error {
  readonly status: number;
  readonly fieldErrors: FieldErrors;

  constructor(status: number, message: string, fieldErrors: FieldErrors = {}) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.fieldErrors = fieldErrors;
  }

  /** Pesan siap tampil untuk satu field (422). */
  fieldMessage(field: string): string | undefined {
    const raw = this.fieldErrors[field]?.[0];
    return raw ? translateValidation(raw) : undefined;
  }
}

/* --- Sesi ----------------------------------------------------------------
   Tidak ada lagi Bearer token di localStorage: sesi dipegang cookie httpOnly
   milik Laravel, dan `credentials: "same-origin"` di bawah yang mengirimnya.
   Konsekuensinya request yang mengubah data wajib membawa CSRF token, diambil
   dari cookie XSRF-TOKEN yang dipasang middleware web Laravel. */

function xsrfToken(): string | null {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : null;
}

let onUnauthorized: (() => void) | null = null;

/** Dipanggil sekali oleh AuthProvider: reaksi global terhadap 401. */
export function setUnauthorizedHandler(handler: (() => void) | null) {
  onUnauthorized = handler;
}

/* --- Pesan validasi ------------------------------------------------------
   Backend masih mengirim kunci mentah (`validation.required`). Pemetaan ini
   sementara — hapus begitu API mengirim kalimat jadi. */

const VALIDATION_MESSAGES: Record<string, string> = {
  "validation.required": "Wajib diisi.",
  "validation.required_without": "Wajib diisi.",
  "validation.email": "Format email tidak valid.",
  "validation.unique": "Sudah dipakai, coba nama lain.",
  "validation.confirmed": "Konfirmasi kata sandi tidak cocok.",
  "validation.min.string": "Terlalu pendek.",
  "validation.max.string": "Terlalu panjang.",
  "validation.min.numeric": "Nilainya terlalu kecil.",
  "validation.integer": "Harus berupa angka bulat.",
  "validation.date": "Tanggal tidak valid.",
  "validation.in": "Pilihan tidak valid.",
  "validation.exists": "Data tidak ditemukan.",
};

export function translateValidation(raw: string): string {
  if (VALIDATION_MESSAGES[raw]) return VALIDATION_MESSAGES[raw];
  // Kalimat asli (bukan kunci) langsung dipakai.
  return raw.includes(" ") ? raw : "Isian ini belum benar.";
}

/* --- Request ------------------------------------------------------------- */

type Method = "GET" | "POST" | "PUT" | "DELETE";

async function request<T>(
  method: Method,
  path: string,
  body?: unknown,
): Promise<T> {
  const csrf = method === "GET" ? null : xsrfToken();
  let response: Response;
  try {
    response = await fetch(BASE_URL + path, {
      method,
      headers: {
        Accept: "application/json",
        ...(body !== undefined ? { "Content-Type": "application/json" } : {}),
        ...(csrf ? { "X-XSRF-TOKEN": csrf } : {}),
      },
      body: body !== undefined ? JSON.stringify(body) : undefined,
      credentials: "same-origin",
      cache: "no-store",
    });
  } catch {
    throw new ApiError(0, "Tidak bisa menghubungi server. Cek koneksi kamu.");
  }

  if (response.status === 204) return undefined as T;

  const payload = await response.json().catch(() => null);

  if (!response.ok) {
    const message =
      (payload as { message?: string } | null)?.message ??
      "Terjadi kesalahan di server.";
    const fieldErrors =
      (payload as { errors?: FieldErrors } | null)?.errors ?? {};

    // 401 di sini selalu berarti "sesi mati": login sendiri tidak lewat
    // klien ini lagi (form Inertia ke route web), jadi tidak ada kasus 401
    // yang artinya "kredensial request ini salah".
    if (response.status === 401) onUnauthorized?.();

    throw new ApiError(
      response.status,
      response.status === 422 ? "Periksa lagi isiannya." : message,
      fieldErrors,
    );
  }

  return payload as T;
}

/** Endpoint objek tunggal: buang envelope `{ data }`. */
async function one<T>(method: Method, path: string, body?: unknown): Promise<T> {
  const payload = await request<{ data: T }>(method, path, body);
  return payload.data;
}

/** Endpoint list: kembalikan `data` saja (meta dipakai lewat `listRaw`). */
async function list<T>(path: string): Promise<T[]> {
  const payload = await request<Paginated<T>>("GET", path);
  return payload.data;
}

export const api = {
  request,
  one,
  list,
  listRaw: <T>(path: string) => request<Paginated<T>>("GET", path),
};
