import { test } from "node:test";
import assert from "node:assert/strict";
import { JSDOM } from "jsdom";

test("admin discovers and saves a combo or provider model and discards stale catalogs", async () => {
  const dom = new JSDOM('<!doctype html><div id="root"></div>', { url: "http://localhost/admin/llm-settings" });
  for (const name of ["window", "document", "navigator", "HTMLElement", "Element", "Node", "Event"])
    Object.defineProperty(globalThis, name, { configurable: true, value: dom.window[name] });
  dom.window.performance.getEntriesByType = () => [];
  globalThis.IS_REACT_ACT_ENVIRONMENT = true;
  const React = await import("react");
  const { createRoot } = await import("react-dom/client");
  const { QueryClient, QueryClientProvider } = await import("@tanstack/react-query");
  const { LlmSettingForm } = await import("../../resources/js/Pages/Admin/LlmSettings.tsx");
  const client = new QueryClient({ defaultOptions: { mutations: { retry: false, gcTime: 0 }, queries: { retry: false, gcTime: 0 } } });
  const root = createRoot(document.getElementById("root"));
  const initial = { gateway: "9router", provider: "openai_compatible", selection_mode: "model", base_url: "https://router.test/v1", model: "old-model", has_key: true, key_preview: "...1234" };
  const catalog = { base_url: "https://new-router.test/v1", fetched_at: new Date().toISOString(), models: [
    { id: "amina-combo", name: "Amina Combo", kind: "combo", provider: "combo", supports_tools: null },
    { id: "provider-a/chat-model", name: "Chat model", kind: "model", provider: "provider-a", supports_tools: true },
    { id: "provider-b/no-tools", name: "No tools", kind: "model", provider: "provider-b", supports_tools: false },
  ] };
  const saved = [];
  let resolveFirst, catalogCalls = 0, failCatalog = false;
  const originalFetch = globalThis.fetch;
  globalThis.fetch = async (url, options) => {
    if (url.endsWith("/9router/models")) {
      catalogCalls++;
      if (catalogCalls === 1) return new Promise(resolve => { resolveFirst = resolve; });
      if (failCatalog) return new Response(JSON.stringify({ message: "Periksa koneksi", errors: { key: ["9Router menolak API key"] } }), { status: 422 });
      return new Response(JSON.stringify({ data: catalog }));
    }
    const body = JSON.parse(options.body);
    saved.push(body);
    return new Response(JSON.stringify({ data: { ...initial, ...body } }));
  };
  const flush = async () => React.act(async () => { await new Promise(resolve => setTimeout(resolve, 20)); });
  const until = async predicate => { for (let i = 0; i < 80; i++) { await flush(); if (predicate()) return; } assert.fail(document.body.textContent); };
  const button = text => [...document.querySelectorAll("button")].find(b => b.textContent === text);
  const click = async text => React.act(async () => { assert.ok(button(text), text); button(text).click(); });
  const change = async (id, value) => React.act(async () => {
    const element = document.getElementById(id);
    const prototype = element.tagName === "SELECT" ? dom.window.HTMLSelectElement.prototype : dom.window.HTMLInputElement.prototype;
    Object.getOwnPropertyDescriptor(prototype, "value").set.call(element, value);
    element.dispatchEvent(new dom.window.Event(element.tagName === "SELECT" ? "change" : "input", { bubbles: true }));
  });
  try {
    await React.act(async () => root.render(React.createElement(QueryClientProvider, { client }, React.createElement(LlmSettingForm, { initial }))));
    assert.equal(button("Simpan pengaturan").disabled, true);
    await click("Muat combo & model");
    await until(() => Boolean(resolveFirst));
    await change("llm-base-url", "https://new-router.test/v1");
    await React.act(async () => resolveFirst(new Response(JSON.stringify({ data: catalog }))));
    await until(() => Boolean(button("Muat combo & model")));
    assert.equal(document.body.textContent.includes("Terhubung."), false, "Old connection response must be ignored");
    await click("Muat combo & model");
    await until(() => document.body.textContent.includes("Terhubung."));
    await change("llm-mode", "combo");
    await change("llm-model", "amina-combo");
    await click("Simpan pengaturan");
    await until(() => saved.length === 1 && document.body.textContent.includes("Tersimpan."));
    assert.equal(saved[0].model, "amina-combo");
    assert.equal(saved[0].provider, "openai_compatible");
    assert.equal(saved[0].gateway, "9router");
    assert.equal(saved[0].selection_mode, "combo");
    assert.equal("key" in saved[0], false);
    await change("llm-mode", "model");
    assert.equal(document.querySelector('option[value="provider-b/no-tools"]').disabled, true);
    await change("llm-provider-filter", "provider-a");
    assert.equal(document.querySelector('option[value="provider-b/no-tools"]'), null);
    await change("llm-model", "provider-a/chat-model");
    await click("Simpan pengaturan");
    await until(() => saved.length === 2 && document.body.textContent.includes("Tersimpan."));
    assert.equal(saved[1].model, "provider-a/chat-model");
    assert.equal(saved[1].selection_mode, "model");
    failCatalog = true;
    await click("Muat combo & model");
    await until(() => document.body.textContent.includes("9Router menolak API key"));
    assert.equal(button("Simpan pengaturan").disabled, true);
    assert.equal(saved.length, 2, "Failed discovery does not change saved config");
  } finally {
    await React.act(async () => root.unmount());
    client.clear();
    globalThis.fetch = originalFetch;
    dom.window.close();
  }
});
