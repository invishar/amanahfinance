"use client";

import type { CSSProperties, ReactNode } from "react";

import { Icon } from "@/components/icon";

export function PageHeader({
  title,
  onAdd,
}: {
  title: string;
  onAdd?: () => void;
}) {
  return (
    <div
      style={{
        display: "flex",
        justifyContent: "space-between",
        alignItems: "center",
      }}
    >
      <h1 style={{ fontSize: 22, margin: 0 }}>{title}</h1>
      {onAdd && (
        <button type="button" className="btn btn-primary" onClick={onAdd}>
          <Icon name="plus" size={14} />
          Tambah
        </button>
      )}
    </div>
  );
}

export function RowActions({
  label,
  onEdit,
  onDelete,
}: {
  label: string;
  onEdit: () => void;
  onDelete: () => void;
}) {
  return (
    <div style={{ display: "flex", gap: 6 }}>
      <button
        type="button"
        className="btn btn-icon-sm btn-secondary"
        onClick={onEdit}
        aria-label={`Ubah ${label}`}
      >
        <Icon name="pencil" size={14} />
      </button>
      <button
        type="button"
        className="btn btn-icon-sm btn-secondary"
        onClick={() => {
          if (window.confirm(`Hapus ${label}?`)) onDelete();
        }}
        aria-label={`Hapus ${label}`}
      >
        <Icon name="trash-2" size={14} />
      </button>
    </div>
  );
}

export function ProgressBar({
  pct,
  color = "var(--color-accent-500)",
  height = 8,
}: {
  pct: number;
  color?: string;
  height?: number;
}) {
  return (
    <div
      style={{
        height,
        borderRadius: 4,
        background: "var(--color-neutral-200)",
        overflow: "hidden",
      }}
    >
      <div
        style={{
          height: "100%",
          width: `${Math.max(0, Math.min(100, pct))}%`,
          background: color,
        }}
      />
    </div>
  );
}

export function EmptyState({
  message,
  actionLabel,
  onAction,
}: {
  message: string;
  actionLabel: string;
  onAction: () => void;
}) {
  return (
    <div
      className="card elev-sm"
      style={{ alignItems: "center", textAlign: "center", gap: "var(--space-3)" }}
    >
      <p className="text-muted" style={{ margin: 0, fontSize: 13 }}>
        {message}
      </p>
      <button type="button" className="btn btn-secondary" onClick={onAction}>
        {actionLabel}
      </button>
    </div>
  );
}

/** Skeleton meniru tinggi kartu asli — bukan spinner tengah layar. */
export function Skeleton({
  height,
  style,
}: {
  height: number;
  style?: CSSProperties;
}) {
  return <div className="amana-skeleton" style={{ height, ...style }} />;
}

export function SkeletonList({
  count,
  height,
}: {
  count: number;
  height: number;
}) {
  return (
    <>
      {Array.from({ length: count }, (_, i) => (
        <Skeleton key={i} height={height} />
      ))}
    </>
  );
}

export function Stack({
  children,
  gap = "var(--space-4)",
  style,
}: {
  children: ReactNode;
  gap?: string;
  style?: CSSProperties;
}) {
  return (
    <div style={{ display: "flex", flexDirection: "column", gap, ...style }}>
      {children}
    </div>
  );
}
