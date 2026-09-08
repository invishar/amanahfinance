"use client";

import { useState } from "react";

import { Icon } from "@/components/icon";
import { SkeletonList } from "@/components/ui";
import { useAdminAiLogs, type AiLog } from "@/lib/api/admin-hooks";

export default function AdminAiLogsPage() {
  const [model, setModel] = useState("");
  const [page, setPage] = useState(1);
  const [selected, setSelected] = useState<AiLog | null>(null);

  const { data, isPending } = useAdminAiLogs(model, page);
  const logs = data?.data ?? [];
  const meta = data?.meta;

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: "var(--space-4)" }}>
      <h1 style={{ fontSize: 22, margin: 0 }}>Log Prompt</h1>
      <p className="text-muted" style={{ margin: 0, fontSize: 13 }}>
        Cuma terisi kalau server API jalan dengan <code>APP_ENV=local</code> — satu baris per
        panggilan Amina yang berhasil, isinya prompt user, system prompt yang dikirim ke LLM,
        dan token usage. Debugging lokal, bukan monitoring produksi.
      </p>

      <div style={{ display: "flex", gap: "var(--space-3)", flexWrap: "wrap" }}>
        <div className="field" style={{ maxWidth: 260, flex: 1, minWidth: 200 }}>
          <label htmlFor="model-filter">Model</label>
          <input
            id="model-filter"
            className="input"
            placeholder="mis. claude-sonnet-4-5"
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
        ) : logs.length === 0 ? (
          <p className="text-muted" style={{ margin: 0, fontSize: 13 }}>
            Belum ada prompt yang tercatat.
          </p>
        ) : (
          logs.map((log) => (
            <button
              key={log.id}
              type="button"
              onClick={() => setSelected(log)}
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
                <Icon name="message-circle" size={18} color="var(--color-accent)" />
                <div style={{ minWidth: 0 }}>
                  <div
                    style={{
                      fontSize: 14,
                      fontWeight: 600,
                      overflow: "hidden",
                      textOverflow: "ellipsis",
                      whiteSpace: "nowrap",
                      maxWidth: 360,
                    }}
                  >
                    {log.user_prompt || "(tanpa teks)"}
                  </div>
                  <div className="text-muted" style={{ fontSize: 12 }}>
                    {log.family_name ?? "Family tidak diketahui"} · {log.model} ·{" "}
                    {log.created_at ? new Date(log.created_at).toLocaleString("id-ID") : "—"}
                  </div>
                </div>
              </div>
              <span className="tag tag-outline">
                {(log.input_tokens ?? "—") + " in / " + (log.output_tokens ?? "—") + " out"}
              </span>
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

      {selected && <AiLogDetailDialog log={selected} onClose={() => setSelected(null)} />}
    </div>
  );
}

function AiLogDetailDialog({ log, onClose }: { log: AiLog; onClose: () => void }) {
  return (
    <div className="dialog-backdrop" onClick={onClose} role="presentation">
      <div
        className="dialog"
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
        aria-label="Detail prompt"
      >
        <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
          <div className="dialog-title">{log.model}</div>
          <span className="tag tag-outline">
            {(log.input_tokens ?? "—") + " in / " + (log.output_tokens ?? "—") + " out"}
          </span>
        </div>

        <div style={{ display: "flex", flexDirection: "column", gap: 4, fontSize: 13 }}>
          <div>
            <span className="text-muted">Waktu:</span>{" "}
            {log.created_at ? new Date(log.created_at).toLocaleString("id-ID") : "—"}
          </div>
          <div>
            <span className="text-muted">Family:</span> {log.family_name ?? "—"}
          </div>
          <div>
            <span className="text-muted">Thread ID:</span> {log.thread_id ?? "—"}
          </div>
          <div>
            <span className="text-muted">Message ID:</span> {log.message_id ?? "—"}
          </div>
        </div>

        <div>
          <div className="card-kicker" style={{ marginBottom: 6 }}>
            User prompt
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
            }}
          >
            {log.user_prompt ?? "—"}
          </pre>
        </div>

        <div>
          <div className="card-kicker" style={{ marginBottom: 6 }}>
            System prompt
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
              maxHeight: 280,
              overflowY: "auto",
            }}
          >
            {log.system_prompt ?? "—"}
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
