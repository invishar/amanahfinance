"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

import { ApiError } from "@/lib/api/client";
import { useAcceptInvite, useActiveFamily, useCreateFamily } from "@/lib/api/hooks";
import { useSession } from "@/lib/auth";

type Mode = "create" | "join";

export default function OnboardingPage() {
  const router = useRouter();
  const { status } = useSession();
  const { familyId, isLoading } = useActiveFamily();
  const createFamily = useCreateFamily();
  const acceptInvite = useAcceptInvite();

  const [mode, setMode] = useState<Mode>("create");
  const [familyName, setFamilyName] = useState("");
  const [joinCode, setJoinCode] = useState("");
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (status === "anonymous") router.replace("/login");
  }, [status, router]);

  // Sudah punya family → tidak ada yang perlu disiapkan.
  useEffect(() => {
    if (familyId) router.replace("/chat");
  }, [familyId, router]);

  const pending = createFamily.isPending || acceptInvite.isPending;

  const submit = async () => {
    setError(null);
    try {
      if (mode === "create") {
        await createFamily.mutateAsync(familyName.trim());
      } else {
        await acceptInvite.mutateAsync(joinCode.trim());
      }
      router.replace("/chat");
    } catch (e) {
      if (e instanceof ApiError) {
        const field = mode === "create" ? "name" : "token";
        setError(e.fieldMessage(field) ?? e.message);
      } else {
        setError("Terjadi kesalahan tak terduga.");
      }
    }
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

        {error && <p className="field-error">{error}</p>}

        <button
          type="button"
          className="btn btn-primary btn-block"
          style={{ height: 44, fontSize: 15 }}
          onClick={submit}
          disabled={pending || isLoading}
        >
          {pending ? "Menyiapkan…" : "Lanjut ke AmanaFinance"}
        </button>
      </div>
    </div>
  );
}
