import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import ts from "typescript";

const source = readFileSync(new URL("../../resources/js/lib/chat-state.ts", import.meta.url), "utf8");
const { outputText } = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } });
const { mergeAiActions, parseSseFrame } = await import(`data:text/javascript;base64,${Buffer.from(outputText).toString("base64")}`);

test("delayed list and SSE replay cannot reopen confirmed, edited or rejected cards", () => {
  for (const status of ["confirmed", "edited", "rejected", "expired"]) {
    const old = { id: "old", message_id: "first", status };
    const result = mergeAiActions([old], [{ ...old, status: "pending" }, { id: "new", message_id: "second", status: "pending" }]);
    assert.equal(result[0].status, status);
    assert.equal(result[1].status, "pending");
    assert.equal(mergeAiActions(result, [{ ...old, status: "pending" }]).length, 2);
  }
});

test("confirmation inserts missing action and updates a pending card", () => {
  const confirmed = { id: "a", status: "confirmed", result_id: "transaction" };
  assert.deepEqual(mergeAiActions([], [confirmed]), [confirmed]);
  assert.equal(mergeAiActions([{ id: "a", status: "pending" }], [confirmed])[0].result_id, "transaction");
});

test("SSE accepts CRLF, multiline JSON, and ignores malformed events", () => {
  assert.deepEqual(parseSseFrame('event:progress\r\ndata: {"stage":"drafting"}'), { event: "progress", data: { stage: "drafting" } });
  assert.deepEqual(parseSseFrame('event: action_card\ndata: {"id":"a",\ndata: "message_id":"m"}').data, { id: "a", message_id: "m" });
  assert.equal(parseSseFrame('event: progress\ndata: broken'), null);
  assert.equal(parseSseFrame(': heartbeat'), null);
});
