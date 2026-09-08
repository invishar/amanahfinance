"use client";

import { useEffect, useRef } from "react";

import { AiActionCard } from "@/components/chat/ai-action-card";
import { Icon } from "@/components/icon";
import { Skeleton } from "@/components/ui";
import type { Account, AiAction, IncomeSource, SavingsGoal, Wallet } from "@/lib/api/hooks";
import { formatChatDayLabel, formatChatTimeLabel } from "@/lib/format";

export interface ChatItem {
  id: string;
  /** `system` = pesan error dari server (CLAUDE.md: ProcessAssistantMessage::failed()), bukan balasan Amina biasa. */
  role: "user" | "assistant" | "system";
  content: string;
  /** Draft AiAction sungguhan menunggu konfirmasi user. */
  aiAction?: AiAction;
  /** Pesan optimistic yang belum dikonfirmasi server. */
  pending?: boolean;
  /** Status pengiriman untuk bubble user terakhir. */
  deliveryStatus?: "sending" | "read";
  /** Epoch ms, dipakai utk kelompok bubble per-hari (label "Hari ini"/"Kemarin", ala WA). Falsy = tidak diberi label. */
  at?: number;
}

function isSameDay(a: number, b: number) {
  const da = new Date(a);
  const db = new Date(b);
  return (
    da.getFullYear() === db.getFullYear() &&
    da.getMonth() === db.getMonth() &&
    da.getDate() === db.getDate()
  );
}

export function MessageList({
  items,
  isLoading,
  isTyping,
  aiActionEntities,
  onConfirmAiAction,
  onRejectAiAction,
  confirmingAiActionId,
  rejectingAiActionId,
  aiActionErrors,
}: {
  items: ChatItem[];
  isLoading: boolean;
  isTyping: boolean;
  aiActionEntities: {
    accounts: Account[];
    wallets: Wallet[];
    incomeSources: IncomeSource[];
    savingsGoals: SavingsGoal[];
  };
  onConfirmAiAction: (id: string, edits?: Record<string, unknown>) => void;
  onRejectAiAction: (id: string) => void;
  confirmingAiActionId: string | null;
  rejectingAiActionId: string | null;
  aiActionErrors: Record<string, string>;
}) {
  const bottomRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ block: "end" });
  }, [items, isTyping]);

  // Kelompokkan bubble per-hari supaya label tanggal bisa "mengambang" (CSS
  // `position: sticky`) di atas kelompoknya sendiri saat digulir -- persis
  // seperti WA -- alih-alih ikut tergulir seperti item biasa.
  const groups: { key: string; label: string | null; items: ChatItem[] }[] = [];
  let lastDay: number | null = null;
  for (const m of items) {
    if (m.at && (lastDay === null || !isSameDay(lastDay, m.at))) {
      lastDay = m.at;
      groups.push({ key: `day-${m.id}`, label: formatChatDayLabel(m.at), items: [] });
    }
    if (groups.length === 0) groups.push({ key: "ungrouped", label: null, items: [] });
    groups[groups.length - 1].items.push(m);
  }

  return (
    <div
      className="chat-messages"
      style={{
        flex: 1,
        overflowY: "auto",
        padding: "var(--space-4)",
        display: "flex",
        flexDirection: "column",
        gap: "var(--space-4)",
      }}
    >
      {isLoading && items.length === 0 ? <ChatLoading /> : null}
      {groups.map((group) => (
        <div key={group.key} style={{ display: "flex", flexDirection: "column", gap: "var(--space-3)" }}>
          {group.label && <DateSeparator label={group.label} />}
          {group.items.map((m) => (
            <MessageRow
              key={m.id}
              m={m}
              aiActionEntities={aiActionEntities}
              onConfirmAiAction={onConfirmAiAction}
              onRejectAiAction={onRejectAiAction}
              confirmingAiActionId={confirmingAiActionId}
              rejectingAiActionId={rejectingAiActionId}
              aiActionErrors={aiActionErrors}
            />
          ))}
        </div>
      ))}

      {isTyping && <TypingIndicator />}
      <div ref={bottomRef} />
    </div>
  );
}

function MessageRow({
  m,
  aiActionEntities,
  onConfirmAiAction,
  onRejectAiAction,
  confirmingAiActionId,
  rejectingAiActionId,
  aiActionErrors,
}: {
  m: ChatItem;
  aiActionEntities: {
    accounts: Account[];
    wallets: Wallet[];
    incomeSources: IncomeSource[];
    savingsGoals: SavingsGoal[];
  };
  onConfirmAiAction: (id: string, edits?: Record<string, unknown>) => void;
  onRejectAiAction: (id: string) => void;
  confirmingAiActionId: string | null;
  rejectingAiActionId: string | null;
  aiActionErrors: Record<string, string>;
}) {
  return (
    <div
      style={{
        display: "flex",
        justifyContent: m.role === "user" ? "flex-end" : "flex-start",
      }}
    >
      <div
        style={{
          maxWidth: "82%",
          display: "flex",
          flexDirection: "column",
          gap: 8,
          opacity: m.pending ? 0.6 : 1,
        }}
      >
        {m.content && (
          <div
            style={{
              padding: "10px 14px",
              borderRadius: "var(--radius-lg)",
              fontSize: 14,
              lineHeight: 1.5,
              whiteSpace: "pre-wrap",
              background:
                m.role === "user" ? "var(--color-accent-100)" : "var(--color-surface)",
              color: m.role === "system" ? "var(--color-accent-800)" : undefined,
              border: m.role === "system"
                ? "1px solid var(--color-accent-800)"
                : "1px solid var(--color-divider)",
            }}
          >
            <div>{m.content}</div>
            {m.at ? (
              <div
                className={m.role === "system" ? undefined : "text-muted"}
                style={{ fontSize: 10, marginTop: 4, textAlign: "right" }}
              >
                <span>{formatChatTimeLabel(m.at)}</span>
                {m.role === "user" && m.deliveryStatus ? (
                  <span
                    style={{
                      marginLeft: 6,
                      display: "inline-flex",
                      alignItems: "center",
                      gap: 3,
                      color: m.deliveryStatus === "read" ? "var(--color-accent-2)" : undefined,
                    }}
                  >
                    <Icon name={m.deliveryStatus === "read" ? "check-check" : "check"} size={12} />
                    {m.deliveryStatus === "read" ? "Dibaca Amina" : "Mengirim…"}
                  </span>
                ) : null}
              </div>
            ) : null}
          </div>
        )}
        {m.aiAction && (
          <AiActionCard
            aiAction={m.aiAction}
            entities={aiActionEntities}
            onConfirm={(edits) => onConfirmAiAction(m.aiAction!.id!, edits)}
            onReject={() => onRejectAiAction(m.aiAction!.id!)}
            isConfirming={confirmingAiActionId === m.aiAction.id}
            isRejecting={rejectingAiActionId === m.aiAction.id}
            errorMessage={m.aiAction.id ? aiActionErrors[m.aiAction.id] : null}
          />
        )}
      </div>
    </div>
  );
}

function DateSeparator({ label }: { label: string }) {
  return (
    <div
      style={{
        position: "sticky",
        top: 0,
        zIndex: 1,
        display: "flex",
        justifyContent: "center",
        padding: "var(--space-2) 0",
      }}
    >
      <span
        style={{
          fontSize: 11,
          fontWeight: 600,
          color: "var(--color-text)",
          background: "var(--color-surface)",
          border: "1px solid var(--color-divider)",
          borderRadius: 999,
          padding: "4px 12px",
          boxShadow: "var(--shadow-sm)",
        }}
      >
        {label}
      </span>
    </div>
  );
}

function TypingIndicator() {
  return (
    <div style={{ display: "flex", justifyContent: "flex-start" }}>
      <div
        style={{
          padding: "12px 16px",
          borderRadius: "var(--radius-lg)",
          background: "var(--color-surface)",
          border: "1px solid var(--color-divider)",
          display: "flex",
          gap: 4,
        }}
        aria-label="Amina sedang membaca dan menyiapkan jawaban"
      >
        {[0, 0.15, 0.3].map((delay) => (
          <span
            key={delay}
            style={{
              width: 6,
              height: 6,
              borderRadius: "50%",
              background: "var(--color-accent)",
              display: "inline-block",
              animation: `amanaBlink 1.2s infinite ${delay}s`,
            }}
          />
        ))}
        <span className="text-muted" style={{ fontSize: 11, marginLeft: 5 }}>
          Amina sedang menyiapkan jawaban
        </span>
      </div>
    </div>
  );
}

function ChatLoading() {
  return (
    <div aria-label="Memuat percakapan" style={{ display: "flex", flexDirection: "column", gap: 10 }}>
      <Skeleton height={52} style={{ width: "62%", borderRadius: "var(--radius-lg)" }} />
      <Skeleton height={44} style={{ width: "48%", alignSelf: "flex-end", borderRadius: "var(--radius-lg)" }} />
      <Skeleton height={68} style={{ width: "70%", borderRadius: "var(--radius-lg)" }} />
    </div>
  );
}
