// Satu-satunya tempat `fetch` terjadi. Tidak ada komponen yang boleh
// memanggil fetch sendiri. Tipe request/response berasal dari schema.d.ts
// hasil generate `npm run api:types` — jangan diketik ulang.

import type { components } from "@/lib/api/schema";
import { tokenStore } from "@/lib/token-store";

export type Schemas = components["schemas"];

const BASE_URL = (
  process.env.NEXT_PUBLIC_API_URL ?? "http://127.0.0.1:8000/api/v1"
).replace(/\/+$/, "");

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

/* --- Token ---------------------------------------------------------------
   Dibaca langsung dari `tokenStore` setiap request. Sengaja tidak dicermin ke
   variabel modul lewat useEffect: request pertama setelah reload bisa jalan
   sebelum effect sempat memasang token, dan hasilnya 401 palsu. */

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
  const token = tokenStore.get();
  let response: Response;
  try {
    response = await fetch(BASE_URL + path, {
      method,
      headers: {
        Accept: "application/json",
        ...(body !== undefined ? { "Content-Type": "application/json" } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: body !== undefined ? JSON.stringify(body) : undefined,
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
