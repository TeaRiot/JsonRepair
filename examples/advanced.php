<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Teariot\JsonRepair\JsonRepair;

echo "=== teariot/json-repair — Advanced Examples ===\n\n";

// ─── Extract JSON from LLM response with <think> block ─────────────────
echo "1. Extract from LLM response with <think>:\n";
$llm = '<think>**Analyzing** I need to detect brand mentions.</think>  {"mentioned": true, "tone": "negative", "comment": "Bad reviews"}';
$json = JsonRepair::extractAndFix($llm);
echo "   Out: {$json}\n\n";

// ─── Extract from log line ─────────────────────────────────────────────
echo "2. Extract from log line:\n";
$log = '[2026-03-24 13:17:52] production.WARNING: JSON error: {"status": "ok", "count": 42}';
echo "   Out: " . JsonRepair::extractAndFix($log) . "\n\n";

// ─── Extract from plain text ───────────────────────────────────────────
echo "3. Extract from plain text:\n";
$text = "Based on my analysis, here is the result:\n\n{\"mentioned\": true, \"tone\": \"positive\"}";
echo "   Out: " . JsonRepair::extractAndFix($text) . "\n\n";

// ─── Extract from markdown code block ──────────────────────────────────
echo "4. Extract from markdown fence:\n";
$md = "Here is the JSON:\n```json\n{\"a\": 1, \"b\": 2}\n```\nDone.";
echo "   Out: " . JsonRepair::extractAndFix($md) . "\n\n";

// ─── Truncated LLM response with template ──────────────────────────────
echo "5. Truncated JSON + template:\n";
$template = [
    'mentioned'    => false,
    'occurrences'  => [],
    'position'     => null,
    'tone'         => 'non',
    'comment'      => null,
    'other_brands' => [],
];
$truncated = '{"mentioned": true, "tone": "negative"';
$data = JsonRepair::fixWithTemplate($truncated, $template);
echo "   Result: ";
echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

// ─── Extract + decode + template in one call ───────────────────────────
echo "6. extractAndDecode with template:\n";
$raw = '<think>Some reasoning</think> {"mentioned": true, "tone": "negative"}';
$data = JsonRepair::extractAndDecode($raw, $template);
echo "   Result: ";
echo json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";

// ─── LLM refusal mid-JSON ──────────────────────────────────────────────
echo "7. LLM refusal after partial JSON:\n";
$refusal = '{"mentioned": false, "occurrences": [], "position": null, "tone": "non", "I\'m sorry, but I cannot assist.';
$data = JsonRepair::fixWithTemplate($refusal, $template);
echo "   Result: ";
echo json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";

// ─── JSONP / string concat / regex ─────────────────────────────────────
echo "8. JSONP:   callback({\"ok\":true}); → " . JsonRepair::fix('callback({"ok":true});') . "\n";
echo "   Concat: \"a\" + \"b\"             → " . JsonRepair::fix('"a" + "b"') . "\n";
echo "   Regex:  /test/gi               → " . JsonRepair::fix('/test/gi') . "\n\n";

// ─── NDJSON ────────────────────────────────────────────────────────────
echo "9. NDJSON:\n";
$ndjson = "{\"a\":1}\n{\"b\":2}\n{\"c\":3}";
echo "   Out: " . JsonRepair::fix($ndjson) . "\n\n";

// ─── HTML entities ─────────────────────────────────────────────────────
echo "10. HTML entities:\n";
echo "   Out: " . JsonRepair::fix('{"t":"Tom &amp; Jerry &lt;3"}') . "\n\n";

// ─── Streaming ─────────────────────────────────────────────────────────
echo "11. Streaming:\n";
$tmp = tempnam(sys_get_temp_dir(), 'jr_');
file_put_contents($tmp, '{"items": [1, 2, 3,], "ok": true,}');
$h = fopen($tmp, 'r');
echo "   Out: " . JsonRepair::streamCollect($h) . "\n";
fclose($h);
unlink($tmp);
echo "\n";

// ─── Helper functions ──────────────────────────────────────────────────
echo "12. Helper functions:\n";
echo "   json_repair:             " . \Teariot\JsonRepair\json_repair("{a: 'b'}") . "\n";
echo "   json_try_repair:         " . \Teariot\JsonRepair\json_try_repair("{x: 1}") . "\n";
echo "   json_extract_and_fix:    " . \Teariot\JsonRepair\json_extract_and_fix('text {"a":1} end') . "\n";

$data = \Teariot\JsonRepair\json_extract_and_decode('prefix {"x":42}', ['x' => 0, 'y' => null]);
echo "   json_extract_and_decode: x={$data['x']}, y=" . var_export($data['y'], true) . "\n";

$data = \Teariot\JsonRepair\json_fix_with_template('{a:1}', ['a' => 0, 'b' => 'default']);
echo "   json_fix_with_template:  " . json_encode($data) . "\n\n";
