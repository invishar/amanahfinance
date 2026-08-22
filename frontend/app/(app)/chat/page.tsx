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
  useCreateOnboardingAnswer,
  useCreateThread,
  useIncomeSources,
  useMessages,
  useOnboardingAnswers,
  usePendingAiActions,
  useRejectAiAction,
  useSavingsGoals,
  useSendMessage,
  useWallets,
} from "@/lib/api/hooks";
import {
  DEMO_AMINA,
  DEMO_CHIPS,
  demoReply,
  type DemoActionCard,
  type Scenario,
} from "@/lib/mock/assistant";

interface DemoItem extends ChatItem {
  at: number;
  /**
   * Jumlah pesan server (`messages.data`) yang sudah ada saat item lokal ini
   * dibuat. Dipakai untuk menyisipkan bubble ini di posisi yang benar --
   * BUKAN lewat perbandingan `at` dengan timestamp server, karena jam client
   * & server bisa beda (root cause bubble jawaban user ketuker posisi dgn
   * pertanyaan onboarding berikutnya begitu server clock sedikit di
   * belakang/depan jam browser).
   */
  anchor: number;
  /**
   * `question_key` yang dijawab, hanya diisi utk echo jawaban onboarding.
   * Dipakai untuk membuang bubble optimistic lokal ini begitu jawaban yang
   * sama sudah datang dari `GET /onboarding-answers` (lihat `items` di
   * bawah) -- supaya tidak tampil dobel.
   */
  onboardingQuestionKey?: string;
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

  /* --- Bagian demo (skenario contoh lewat chip, lihat lib/mock/assistant) - */
  const [demoItems, setDemoItems] = useState<DemoItem[]>([]);
  const [isTyping, setIsTyping] = useState(false);
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
  // Naskah & progres wawancara awal datang dari server lewat
  // ChatThreadResource.onboarding (API-v1.md "Chat Threads") -- klien cuma
  // tahu `question_key` yang sedang aktif, tidak pernah menyimpan naskahnya
  // sendiri (CLAUDE.md "Alur AI").
  const onboarding = thread?.kind === "onboarding" ? thread.onboarding : null;
  const inWawancara = Boolean(onboarding) && !onboarding!.done;
  const createOnboardingAnswer = useCreateOnboardingAnswer(threadId);
  // Sumber kebenaran bubble jawaban onboarding -- lihat `useOnboardingAnswers`
  // kenapa ini dibutuhkan (jawabannya sengaja tidak tersimpan sbg ChatMessage).
  const onboardingAnswers = useOnboardingAnswers();
  const [skipFeedback, setSkipFeedback] = useState<
    { type: "success" | "error"; text: string } | null
  >(null);

  useEffect(
    () => () => {
      timers.current.forEach(clearTimeout);
    },
    [],
  );

  const later = (fn: () => void, ms: number) => {
    timers.current.push(setTimeout(fn, ms));
  };

  const pushDemo = (item: Omit<DemoItem, "at" | "id" | "anchor"> & { id?: string }) => {
    setDemoItems((prev) => [
      ...prev,
      {
        ...item,
        id: item.id ?? `demo-${Date.now()}-${prev.length}`,
        at: Date.now(),
        anchor: messages.data?.length ?? 0,
      },
    ]);
  };

  const runDemoAnswer = (text: string, scenario?: Scenario) => {
    if (!DEMO_AMINA || inWawancara) return;
    setIsTyping(true);

    // Timer di sini adalah bagian dari demo; balasan sungguhan nanti dipicu
    // event dari server, bukan setTimeout.
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

    // Selama wawancara awal, jawaban bebas dicatat sebagai OnboardingAnswer
    // (bukan cuma lewat tombol "Lewati") supaya progresnya beneran maju --
    // pertanyaan berikutnya disisipkan server ke thread yang sama begitu ini
    // sukses. Sengaja TIDAK ikut lewat /chat-threads/messages di sini: thread
    // ini masih naskah terstruktur, bukan obrolan bebas, jadi tidak perlu
    // memicu balasan LLM sungguhan (yang juga cuma mubazir kena
    // rate limit provider di tengah wawancara). Balasan user langsung
    // ditampilkan lewat bubble lokal (optimistic) supaya riwayatnya kelihatan
    // seketika, lalu digantikan bubble dari `useOnboardingAnswers` begitu
    // request-nya sukses -- itu yang bikin bubble-nya tetap ada walau user
    // pindah halaman lalu balik lagi.
    if (inWawancara && scenario === undefined) {
      // Sama seperti skipOnboardStep: tunggu question_key yang segar dulu
      // kalau masih ada refetch dari jawaban sebelumnya yang belum selesai.
      if (!onboarding?.question_key || createOnboardingAnswer.isPending || threads.isFetching)
        return;
      pushDemo({
        role: "user",
        content,
        onboardingQuestionKey: onboarding.question_key,
      });
      setSkipFeedback(null);
      createOnboardingAnswer.mutate(
        { question_key: onboarding.question_key, answer: { note: content } },
        {
          onError: () => {
            setSkipFeedback({
              type: "error",
              text: "Gagal menyimpan jawaban, coba lagi.",
            });
          },
        },
      );
      return;
    }

    sendMessage.mutate({
      content,
      input_mode: scenario === "transaction_voice" ? "voice" : "text",
    });
    runDemoAnswer(content, scenario);
  };

  const skipOnboardStep = () => {
    // `threads.isFetching` juga dicek supaya tidak submit `question_key` basi
    // -- begitu satu jawaban sukses, chat-threads diinvalidasi (lihat
    // useCreateOnboardingAnswer) dan butuh waktu refetch sebelum
    // `onboarding.question_key` di closure ini benar-benar yang terbaru.
    if (!onboarding?.question_key || createOnboardingAnswer.isPending || threads.isFetching)
      return;
    setSkipFeedback(null);
    createOnboardingAnswer.mutate(
      { question_key: onboarding.question_key, skipped: true },
      {
        onSuccess: () => {
          setSkipFeedback({
            type: "success",
            text: "Oke, pertanyaan ini dilewati — bisa diisi belakangan.",
          });
          later(() => setSkipFeedback(null), 2500);
        },
        onError: () => {
          setSkipFeedback({
            type: "error",
            text: "Gagal melewati pertanyaan, coba lagi.",
          });
        },
      },
    );
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

  /**
   * Pesan server + item demo. `fromServer`, `fromAiActions`, &
   * `fromOnboardingAnswers` semuanya pakai jam server jadi aman diurutkan
   * lewat `at`. `demoItems` (balasan demo & echo jawaban onboarding,
   * keduanya lokal-only) TIDAK ikut sort-by-`at` terhadap ketiganya --
   * disisipkan lewat `anchor` (jumlah pesan server saat item itu dibuat,
   * lihat `pushDemo`) supaya urutannya tidak goyah saat jam client & server
   * beda.
   *
   * Echo jawaban onboarding butuh perlakuan ekstra: jawabannya sengaja tidak
   * tersimpan sebagai `ChatMessage` (lihat komentar di `send()`), cuma
   * `OnboardingAnswer`, jadi begitu komponen ini unmount (user pindah
   * halaman) `demoItems` hilang dan bubble-nya lenyap padahal jawabannya
   * masih ada di server. `fromOnboardingAnswers` di bawah membaca ulang dari
   * `GET /onboarding-answers` supaya bubble itu muncul lagi setelah remount;
   * begitu jawaban utk satu `question_key` sudah kebaca dari sana, echo
   * lokal utk `question_key` yang sama dibuang dari `demoItems` supaya tidak
   * dobel.
   */
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
      anchor: 0,
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
            anchor: 0,
          }))
      : [];

    const answeredQuestionKeys = new Set<string>();
    const fromOnboardingAnswers = (onboardingAnswers.data ?? [])
      .filter((a) => !a.skipped && typeof a.answer?.note === "string" && a.answer.note)
      .map((a) => {
        if (a.question_key) answeredQuestionKeys.add(a.question_key);
        return {
          id: `onboarding-answer-${a.id}`,
          role: "user" as const,
          content: String(a.answer!.note),
          at: a.answered_at ? Date.parse(a.answered_at) : 0,
          anchor: 0,
        };
      });

    const result: DemoItem[] = [
      ...fromServer,
      ...fromAiActions,
      ...fromOnboardingAnswers,
    ].sort((a, b) => a.at - b.at);

    demoItems
      .filter(
        (item) =>
          !item.onboardingQuestionKey ||
          !answeredQuestionKeys.has(item.onboardingQuestionKey),
      )
      .forEach((item, i) => {
        result.splice(Math.min(item.anchor + i, result.length), 0, item);
      });

    return result;
  }, [messages.data, demoItems, pendingAiActions.data, onboardingAnswers.data]);

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
        isTyping={isTyping || stream.isThinking || createOnboardingAnswer.isPending}
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

      {inWawancara && onboarding && (
        <div className="text-muted" style={{ padding: "0 var(--space-4)", fontSize: 11 }}>
          Pertanyaan {onboarding.step} dari {onboarding.total} —
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
            disabled={createOnboardingAnswer.isPending || threads.isFetching}
          >
            {createOnboardingAnswer.isPending ? "Melewati…" : "Lewati pertanyaan ini"}
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

      {skipFeedback && (
        <p
          className={skipFeedback.type === "error" ? "field-error" : "text-muted"}
          style={{ padding: "0 var(--space-4)", fontSize: 12 }}
        >
          {skipFeedback.text}
        </p>
      )}

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
