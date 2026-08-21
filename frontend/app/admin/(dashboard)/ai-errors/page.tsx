"use client";

import { useState } from "react";

import { Icon } from "@/components/icon";
import { SkeletonList } from "@/components/ui";
import { useAdminAiErrors, type AiProviderError } from "@/lib/api/admin-hooks";

function StatusTag({ status }: { status?: number | null }) {
  if (status == null) {
    return <span className="tag tag-neutral">Tanpa respons (timeout/koneksi)</span>;
  }
  const cls = status === 429 ? "tag-accent" : status >= 500 ? "tag-neutral" : "tag-outline";
  return <span className={`tag ${cls}`}>HTTP {status}</span>;
}

export default function AdminAiErrorsPage() {
  const [status, setStatus] = useState("");
  const [model, setModel] = useState("");
  const [page, setPage] = useState(1);
  const [selected, setSelected] = useState<AiProviderError | null>(null);

  const { data, isPending } = useAdminAiErrors(status, model, page);
  const errors = data?.data ?? [];
  const meta = data?.meta;

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: "var(--space-4)" }}>
      <h1 style={{ fontSize: 22, margin: 0 }}>Log AI</h1>
      <p className="text-muted" style={{ margin: 0, fontSize: 13 }}>
        Setiap kali panggilan LLM dibalas selain 2xx (rate limit, auth, request terlalu
        besar, timeout, dst.) — satu baris per percobaan. Duplikat channel log{" "}
        <code>ai</code> di <code>storage/logs</code>, di sini bisa difilter & dipaginasi.
      </p>

      <div style={{ display: "flex", gap: "var(--space-3)", flexWrap: "wrap" }}>
        <div className="field" style={{ maxWidth: 160 }}>
          <label htmlFor="status-filter">Status</label>
          <input
            id="status-filter"
            className="input"
            type="number"
            placeholder="mis. 429"
            value={status}
            onChange={(e) => {
              setStatus(e.target.value);
              setPage(1);
            }}
          />
        </div>

        <div className="field" style={{ maxWidth: 260, flex: 1, minWidth: 200 }}>
          <label htmlFor="model-filter">Model</label>
          <input
            id="model-filter"
            className="input"
            placeholder="mis. openai/gpt-oss-120b"
            value={model}
            onChange={(e) => {
              setModel(e.target.value);
              setPage(1);
            }}
          />
        </div>
      </div>

      <div className="card elev-sm">
        {isPending ? (
          <SkeletonList count={5} height={56} />
        ) : errors.length === 0 ? (
          <p className="text-muted" style={{ margin: 0, fontSize: 13 }}>
            Belum ada kegagalan provider yang tercatat.
          </p>
        ) : (
          errors.map((err) => (
            <button
              key={err.id}
              type="button"
              onClick={() => setSelected(err)}
              style={{
                display: "flex",
                alignItems: "center",
                justifyContent: "space-between",
                gap: 10,
                width: "100%",
                padding: "10px 4px",
                borderBottom: "1px solid var(--color-divider)",
                background: "none",
                border: "none",
                borderRadius: 0,
                textAlign: "left",
                cursor: "pointer",
                font: "inherit",
                color: "inherit",
              }}
            >
              <div style={{ display: "flex", alignItems: "center", gap: 10, minWidth: 0 }}>
                <Icon name="alert-triangle" size={18} color="var(--color-accent)" />
                <div style={{ minWidth: 0 }}>
                  <div style={{ fontSize: 14, fontWeight: 600, overflow: "hidden", textOverflow: "ellipsis" }}>
                    {err.model}
                  </div>
                  <div className="text-muted" style={{ fontSize: 12 }}>
                    {err.family_name ?? "Family tidak diketahui"} ·{" "}
                    {err.created_at ? new Date(err.created_at).toLocaleString("id-ID") : "—"}
                  </div>
                </div>
              </div>
              <StatusTag status={err.status} />
            </button>
          ))
        )}
      </div>

      {meta && (meta.last_page ?? 1) > 1 && (
        <div style={{ display: "flex", justifyContent: "center", alignItems: "center", gap: 10 }}>
          <button
            type="button"
            className="btn btn-icon-sm btn-secondary"
            disabled={page <= 1}
            onClick={() => setPage((p) => Math.max(1, p - 1))}
            aria-label="Halaman sebelumnya"
          >
            <Icon name="chevron-left" size={14} />
          </button>
          <span className="text-muted" style={{ fontSize: 12 }}>
            Halaman {meta.current_page} / {meta.last_page}
          </span>
          <button
            type="button"
            className="btn btn-icon-sm btn-secondary"
            disabled={page >= (meta.last_page ?? 1)}
            onClick={() => setPage((p) => p + 1)}
            aria-label="Halaman berikutnya"
          >
            <Icon name="chevron-right" size={14} />
          </button>
        </div>
      )}

      {selected && <AiErrorDetailDialog error={selected} onClose={() => setSelected(null)} />}
    </div>
  );
}

function AiErrorDetailDialog({
  error,
  onClose,
}: {
  error: AiProviderError;
  onClose: () => void;
}) {
  return (
    <div className="dialog-backdrop" onClick={onClose} role="presentation">
      <div
        className="dialog"
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
        aria-label="Detail kegagalan AI"
      >
        <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
          <div className="dialog-title">{error.model}</div>
          <StatusTag status={error.status} />
        </div>

        <div style={{ display: "flex", flexDirection: "column", gap: 4, fontSize: 13 }}>
          <div>
            <span className="text-muted">Waktu:</span>{" "}
            {error.created_at ? new Date(error.created_at).toLocaleString("id-ID") : "—"}
          </div>
          <div>
            <span className="text-muted">Family:</span> {error.family_name ?? "—"}
          </div>
          <div>
            <span className="text-muted">Exception:</span> {error.exception}
          </div>
          <div>
            <span className="text-muted">Thread ID:</span> {error.thread_id ?? "—"}
          </div>
          <div>
            <span className="text-muted">Message ID:</span> {error.message_id ?? "—"}
          </div>
        </div>

        <div>
          <div className="card-kicker" style={{ marginBottom: 6 }}>
            Respons provider
          </div>
          <pre
            style={{
              margin: 0,
              padding: "var(--space-3)",
              background: "var(--color-bg)",
              border: "1px solid var(--color-divider)",
              borderRadius: "var(--radius-md)",
              fontSize: 12,
              whiteSpace: "pre-wrap",
              wordBreak: "break-word",
              maxHeight: 240,
              overflowY: "auto",
            }}
          >
            {error.body ?? "—"}
          </pre>
        </div>

        <div className="dialog-actions">
          <button type="button" className="btn btn-secondary" onClick={onClose}>
            Tutup
          </button>
        </div>
      </div>
    </div>
  );
}
