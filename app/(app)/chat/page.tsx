"use client";

import { useEffect, useRef, useState } from "react";

import { Icon } from "@/components/icon";
import { MessageList } from "@/components/chat/message-list";
import { onboardQuestions, type Scenario } from "@/lib/mock/assistant";
import { useAmana } from "@/lib/store";

const CHIPS: { label: string; scenario: Scenario; demoText: string }[] = [
  { label: "Catat pengeluaran", scenario: "transaction_text", demoText: "Tadi beli kopi sama snack 45rb pakai GoPay" },
  { label: "Buat wallet baru", scenario: "create_wallet", demoText: "Aku mau bikin wallet baru buat Kesehatan" },
  { label: "Tambah akun baru", scenario: "create_account", demoText: "Tolong tambahin akun Dana sebagai e-wallet" },
  { label: "Minta saran keuangan", scenario: "advice", demoText: "Gimana kondisi keuangan bulan ini?" },
];

export default function ChatPage() {
  const { messages, isTyping, onboardStep, family, sendChat, skipOnboardStep } =
    useAmana();
  const [input, setInput] = useState("");
  const [isRecording, setIsRecording] = useState(false);
  const recordTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(
    () => () => {
      if (recordTimer.current) clearTimeout(recordTimer.current);
    },
    [],
  );

  const send = () => {
    const text = input.trim();
    if (!text) return;
    setInput("");
    sendChat(text);
  };

  // Placeholder Web Speech API / unggah audio.
  const toggleRecording = () => {
    if (isRecording) return;
    setIsRecording(true);
    recordTimer.current = setTimeout(() => {
      setIsRecording(false);
      sendChat(
        "Tadi abis makan siang di warteg 25rb dari GoPay",
        "transaction_voice",
      );
    }, 1500);
  };

  // Placeholder POST /uploads + pesan dengan input_mode: 'image'.
  const sendReceipt = () => sendChat("[Foto struk diunggah]", "transaction_receipt");

  const inWawancara = onboardStep !== null;

  return (
    <div className="amana-chat-pane">
      <div
        style={{
          display: "flex",
          alignItems: "center",
          gap: 10,
          padding: "var(--space-3) var(--space-4)",
          borderBottom: "1px solid var(--color-divider)",
        }}
      >
        <div
          className="amana-brand-circle"
          style={{ width: 38, height: 38, fontSize: 15 }}
        >
          A
        </div>
        <div>
          <div
            style={{
              fontFamily: "var(--font-heading)",
              fontWeight: "var(--font-heading-weight)",
              fontSize: 16,
            }}
          >
            Amina
          </div>
          <div className="text-muted" style={{ fontSize: 12 }}>
            Asisten keuangan {family.name}
          </div>
        </div>
      </div>

      <MessageList messages={messages} isTyping={isTyping} />

      {inWawancara && (
        <div
          className="text-muted"
          style={{ padding: "0 var(--space-4)", fontSize: 11 }}
        >
          Pertanyaan {(onboardStep ?? 0) + 1} dari {onboardQuestions.length} —
          boleh dilewati kapan saja
        </div>
      )}

      <div
        style={{
          padding: "var(--space-2) var(--space-4)",
          display: "flex",
          gap: 8,
          overflowX: "auto",
        }}
      >
        {inWawancara ? (
          <button
            type="button"
            className="btn btn-secondary"
            style={{ fontSize: 12, whiteSpace: "nowrap", flex: "none" }}
            onClick={skipOnboardStep}
          >
            Lewati pertanyaan ini
          </button>
        ) : (
          CHIPS.map((c) => (
            <button
              key={c.label}
              type="button"
              className="btn btn-secondary"
              style={{ fontSize: 12, whiteSpace: "nowrap", flex: "none" }}
              onClick={() => sendChat(c.demoText, c.scenario)}
            >
              {c.label}
            </button>
          ))
        )}
      </div>

      <div
        style={{
          padding: "var(--space-3) var(--space-4)",
          borderTop: "1px solid var(--color-divider)",
          display: "flex",
          gap: 8,
          alignItems: "center",
        }}
      >
        <button
          type="button"
          className="btn btn-icon btn-secondary"
          onClick={sendReceipt}
          title="Kirim foto struk"
          aria-label="Kirim foto struk"
        >
          <Icon name="camera" size={18} />
        </button>
        <button
          type="button"
          className="btn btn-icon"
          style={{
            border: `1.5px solid ${isRecording ? "var(--color-accent)" : "var(--color-divider)"}`,
            color: isRecording ? "var(--color-accent)" : "var(--color-text)",
          }}
          onClick={toggleRecording}
          title="Rekam suara"
          aria-label="Rekam suara"
          aria-pressed={isRecording}
        >
          <Icon
            name="mic"
            size={18}
            style={
              isRecording ? { animation: "amanaPulse 1s infinite" } : undefined
            }
          />
        </button>
        <input
          className="input"
          style={{ flex: 1 }}
          placeholder="Tulis pesan ke Amina..."
          value={input}
          onChange={(e) => setInput(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === "Enter") {
              e.preventDefault();
              send();
            }
          }}
          aria-label="Pesan untuk Amina"
        />
        <button
          type="button"
          className="btn btn-icon btn-primary"
          onClick={send}
          title="Kirim"
          aria-label="Kirim"
        >
          <Icon name="send" size={18} />
        </button>
      </div>
    </div>
  );
}
