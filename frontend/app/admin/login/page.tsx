"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

import { AuthHeader } from "@/components/auth-header";
import { Icon } from "@/components/icon";
import { ApiError } from "@/lib/api/client";
import { useMe, useSession } from "@/lib/auth";

export default function AdminLoginPage() {
  const router = useRouter();
  const { login, logout, status } = useSession();
  const me = useMe();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  // Sudah login sebagai admin (mis. buka /admin/login lagi) -> langsung masuk.
  useEffect(() => {
    if (status === "authenticated" && me.data?.is_admin) {
      router.replace("/admin");
    }
  }, [status, me.data, router]);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setFieldErrors({});
    setFormError(null);
    setPending(true);
    try {
      await login(email.trim(), password);
      const check = await me.refetch();
      if (!check.data?.is_admin) {
        await logout();
        setFormError("Akun ini tidak punya akses admin platform.");
        setPending(false);
        return;
      }
      router.replace("/admin");
    } catch (error) {
      if (error instanceof ApiError) {
        const perField: Record<string, string> = {};
        for (const key of Object.keys(error.fieldErrors)) {
          const message = error.fieldMessage(key);
          if (message) perField[key] = message;
        }
        setFieldErrors(perField);
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
        <AuthHeader title="Admin Platform" subtitle="Kelola user, pembayaran, dan setting LLM AmanaFinance" />

        <div
          style={{
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            gap: 6,
            fontSize: 12,
            color: "var(--color-accent-700)",
          }}
        >
          <Icon name="shield-check" size={14} />
          Hanya untuk akun dengan akses admin platform
        </div>

        <div className="field">
          <label htmlFor="email">Email</label>
          <input
            id="email"
            className="input"
            type="email"
            autoComplete="email"
            placeholder="admin@email.com"
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
          {fieldErrors.password && <p className="field-error">{fieldErrors.password}</p>}
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
      </form>
    </div>
  );
}
