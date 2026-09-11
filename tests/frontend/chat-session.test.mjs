import { test } from "node:test";
import assert from "node:assert/strict";
import { JSDOM } from "jsdom";

test("chat survives Inertia navigation, consumes split SSE cards, and never reopens a settled form", async () => {
  const dom = new JSDOM('<!doctype html><html><head></head><body><div id="app"></div></body></html>', { url: "http://localhost/chat", pretendToBeVisual: true });
  for (const key of ["window", "document", "navigator", "location", "history", "sessionStorage", "HTMLElement", "Element", "Node", "CustomEvent", "Event", "MutationObserver", "DOMParser"])
    Object.defineProperty(globalThis, key, { configurable: true, value: dom.window[key] });
  globalThis.requestAnimationFrame = dom.window.requestAnimationFrame.bind(dom.window);
  globalThis.cancelAnimationFrame = dom.window.cancelAnimationFrame.bind(dom.window);
  globalThis.IS_REACT_ACT_ENVIRONMENT = true;
  dom.window.scrollTo = () => {};
  dom.window.performance.getEntriesByType = () => [];
  dom.window.HTMLElement.prototype.scrollIntoView = () => {};
  dom.window.matchMedia = () => ({ matches: false, addListener() {}, removeListener() {}, addEventListener() {}, removeEventListener() {} });
  const React = await import("react");
  const { createRoot } = await import("react-dom/client");
  const { createInertiaApp, router } = await import("@inertiajs/react");
  const { QueryClient, QueryClientProvider } = await import("@tanstack/react-query");
  const { AppLayout } = await import("../../resources/js/layouts/AppLayout.tsx");
  const { AuthProvider } = await import("../../resources/js/lib/auth.tsx");
  const { UiProvider } = await import("../../resources/js/lib/ui-store.tsx");
  const { default: Chat } = await import("../../resources/js/Pages/App/Chat.tsx");
  const { default: Budgets } = await import("../../resources/js/Pages/App/Wallets.tsx");
  const h = React.createElement;
  const layout = (page) => h(AppLayout, null, page);
  Chat.layout = layout;
  Budgets.layout = layout;
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: 120000, gcTime: 0 }, mutations: { retry: false, gcTime: 0 } } });
  let root;
  let turn = 0, streams = 0, earlyAborts = 0, controller, complete = false, failNextSend = false;
  const messages = [{ id: "greeting", role: "assistant", content: "Halo penguji", created_at: "2026-09-11T10:00:00Z" }];
  let actions = [];
  const originalFetch = globalThis.fetch;
  globalThis.fetch = async (input, options = {}) => {
    const path = new URL(input, "http://localhost").pathname.replace("/api/v1", "");
    const json = (data) => new Response(JSON.stringify({ data }), { headers: { "Content-Type": "application/json" } });
    if (path === "/families") return json([{ id: "family", name: "Penguji", onboarding_done: true }]);
    if (path === "/chat-threads") return json([{ id: "thread", kind: "general" }]);
    if (path.endsWith("/messages")) {
      if (options.method === "POST") {
        if (failNextSend) throw new Error("offline");
        const message = { id: `user-${++turn}`, thread_id: "thread", role: "user", content: JSON.parse(options.body).content, created_at: `2026-09-11T10:0${turn}:00Z` };
        messages.push(message); return json(message);
      }
      assert.match(String(input), /latest=1/);
      return json(messages);
    }
    if (path.endsWith("/stream")) {
      streams++; complete = false;
      options.signal.addEventListener("abort", () => { if (!complete) earlyAborts++; });
      return new Response(new ReadableStream({ start(c) { controller = c; }, cancel() { if (!complete) earlyAborts++; } }));
    }
    if (path === "/ai-actions") return json(actions);
    if (path.endsWith("/confirm")) {
      const action = actions.find(a => path.includes(a.id));
      action.status = "confirmed";
      return json({ ...action, result_id: "transaction" });
    }
    if (path === "/accounts") return json([{ id: "account", name: "Tunai", account_type: "cash", current_balance: 50000 }]);
    if (path === "/wallets") return json([{ id: "budget", name: "Jajan", monthly_budget: 100000 }]);
    if (path === "/analytics/summary") return json({ wallets: [] });
    return json([]);
  };
  const flush = async () => React.act(async () => { await new Promise(r => setTimeout(r, 25)); });
  const until = async (predicate, description) => {
    for (let i = 0; i < 80; i++) { await flush(); if (predicate()) return; }
    assert.fail(`${description}\n${document.body.textContent}`);
  };
  const emit = (event, data) => controller.enqueue(new TextEncoder().encode(`event: ${event}\ndata: ${JSON.stringify(data)}\n\n`));
  const click = async (text) => {
    const button = [...document.querySelectorAll("button")].find(b => b.textContent.trim() === text);
    assert.ok(button, `Missing button ${text}`);
    await React.act(async () => button.click());
  };
  const finish = () => { complete = true; emit("retry", { after: "2026-09-11T10:00:00Z" }); controller.close(); };
  const navigate = async (component, url) => {
    await React.act(async () => router.replace({ component, url, preserveState: false }));
    await until(() => location.pathname === url, `Navigate to ${url}`);
  };
  try {
    await React.act(async () => createInertiaApp({
      page: { component: "App/Chat", props: { auth: { user: { id: "user", name: "Penguji", email: "test@example.test" } }, errors: {} }, url: "/chat", version: null, clearHistory: false, encryptHistory: false },
      resolve: name => name === "App/Chat" ? Chat : Budgets,
      setup: ({ el, App, props }) => { root = createRoot(el); root.render(h(QueryClientProvider, { client: queryClient }, h(AuthProvider, null, h(UiProvider, null, h(App, props))))); },
      progress: false,
    }));
    await until(() => document.body.textContent.includes("Halo penguji"), "Initial chat loads");
    await click("Catat pengeluaran");
    await until(() => streams === 1, "Stream starts after POST");
    await React.act(async () => emit("progress", { message_id: "user-1", stage: "drafting" }));
    assert.match(document.body.textContent, /Amina sedang membuat formulir konfirmasi/);
    await navigate("App/Wallets", "/wallets");
    await until(() => document.body.textContent.includes("Budgeting keluarga"), "Budgeting page loads");
    assert.match(document.body.textContent, /Amina sedang membuat formulir konfirmasi/);
    assert.equal(earlyAborts, 0, "Navigation must not abort SSE");
    messages.push({ id: "reply-1", role: "assistant", content: "Formulir pertama siap", created_at: "2026-09-11T10:01:01Z" });
    await React.act(async () => emit("message", messages.at(-1)));
    await flush();
    assert.equal(earlyAborts, 0, "A message chunk must not cancel following action chunks");
    const first = { id: "action-1", message_id: "user-1", status: "pending", action: "create_transaction", payload: { type: "expense", amount: 5000, note: "jajan", account_id: "account", wallet_id: "budget" }, created_at: "2026-09-11T10:01:01Z" };
    actions.push(first);
    await React.act(async () => { emit("action_card", first); finish(); });
    await until(() => !document.body.textContent.includes("Amina sedang membuat formulir"), "Background work completes");
    await navigate("App/Chat", "/chat");
    await until(() => document.body.textContent.includes("Formulir pertama siap"), "Reply survives navigation");
    await click("Ya, lanjutkan");
    await until(() => document.body.textContent.includes("Sudah disimpan"), "Confirmation completes");
    await click("Catat pengeluaran");
    await until(() => streams === 2, "Next turn starts");
    await React.act(async () => emit("action_card", { ...first, status: "pending" }));
    const second = { ...first, id: "action-2", message_id: "user-2", status: "pending", created_at: "2026-09-11T10:02:01Z" };
    actions = [{ ...first, status: "pending" }, second]; // delayed list response
    messages.push({ id: "reply-2", role: "assistant", content: "Formulir kedua siap", created_at: "2026-09-11T10:02:01Z" });
    await React.act(async () => { emit("message", messages.at(-1)); emit("action_card", second); finish(); });
    await until(() => document.body.textContent.includes("Formulir kedua siap"), "Next reply completes");
    assert.equal([...document.querySelectorAll("button")].filter(b => b.textContent.trim() === "Ya, lanjutkan").length, 1);
    assert.match(document.body.textContent, /Sudah disimpan/);
    assert.equal(earlyAborts, 0);
    failNextSend = true;
    await click("Catat pengeluaran");
    await until(() => document.body.textContent.includes("Amina sedang sulit dihubungi"), "Send error is visible");
    assert.equal(streams, 2, "A failed POST must not open a stream");
    assert.equal(queryClient.getQueryData(["messages", "thread"]).some(m => m.id.startsWith("optimistic-")), false);
  } finally {
    await React.act(async () => root?.unmount());
    queryClient.clear();
    globalThis.fetch = originalFetch;
    dom.window.close();
  }
});
