"use client";

import { useEffect, useRef } from "react";

import { ActionCard } from "@/components/chat/action-card";
import { AiActionCard } from "@/components/chat/ai-action-card";
import type { Account, AiAction, IncomeSource, SavingsGoal, Wallet } from "@/lib/api/hooks";
import type { DemoActionCard } from "@/lib/mock/assistant";

export interface ChatItem {
  id: string;
  /** `system` = pesan error dari server (CLAUDE.md: ProcessAssistantMessage::failed()), bukan balasan Amina biasa. */
  role: "user" | "assistant" | "system";
  content: string;
  card?: DemoActionCard;
  /** Draft AiAction sungguhan (bukan skenario demo) -- lihat `card` untuk itu. */
  aiAction?: AiAction;
  /** Pesan optimistic yang belum dikonfirmasi server. */
  pending?: boolean;
}

export function MessageList({
  items,
  isTyping,
  demo,
  onResolveCard,
  aiActionEntities,
  onConfirmAiAction,
  onRejectAiAction,
  confirmingAiActionId,
  rejectingAiActionId,
  aiActionErrors,
}: {
  items: ChatItem[];
  isTyping: boolean;
  demo: boolean;
  onResolveCard: (id: string, status: "confirmed" | "cancelled") => void;
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

  return (
    <div
      style={{
        flex: 1,
        overflowY: "auto",
        padding: "var(--space-4)",
        display: "flex",
        flexDirection: "column",
        gap: "var(--space-3)",
      }}
    >
      {items.map((m) => (
        <div
          key={m.id}
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
                  background:
                    m.role === "user"
                      ? "var(--color-accent-100)"
                      : "var(--color-surface)",
                  color: m.role === "system" ? "var(--color-accent-800)" : undefined,
                  border: m.role === "system"
                    ? "1px solid var(--color-accent-800)"
                    : "1px solid var(--color-divider)",
                }}
              >
                {m.content}
              </div>
            )}
            {m.card && (
              <ActionCard
                card={m.card}
                demo={demo}
                onConfirm={() => onResolveCard(m.id, "confirmed")}
                onCancel={() => onResolveCard(m.id, "cancelled")}
              />
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
      ))}

      {isTyping && <TypingIndicator />}
      <div ref={bottomRef} />
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
        aria-label="Amina sedang mengetik"
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
      </div>
    </div>
  );
}
