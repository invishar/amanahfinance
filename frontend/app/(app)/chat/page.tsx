"use client";

import Link from "next/link";
import { useEffect, useMemo, useRef, useState } from "react";

import { Icon } from "@/components/icon";
import { MessageList, type ChatItem } from "@/components/chat/message-list";
import { ApiError } from "@/lib/api/client";
import {
  useAccounts,
  useActiveFamily,
  useChatStream,
  useChatThreads,
  useConfirmAiAction,
  useCreateThread,
  useIncomeSources,
  useMessages,
  usePendingAiActions,
  useRejectAiAction,
  useSavingsGoals,
  useSendMessage,
  useWallets,
} from "@/lib/api/hooks";
const QUICK_PROMPTS: { label: string; text: string }[] = [
  { label: "Catat pengeluaran", text: "Tadi beli kopi sama snack 45rb pakai GoPay" },
  { label: "Buat wallet baru", text: "Aku mau bikin wallet baru buat Kesehatan" },
  { label: "Tambah akun baru", text: "Tolong tambahin akun Dana sebagai e-wallet" },
  { label: "Minta saran keuangan", text: "Gimana kondisi keuangan bulan ini?" },
];

// Selama wawancara awal, pintasan ini menggantikan QUICK_PROMPTS. Isinya
// tetap dikirim sebagai pesan biasa -- Amina yang memaknainya.
const ONBOARDING_PROMPTS: { label: string; text: string }[] = [
  { label: "Lewati ini", text: "Lewati pertanyaan ini dulu ya" },
  { label: "Belum tahu nominalnya", text: "Aku belum tahu nominal pastinya" },
  { label: "Sudah cukup", text: "Sudah cukup, lanjut aja" },
];

// `at` cuma dipakai untuk mengurutkan; semua sumbernya (pesan & ai_actions)
// memakai timestamp server, jadi tidak ada lagi masalah beda jam client-server
// seperti waktu wawancara masih punya bubble lokal sendiri.
interface SortableItem extends ChatItem {
  at: number;
}

export default function ChatPage() {
  const { family, familyId } = useActiveFamily();
  const threads = useChatThreads();
  const createThread = useCreateThread();
  const wallets = useWallets();
  const accounts = useAccounts();
  const incomeSources = useIncomeSources();
  const savingsGoals = useSavingsGoals();

  const threadId = threads.data?.[0]?.id ?? null;
  const messages = useMessages(threadId ?? null);
  const sendMessage = useSendMessage(threadId ?? null);
  const lastServerMessage = messages.data?.at(-1);
  const awaitingReply = messages.isSuccess && lastServerMessage?.role === "user";
  // Jangan tahan koneksi SSE saat tidak ada balasan yang ditunggu. Selain
  // menghemat koneksi produksi, ini mencegah PHP dev server yang single-thread
  // memblokir seluruh request aplikasi selama stream 20 detik.
  const stream = useChatStream(threadId, familyId, awaitingReply);

  /* --- Kartu aksi (AiAction) sungguhan ------------------------------------ */
  const pendingAiActions = usePendingAiActions();
  const confirmAiAction = useConfirmAiAction();
  const rejectAiAction = useRejectAiAction();
  const [aiActionErrors, setAiActionErrors] = useState<Record<string, string>>({});

  const confirmAiActionCard = (id: string, edits?: Record<string, unknown>) => {
    setAiActionErrors((prev) => {
      const next = { ...prev };
      delete next[id];
      return next;
    });
    confirmAiAction.mutate(
      { id, edits },
      {
        onError: (error) => {
          let message = "Gagal menyimpan, coba lagi.";
          if (error instanceof ApiError) {
            const fieldMessages = Object.keys(error.fieldErrors)
              .map((key) => error.fieldMessage(key))
              .filter((m): m is string => Boolean(m));
            message = fieldMessages.length > 0 ? fieldMessages.join(" ") : error.message;
          }
          setAiActionErrors((prev) => ({ ...prev, [id]: message }));
        },
      },
    );
  };

  const rejectAiActionCard = (id: string) => rejectAiAction.mutate(id);

  const [input, setInput] = useState("");
  const [isRecording, setIsRecording] = useState(false);
  const timers = useRef<ReturnType<typeof setTimeout>[]>([]);

  const threadRequested = useRef(false);

  // Belum punya thread → buat satu (sekali saja). Family baru sudah otomatis
  // dapat thread kind=onboarding dari FamilyActions::create di server; ini
  // cuma fallback kalau entah kenapa family belum punya thread sama sekali.
  useEffect(() => {
    if (
      threads.isSuccess &&
      (threads.data?.length ?? 0) === 0 &&
      !threadRequested.current
    ) {
      threadRequested.current = true;
      createThread.mutate("general");
    }
  }, [threads.isSuccess, threads.data, createThread]);

  const thread = threads.data?.[0];
  // Server menandai wawancara awal masih berjalan lewat
  // ChatThreadResource.onboarding.done (dinyalakan tool finish_onboarding).
  // Klien cuma memakainya untuk petunjuk kecil di UI -- naskah pertanyaannya
  // tetap tidak pernah ada di sini (CLAUDE.md "Alur AI").
  const onboarding = thread?.kind === "onboarding" ? thread.onboarding : null;
  const inWawancara = Boolean(onboarding) && !onboarding!.done;
  useEffect(
    () => () => {
      timers.current.forEach(clearTimeout);
    },
    [],
  );

  const later = (fn: () => void, ms: number) => {
    timers.current.push(setTimeout(fn, ms));
  };

  const send = (
    text: string,
    opts?: { inputMode?: "text" | "voice" },
  ) => {
    const content = text.trim();
    if (!content || !threadId) return;
    setInput("");

    // Wawancara awal TIDAK lagi punya jalur sendiri. Sejak Amina yang
    // mewawancarai (server menempelkan `onboarding_briefing` ke system prompt
    // selama thread kind=onboarding, lihat AssistantService::respond), jawaban
    // user adalah pesan chat biasa: dia yang menentukan pertanyaan berikutnya
    // dan menyiapkan draft entitas lewat tool create_*. Draft itu muncul
    // sebagai kartu aksi yang bisa diedit/dikonfirmasi seperti di chat biasa.
    sendMessage.mutate({
      content,
      input_mode: opts?.inputMode ?? "text",
    });
  };

  const toggleRecording = () => {
    if (isRecording) return;
    setIsRecording(true);
    later(() => {
      setIsRecording(false);
      send("Tadi abis makan siang di warteg 25rb dari GoPay", {
        inputMode: "voice",
      });
    }, 1500);
  };

  // Pesan dari server digabung dengan kartu aksi yang masih pending, lalu
  // diurutkan berdasarkan waktu server.
  const items: SortableItem[] = useMemo(() => {
    const lastUserId = [...(messages.data ?? [])]
      .reverse()
      .find((message) => message.role === "user")?.id;
    const fromServer = (messages.data ?? []).map((m) => ({
      id: m.id ?? "",
      role: (m.role === "user" || m.role === "system" ? m.role : "assistant") as
        | "user"
        | "assistant"
        | "system",
      content: m.content ?? "",
      pending: (m.id ?? "").startsWith("optimistic-"),
      deliveryStatus:
        m.role === "user" && m.id === lastUserId
          ? ((m.id ?? "").startsWith("optimistic-") ? "sending" as const : "read" as const)
          : undefined,
      at: m.created_at ? Date.parse(m.created_at) : 0,
    }));

    // `/ai-actions` tidak thread-scoped (lihat useChatStream) -- saring ke
    // milik thread ini lewat message_id. Event SSE tidak membawa message_id
    // sama sekali (server sudah menyaringnya duluan), jadi selalu lolos.
    const messageIds = new Set((messages.data ?? []).map((m) => m.id));
    const fromAiActions = (pendingAiActions.data ?? [])
      .filter((a) => !a.message_id || messageIds.has(a.message_id))
      .map((a) => ({
        id: `ai-action-${a.id}`,
        role: "assistant" as const,
        content: "",
        aiAction: a,
        at: a.created_at ? Date.parse(a.created_at) : 0,
      }));

    return [...fromServer, ...fromAiActions].sort((a, b) => a.at - b.at);
  }, [messages.data, pendingAiActions.data]);

  return (
    <div className="amana-chat-pane">
      <div
        className="chat-header"
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
        <div style={{ flex: 1, minWidth: 0 }}>
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
            Asisten keuangan {family?.name ?? ""}
          </div>
        </div>
      </div>

      <div className="ai-fallback">
        <div>
          <strong>Kontrol manual selalu tersedia</strong>
          <div className="text-muted">Amina membantu, tetapi pencatatan tidak bergantung pada AI.</div>
        </div>
        <div className="ai-fallback-actions">
          <Link href="/transactions" className="btn btn-secondary">Catat transaksi</Link>
          <Link href="/wallets" className="btn btn-secondary">Atur anggaran</Link>
        </div>
      </div>

      <MessageList
        items={items}
        isLoading={threads.isPending || messages.isPending}
        isTyping={awaitingReply || stream.isThinking}
        aiActionEntities={{
          accounts: accounts.data ?? [],
          wallets: wallets.data ?? [],
          incomeSources: incomeSources.data ?? [],
          savingsGoals: savingsGoals.data ?? [],
        }}
        onConfirmAiAction={confirmAiActionCard}
        onRejectAiAction={rejectAiActionCard}
        confirmingAiActionId={confirmAiAction.isPending ? (confirmAiAction.variables?.id ?? null) : null}
        rejectingAiActionId={rejectAiAction.isPending ? (rejectAiAction.variables ?? null) : null}
        aiActionErrors={aiActionErrors}
      />

      {inWawancara && (
        <div className="text-muted" style={{ padding: "0 var(--space-4)", fontSize: 11 }}>
          Amina lagi kenalan sama keuangan keluargamu — jawab santai aja, boleh
          bilang lewat kalau ada yang belum kepikiran
        </div>
      )}

      <div
        className="chat-quick-prompts"
        style={{
          padding: "var(--space-2) var(--space-4)",
          display: "flex",
          gap: 8,
          overflowX: "auto",
        }}
      >
        {(inWawancara ? ONBOARDING_PROMPTS : QUICK_PROMPTS).map((c) => (
          <button
            key={c.label}
            type="button"
            className="btn btn-secondary"
            style={{ fontSize: 12, whiteSpace: "nowrap", flex: "none" }}
            onClick={() => send(c.text)}
          >
            {c.label}
          </button>
        ))}
      </div>

      {(sendMessage.isError || stream.streamError) && (
        <div className="notice notice-danger" style={{ margin: "0 var(--space-4)" }}>
          <span>Amina sedang sulit dihubungi. Semua fitur tetap bisa dipakai secara manual.</span>
          <Link href="/transactions" className="btn btn-secondary">Catat manual</Link>
        </div>
      )}

      <div
        className="chat-composer"
        style={{
          padding: "var(--space-3) var(--space-4)",
          borderTop: "1px solid var(--color-divider)",
          display: "flex",
          gap: 8,
          alignItems: "center",
        }}
      >
        {/* Unggah struk & rekaman menunggu endpoint upload di API. */}
        <button
          type="button"
          className="btn btn-icon btn-secondary"
          onClick={() => send("[Foto struk diunggah]")}
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
            style={isRecording ? { animation: "amanaPulse 1s infinite" } : undefined}
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
              send(input);
            }
          }}
          aria-label="Pesan untuk Amina"
          disabled={!threadId}
        />
        <button
          type="button"
          className="btn btn-icon btn-primary"
          onClick={() => send(input)}
          title="Kirim"
          aria-label="Kirim"
          disabled={!threadId || sendMessage.isPending}
        >
          <Icon name="send" size={18} />
        </button>
      </div>
    </div>
  );
}
