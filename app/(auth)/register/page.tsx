"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";

import { AuthHeader } from "@/components/auth-header";

export default function RegisterPage() {
  const router = useRouter();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    const errors: Record<string, string> = {};
    if (!name.trim()) errors.name = "Nama wajib diisi.";
    if (!email.trim()) errors.email = "Email wajib diisi.";
    if (password.length < 8) errors.password = "Minimal 8 karakter.";
    setFieldErrors(errors);
    if (Object.keys(errors).length > 0) return;

    // TODO: POST /auth/register → lanjut ke pembuatan/join family.
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
          title="Buat akun baru"
          subtitle="Mulai catat keuangan keluarga bareng-bareng"
        />

        <div className="field">
          <label htmlFor="name">Nama lengkap</label>
          <input
            id="name"
            className="input"
            autoComplete="name"
            placeholder="Rizki Pratama"
            value={name}
            onChange={(e) => setName(e.target.value)}
          />
          {fieldErrors.name && <p className="field-error">{fieldErrors.name}</p>}
        </div>

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
            autoComplete="new-password"
            placeholder="••••••••"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
          />
          {fieldErrors.password && (
            <p className="field-error">{fieldErrors.password}</p>
          )}
        </div>

        <button
          type="submit"
          className="btn btn-primary btn-block"
          style={{ height: 44, fontSize: 15 }}
        >
          Daftar
        </button>

        <p style={{ textAlign: "center", fontSize: 13, margin: 0 }}>
          Sudah punya akun? <Link href="/login">Masuk</Link>
        </p>
      </form>
    </div>
  );
}
