"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";

import { ApiError } from "@/lib/api/client";
import { AuthProvider } from "@/lib/auth";
import { UiProvider } from "@/lib/ui-store";

export function Providers({ children }: { children: ReactNode }) {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            // Data finansial tetap di-invalidasi segera setelah setiap mutasi.
            // Cache lebih panjang membuat perpindahan halaman tidak memanggil
            // ulang endpoint yang sama berulang kali di shared hosting.
            staleTime: 120_000,
            refetchOnWindowFocus: false,
            // 4xx tidak akan membaik dengan diulang.
            retry: (failureCount, error) =>
              error instanceof ApiError && error.status >= 400 && error.status < 500
                ? false
                : failureCount < 2,
          },
          mutations: { retry: false },
        },
      }),
  );

  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <UiProvider>{children}</UiProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}
