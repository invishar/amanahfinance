"use client";

// Token sesi disimpan di localStorage dan dibaca lewat useSyncExternalStore —
// bukan lewat setState di dalam effect, supaya tidak ada render bertingkat saat
// hidrasi dan tab lain ikut tersinkron lewat event `storage`.

const KEY = "amana.token";

const listeners = new Set<() => void>();

function emit() {
  listeners.forEach((listener) => listener());
}

export const tokenStore = {
  subscribe(listener: () => void) {
    listeners.add(listener);
    window.addEventListener("storage", listener);
    return () => {
      listeners.delete(listener);
      window.removeEventListener("storage", listener);
    };
  },
  get(): string | null {
    if (typeof window === "undefined") return null;
    return window.localStorage.getItem(KEY);
  },
  /** Snapshot saat render di server / sebelum hidrasi. */
  getServer(): string | null {
    return null;
  },
  set(token: string | null) {
    if (token) window.localStorage.setItem(KEY, token);
    else window.localStorage.removeItem(KEY);
    emit();
  },
};

/** `false` sampai komponen ter-hidrasi di browser. */
export const hydratedStore = {
  subscribe() {
    return () => {};
  },
  get() {
    return true;
  },
  getServer() {
    return false;
  },
};
