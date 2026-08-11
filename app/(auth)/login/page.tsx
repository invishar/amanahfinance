"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

import { AuthHeader } from "@/components/auth-header";
import { ApiError } from "@/lib/api/client";
import { useSession } from "@/lib/auth";

export default function LoginPage() {
  const router = useRouter();
  const { login, status } = useSession();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  useEffect(() => {
    if (status === "authenticated") router.replace("/chat");
  }, [status, router]);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setFieldErrors({});
    setFormError(null);
    setPending(true);
    try {
      await login(email.trim(), password);
      router.replace("/chat");
    } catch (error) {
      if (error instanceof ApiError) {
        const perField: Record<string, string> = {};
        for (const key of Object.keys(error.fieldErrors)) {
          const message = error.fieldMessage(key);
          if (message) perField[key] = message;
        }
        setFieldErrors(perField);
        // Kredensial salah dibalas 422 dengan pesan generik, tanpa field.
        if (Object.keys(perField).length === 0) setFormError(error.message);
      } else {
        setFormError("Terjadi kesalahan tak terduga.");
      }
      setPending(false);
    }
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
          disabled={pending}
        >
          {pending ? "Masuk…" : "Masuk"}
        </button>

        <p style={{ textAlign: "center", fontSize: 13, margin: 0 }}>
          Belum punya akun? <Link href="/register">Daftar</Link>
        </p>
      </form>
    </div>
  );
}
