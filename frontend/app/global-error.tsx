"use client";

import "./globals.css";
import { useState } from "react";

export default function GlobalError({
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  const [english] = useState(() =>
    typeof window !== "undefined" && window.localStorage.getItem("amanafinance-locale") === "en",
  );
  return (
    <html lang={english ? "en" : "id"}>
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
          <h1 style={{ fontSize: 20, fontWeight: 600 }}>
            {english ? "Something went wrong" : "Terjadi kesalahan"}
          </h1>
          <p style={{ fontSize: 15, lineHeight: 1.6, maxWidth: 320 }}>
            {english ? "Amina is having trouble. Please reload the page." : "Amina lagi ada gangguan. Coba muat ulang halamannya."}
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
            {english ? "Try again" : "Coba lagi"}
          </button>
        </div>
      </body>
    </html>
  );
}
