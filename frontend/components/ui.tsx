"use client";

import type { CSSProperties, ReactNode } from "react";

import { Icon } from "@/components/icon";

export function PageHeader({
  title,
  eyebrow,
  description,
  onAdd,
  addLabel = "Tambah",
}: {
  title: string;
  eyebrow?: string;
  description?: string;
  onAdd?: () => void;
  addLabel?: string;
}) {
  return (
    <div className="page-header">
      <div>
        {eyebrow ? <div className="page-eyebrow">{eyebrow}</div> : null}
        <h1>{title}</h1>
        {description ? <p>{description}</p> : null}
      </div>
      {onAdd && (
        <button type="button" className="btn btn-primary" onClick={onAdd}>
          <Icon name="plus" size={14} />
          {addLabel}
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
        onClick={onDelete}
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
  icon = "sparkles",
  title,
  message,
  actionLabel,
  onAction,
}: {
  icon?: string;
  title?: string;
  message: string;
  actionLabel: string;
  onAction: () => void;
}) {
  return (
    <div className="card empty-state">
      <div className="empty-state-icon"><Icon name={icon} size={22} /></div>
      {title ? <div className="card-title">{title}</div> : null}
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
