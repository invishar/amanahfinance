"use client";

import { useState } from "react";

import { Icon } from "@/components/icon";
import { Skeleton } from "@/components/ui";
import { useLlmSetting, useUpdateLlmSetting, type LlmSetting } from "@/lib/api/admin-hooks";
import { ApiError } from "@/lib/api/client";

export default function AdminLlmSettingsPage() {
  const { data, isPending } = useLlmSetting();

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: "var(--space-4)" }}>
      <h1 style={{ fontSize: 22, margin: 0 }}>LLM Setting</h1>

      {isPending || !data ? <Skeleton height={280} /> : <LlmSettingForm initial={data} />}
    </div>
  );
}

// Form baru dimuat sekali data server siap (dirender lewat kondisi di atas),
// jadi state lokal bisa langsung diinisialisasi dari props tanpa efek sinkron.
function LlmSettingForm({ initial }: { initial: LlmSetting }) {
  const update = useUpdateLlmSetting();

  const [model, setModel] = useState(initial.model ?? "");
  const [baseUrl, setBaseUrl] = useState(initial.base_url ?? "");
  const [key, setKey] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const [hasKey, setHasKey] = useState(initial.has_key ?? false);
  const [keyPreview, setKeyPreview] = useState(initial.key_preview ?? null);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setFieldErrors({});
    setFormError(null);
    setSaved(false);
    try {
      const result = await update.mutateAsync({
        model: model.trim(),
        base_url: baseUrl.trim() || null,
        ...(key.trim() ? { key: key.trim() } : {}),
      });
      setKey("");
      setHasKey(result.has_key ?? false);
      setKeyPreview(result.key_preview ?? null);
      setSaved(true);
      setTimeout(() => setSaved(false), 2500);
    } catch (error) {
      if (error instanceof ApiError) {
        const perField: Record<string, string> = {};
        for (const k of Object.keys(error.fieldErrors)) {
          const message = error.fieldMessage(k);
          if (message) perField[k] = message;
        }
        setFieldErrors(perField);
        if (Object.keys(perField).length === 0) setFormError(error.message);
      } else {
        setFormError("Terjadi kesalahan tak terduga.");
      }
    }
  };

  return (
    <form onSubmit={submit} className="card elev-sm" style={{ maxWidth: 480 }}>
      <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
        <Icon name="sparkles" size={18} color="var(--color-accent)" />
        <div className="card-title">Model &amp; kredensial platform</div>
      </div>
      <p className="card-body" style={{ marginBottom: "var(--space-2)" }}>
        Dipakai oleh asisten Amina di seluruh family. Key tersimpan terenkripsi
        dan tidak pernah ditampilkan utuh.
      </p>

      <div className="field">
        <label htmlFor="model">Model</label>
        <input
          id="model"
          className="input"
          placeholder="claude-sonnet-5"
          value={model}
          onChange={(e) => setModel(e.target.value)}
        />
        {fieldErrors.model && <p className="field-error">{fieldErrors.model}</p>}
      </div>

      <div className="field">
        <label htmlFor="base-url">Base URL (opsional)</label>
        <input
          id="base-url"
          className="input"
          placeholder="https://api.anthropic.com"
          value={baseUrl}
          onChange={(e) => setBaseUrl(e.target.value)}
        />
        {fieldErrors.base_url && <p className="field-error">{fieldErrors.base_url}</p>}
      </div>

      <div className="field">
        <label htmlFor="key">API key {hasKey ? `(saat ini: ${keyPreview})` : "(belum diset)"}</label>
        <input
          id="key"
          className="input"
          type="password"
          placeholder="Kosongkan untuk mempertahankan key lama"
          value={key}
          onChange={(e) => setKey(e.target.value)}
          autoComplete="off"
        />
        {fieldErrors.key && <p className="field-error">{fieldErrors.key}</p>}
      </div>

      {formError && <p className="field-error">{formError}</p>}
      {saved && (
        <p style={{ margin: 0, fontSize: 12, color: "var(--color-accent-2)" }}>Tersimpan.</p>
      )}

      <button
        type="submit"
        className="btn btn-primary"
        style={{ marginTop: "var(--space-2)" }}
        disabled={update.isPending}
      >
        {update.isPending ? "Menyimpan…" : "Simpan"}
      </button>
    </form>
  );
}
