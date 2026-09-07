"use client";

import "./globals.css";

export default function GlobalError({
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  return (
    <html lang="id">
      <body>
        <div
          style={{
            minHeight: "100dvh",
            display: "flex",
            flexDirection: "column",
            alignItems: "center",
            justifyContent: "center",
            gap: 16,
            padding: 26,
            textAlign: "center",
            background: "var(--color-bg, #fdf8f3)",
            color: "var(--color-text, #3a332c)",
          }}
        >
          <h1 style={{ fontSize: 20, fontWeight: 600 }}>Terjadi kesalahan</h1>
          <p style={{ fontSize: 15, lineHeight: 1.6, maxWidth: 320 }}>
            Amina lagi ada gangguan. Coba muat ulang halamannya.
          </p>
          <button
            onClick={() => reset()}
            style={{
              padding: "10px 22px",
              borderRadius: 999,
              border: "none",
              background: "var(--color-accent, #b8912b)",
              color: "#fff",
              fontWeight: 600,
              cursor: "pointer",
            }}
          >
            Coba lagi
          </button>
        </div>
      </body>
    </html>
  );
}
