"use client";

import { useEffect, useRef } from "react";

import { ActionCard } from "@/components/chat/action-card";
import type { ChatMessage } from "@/lib/types";

export function MessageList({
  messages,
  isTyping,
}: {
  messages: ChatMessage[];
  isTyping: boolean;
}) {
  const bottomRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ block: "end" });
  }, [messages, isTyping]);

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
      {messages.map((m) => (
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
                  border: "1px solid var(--color-divider)",
                }}
              >
                {m.content}
              </div>
            )}
            {m.actionCard && <ActionCard messageId={m.id} card={m.actionCard} />}
          </div>
        </div>
      ))}

      {isTyping && <TypingIndicator />}
      <div ref={bottomRef} />
    </div>
  );
}

/** Dipicu event SSE `thinking` saat API tersambung — bukan timer palsu. */
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
