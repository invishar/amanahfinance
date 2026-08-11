"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";

import { AuthHeader } from "@/components/auth-header";

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  // Bentuk error mengikuti response API: 422 per-field, 401 satu baris.
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    const errors: Record<string, string> = {};
    if (!email.trim()) errors.email = "Email wajib diisi.";
    if (!password) errors.password = "Kata sandi wajib diisi.";
    setFieldErrors(errors);
    setFormError(null);
    if (Object.keys(errors).length > 0) return;

    // TODO: POST /auth/login → simpan token, lalu GET /me untuk cek family.
    router.push("/onboarding");
  };

  return (
    <div className="amana-auth-shell">
      <form
        onSubmit={submit}
        noValidate
        style={{
          width: "100%",
          maxWidth: 380,
          display: "flex",
          flexDirection: "column",
          gap: "var(--space-4)",
        }}
      >
        <AuthHeader
          title="AmanaFinance"
          subtitle="Asisten keuangan keluarga yang ngerti obrolan sehari-hari"
        />

        <div className="field">
          <label htmlFor="email">Email</label>
          <input
            id="email"
            className="input"
            type="email"
            autoComplete="email"
            placeholder="nama@email.com"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
          />
          {fieldErrors.email && <p className="field-error">{fieldErrors.email}</p>}
        </div>

        <div className="field">
          <label htmlFor="password">Kata sandi</label>
          <input
            id="password"
            className="input"
            type="password"
            autoComplete="current-password"
            placeholder="••••••••"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
          />
          {fieldErrors.password && (
            <p className="field-error">{fieldErrors.password}</p>
          )}
        </div>

        {formError && <p className="field-error">{formError}</p>}

        <button
          type="submit"
          className="btn btn-primary btn-block"
          style={{ height: 44, fontSize: 15 }}
        >
          Masuk
        </button>

        <p style={{ textAlign: "center", fontSize: 13, margin: 0 }}>
          Belum punya akun? <Link href="/register">Daftar</Link>
        </p>
      </form>
    </div>
  );
}
