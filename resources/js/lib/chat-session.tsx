import { createContext, useContext, type ReactNode } from "react";
import { useActiveFamily, useConfirmAiAction, useRejectAiAction, useChatStream, useChatThreads, useMessages, useSendMessage } from "@/lib/api/hooks";

function useChatSessionState() {
  const { familyId } = useActiveFamily();
  const threads = useChatThreads();
  const threadId = threads.data?.[0]?.id ?? null;
  const messages = useMessages(threadId);
  const sendMessage = useSendMessage(threadId);
  const confirmAiAction = useConfirmAiAction();
  const rejectAiAction = useRejectAiAction();
  const last = messages.data?.at(-1);
  const awaitingReply = last?.role === "user";
  // Open SSE only after POST has supplied the real server id. An optimistic
  // message must never start a worker before its queue job exists.
  const pendingMessageId = awaitingReply && last.id && !last.id.startsWith("optimistic-") ? last.id : null;
  const stream = useChatStream(threadId, familyId, pendingMessageId);
  return { threads, threadId, messages, sendMessage, awaitingReply, stream, confirmAiAction, rejectAiAction };
}

const ChatSessionContext = createContext<ReturnType<typeof useChatSessionState> | null>(null);

export function ChatSessionProvider({ children }: { children: ReactNode }) {
  const session = useChatSessionState();
  return <ChatSessionContext.Provider value={session}>{children}</ChatSessionContext.Provider>;
}

export function useChatSession() {
  const session = useContext(ChatSessionContext);
  if (!session) throw new Error("ChatSessionProvider is missing");
  return session;
}
