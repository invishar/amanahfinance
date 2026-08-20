"use client";

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
import {
  DEMO_AMINA,
  DEMO_CHIPS,
  DEMO_ONBOARD_QUESTIONS,
  demoGreeting,
  demoReply,
  type DemoActionCard,
  type Scenario,
} from "@/lib/mock/assistant";

interface DemoItem extends ChatItem {
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
  // Balasan Amina sungguhan (real LLM) datang lewat SSE, bukan demo timer --
  // aktif berdampingan dengan jalur demo di bawah, yang no-op sendiri begitu
  // NEXT_PUBLIC_MOCK_AMINA=0 (lihat DEMO_AMINA).
  const stream = useChatStream(threadId, familyId);

  /* --- Kartu aksi (AiAction) sungguhan ------------------------------------ */
  const pendingAiActions = usePendingAiActions();
  const confirmAiAction = useConfirmAiAction();
  const rejectAiAction = useRejectAiAction();
  const [aiActionErrors, setAiActionErrors] = useState<Record<string, string>>({});

  const confirmAiActionCard = (id: string) => {
    setAiActionErrors((prev) => {
      const next = { ...prev };
      delete next[id];
      return next;
    });
    confirmAiAction.mutate(
      { id },
      {
        onError: (error) => {
          const message =
            error instanceof ApiError ? error.message : "Gagal menyimpan, coba lagi.";
          setAiActionErrors((prev) => ({ ...prev, [id]: message }));
        },
      },
    );
  };

  const rejectAiActionCard = (id: string) => rejectAiAction.mutate(id);

  const [input, setInput] = useState("");
  const [isRecording, setIsRecording] = useState(false);
  const timers = useRef<ReturnType<typeof setTimeout>[]>([]);

  /* --- Bagian demo (aktif selama backend belum membalas) ----------------- */
  const [demoItems, setDemoItems] = useState<DemoItem[]>([]);
  const [isTyping, setIsTyping] = useState(false);
  // `undefined` = belum pernah diubah pengguna; nilainya diturunkan di bawah.
  const [stepOverride, setStepOverride] = useState<number | null | undefined>(
    undefined,
  );
  const threadRequested = useRef(false);

  // Belum punya thread → buat satu (sekali saja).
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

  // Sapaan + pertanyaan pertama: nilai turunan yang dipatok ke waktu thread
  // dibuat, supaya tetap di urutan paling atas dan tidak butuh state/effect.
  const thread = threads.data?.[0];
  const introItems = useMemo<DemoItem[]>(() => {
    if (!DEMO_AMINA || !thread) return [];
    const at = thread.created_at ? Date.parse(thread.created_at) : 0;
    return [
      {
        id: "demo-greeting",
        role: "assistant",
        content: demoGreeting(family?.name ?? "keluargamu"),
        at,
      },
      {
        id: "demo-q0",
        role: "assistant",
        content: DEMO_ONBOARD_QUESTIONS[0],
        at: at + 1,
      },
    ];
  }, [thread, family?.name]);

  // Wawancara dianggap berjalan selama thread belum punya pesan dari user.
  const serverUserMessages = (messages.data ?? []).filter(
    (m) => m.role === "user",
  ).length;
  const onboardStep =
    stepOverride !== undefined
      ? stepOverride
      : DEMO_AMINA && messages.isSuccess && serverUserMessages === 0
        ? 0
        : null;

  useEffect(
    () => () => {
      timers.current.forEach(clearTimeout);
    },
    [],
  );

  const later = (fn: () => void, ms: number) => {
    timers.current.push(setTimeout(fn, ms));
  };

  const pushDemo = (item: Omit<DemoItem, "at" | "id"> & { id?: string }) => {
    setDemoItems((prev) => [
      ...prev,
      {
        ...item,
        id: item.id ?? `demo-${Date.now()}-${prev.length}`,
        at: Date.now(),
      },
    ]);
  };

  const runDemoAnswer = (text: string, scenario?: Scenario) => {
    if (!DEMO_AMINA) return;
    setIsTyping(true);

    // Timer di sini adalah bagian dari demo; balasan sungguhan nanti dipicu
    // event dari server, bukan setTimeout.
    if (onboardStep !== null && scenario === undefined) {
      later(() => {
        setIsTyping(false);
        const next = onboardStep + 1;
        if (next < DEMO_ONBOARD_QUESTIONS.length) {
          setStepOverride(next);
          pushDemo({ role: "assistant", content: DEMO_ONBOARD_QUESTIONS[next] });
        } else {
          setStepOverride(null);
          pushDemo({
            role: "assistant",
            content:
              "Makasih banyak infonya! Sekarang kamu bisa langsung cerita transaksi atau lihat ringkasannya di Dashboard.",
          });
        }
      }, 700);
      return;
    }

    later(() => {
      setIsTyping(false);
      const reply = demoReply(text, scenario, {
        walletName: wallets.data?.[0]?.name,
        accountName: accounts.data?.[0]?.name,
      });
      pushDemo({
        role: "assistant",
        content: reply.content,
        card: reply.card,
      });
    }, 750);
  };

  const send = (text: string, scenario?: Scenario) => {
    const content = text.trim();
    if (!content || !threadId) return;
    setInput("");
    sendMessage.mutate({
      content,
      input_mode: scenario === "transaction_voice" ? "voice" : "text",
    });
    runDemoAnswer(content, scenario);
  };

  const skipOnboardStep = () => {
    if (onboardStep === null) return;
    setIsTyping(true);
    later(() => {
      setIsTyping(false);
      pushDemo({
        role: "assistant",
        content: "Oke, nggak masalah — bisa diisi belakangan.",
      });
      const next = onboardStep + 1;
      if (next < DEMO_ONBOARD_QUESTIONS.length) {
        setStepOverride(next);
        later(
          () =>
            pushDemo({
              role: "assistant",
              content: DEMO_ONBOARD_QUESTIONS[next],
            }),
          500,
        );
      } else {
        setStepOverride(null);
      }
    }, 700);
  };

  const resolveCard = (id: string, status: "confirmed" | "cancelled") => {
    setDemoItems((prev) =>
      prev.map((item) =>
        item.id === id && item.card
          ? { ...item, card: { ...item.card, status } as DemoActionCard }
          : item,
      ),
    );
  };

  const toggleRecording = () => {
    if (isRecording) return;
    setIsRecording(true);
    later(() => {
      setIsRecording(false);
      send("Tadi abis makan siang di warteg 25rb dari GoPay", "transaction_voice");
    }, 1500);
  };

  /** Pesan server + item demo, diurutkan berdasarkan waktu. */
  const items: DemoItem[] = useMemo(() => {
    const fromServer = (messages.data ?? []).map((m) => ({
      id: m.id ?? "",
      role: (m.role === "user" || m.role === "system" ? m.role : "assistant") as
        | "user"
        | "assistant"
        | "system",
      content: m.content ?? "",
      pending: (m.id ?? "").startsWith("optimistic-"),
      at: m.created_at ? Date.parse(m.created_at) : 0,
    }));

    // `/ai-actions` tidak thread-scoped (lihat useChatStream) -- saring ke
    // milik thread ini lewat message_id. Event SSE tidak membawa message_id
    // sama sekali (server sudah menyaringnya duluan), jadi selalu lolos.
    const messageIds = new Set((messages.data ?? []).map((m) => m.id));
    const fromAiActions = !DEMO_AMINA
      ? (pendingAiActions.data ?? [])
          .filter((a) => !a.message_id || messageIds.has(a.message_id))
          .map((a) => ({
            id: `ai-action-${a.id}`,
            role: "assistant" as const,
            content: "",
            aiAction: a,
            at: a.created_at ? Date.parse(a.created_at) : 0,
          }))
      : [];

    return [...introItems, ...fromServer, ...fromAiActions, ...demoItems].sort(
      (a, b) => a.at - b.at,
    );
  }, [introItems, messages.data, demoItems, pendingAiActions.data]);

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
        {DEMO_AMINA && (
          <span className="tag tag-neutral" title="Balasan Amina belum datang dari server">
            Balasan demo
          </span>
        )}
      </div>

      <MessageList
        items={items}
        isTyping={isTyping || stream.isThinking}
        demo={DEMO_AMINA}
        onResolveCard={resolveCard}
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
          Pertanyaan {(onboardStep ?? 0) + 1} dari {DEMO_ONBOARD_QUESTIONS.length} —
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
          DEMO_CHIPS.map((c) => (
            <button
              key={c.label}
              type="button"
              className="btn btn-secondary"
              style={{ fontSize: 12, whiteSpace: "nowrap", flex: "none" }}
              onClick={() => send(c.demoText, c.scenario)}
            >
              {c.label}
            </button>
          ))
        )}
      </div>

      {sendMessage.isError && (
        <p className="field-error" style={{ padding: "0 var(--space-4)" }}>
          Pesan gagal terkirim. Coba lagi.
        </p>
      )}

      <div
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
          onClick={() => send("[Foto struk diunggah]", "transaction_receipt")}
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
          disabled={!threadId}
        >
          <Icon name="send" size={18} />
        </button>
      </div>
    </div>
  );
}
