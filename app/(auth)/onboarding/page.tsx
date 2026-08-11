"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";

import { useAmana } from "@/lib/store";

type Mode = "create" | "join";

export default function OnboardingPage() {
  const router = useRouter();
  const { startOnboarding } = useAmana();
  const [mode, setMode] = useState<Mode>("create");
  const [familyName, setFamilyName] = useState("Keluarga Pratama");
  const [joinCode, setJoinCode] = useState("");

  const submit = () => {
    // TODO: POST /families atau POST /families/join. Backend yang membuka
    // thread `kind: 'onboarding'` beserta pertanyaan wawancaranya.
    startOnboarding(mode === "create" ? familyName : null);
    router.push("/chat");
  };

  return (
    <div className="amana-auth-shell">
      <div
        style={{
          width: "100%",
          maxWidth: 420,
          display: "flex",
          flexDirection: "column",
          gap: "var(--space-4)",
        }}
      >
        <h1 style={{ fontSize: 24, margin: 0 }}>Siapkan keluargamu</h1>
        <p className="text-muted" style={{ margin: 0, fontSize: 14 }}>
          Satu langkah lagi — buat family baru atau gabung ke yang sudah ada.
        </p>

        <div className="seg" style={{ width: "100%" }}>
          <label className="seg-opt" style={{ flex: 1, justifyContent: "center" }}>
            <input
              type="radio"
              name="obmode"
              checked={mode === "create"}
              onChange={() => setMode("create")}
            />
            Buat baru
          </label>
          <label className="seg-opt" style={{ flex: 1, justifyContent: "center" }}>
            <input
              type="radio"
              name="obmode"
              checked={mode === "join"}
              onChange={() => setMode("join")}
            />
            Gabung keluarga
          </label>
        </div>

        {mode === "create" ? (
          <div className="field">
            <label htmlFor="family-name">Nama keluarga</label>
            <input
              id="family-name"
              className="input"
              placeholder="Keluarga Pratama"
              value={familyName}
              onChange={(e) => setFamilyName(e.target.value)}
            />
          </div>
        ) : (
          <div className="field">
            <label htmlFor="join-code">Kode undangan</label>
            <input
              id="join-code"
              className="input"
              placeholder="AMANA-XXXXX"
              value={joinCode}
              onChange={(e) => setJoinCode(e.target.value)}
            />
          </div>
        )}

        <button
          type="button"
          className="btn btn-primary btn-block"
          style={{ height: 44, fontSize: 15 }}
          onClick={submit}
        >
          Lanjut ke AmanaFinance
        </button>
      </div>
    </div>
  );
}
