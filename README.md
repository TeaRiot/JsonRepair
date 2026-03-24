# teariot/json-repair

PHP 8.0+ library to repair broken, malformed, or non-standard JSON.

Handles real-world garbage: LLM responses with `<think>` blocks, truncated output, single quotes, trailing commas, comments, JSONP, Python constants, MongoDB types, HTML entities, and more.

## Installation

```bash
composer require teariot/json-repair
```

## Quick Start

```php
use Teariot\JsonRepair\JsonRepair;

// Fix broken JSON
$json = JsonRepair::fix("{name: 'John', age: 30,}");
// → {"name":"John","age":30}

// Fix and decode in one call
$data = JsonRepair::decode("{active: True, tags: ['php', 'json',]}");
// → ['active' => true, 'tags' => ['php', 'json']]
```

## Extract JSON from Text

Pull JSON out of LLM responses, log lines, or any surrounding text:

```php
// From LLM response with <think> block
$raw = '<think>Analyzing the query...</think> {"result": true, "score": 42}';
$json = JsonRepair::extractAndFix($raw);
// → {"result":true,"score":42}

// From log line
$log = '[2024-03-24 13:17:52] production.WARNING: Response: {"status": "ok"}';
$json = JsonRepair::extractAndFix($log);
// → {"status":"ok"}

// From markdown code block
$md = "```json\n{\"a\": 1}\n```";
$json = JsonRepair::extractAndFix($md);
// → {"a":1}

// From plain text
$text = "Here is the analysis result:\n\n{\"mentioned\": true, \"tone\": \"positive\"}";
$json = JsonRepair::extractAndFix($text);
// → {"mentioned":true,"tone":"positive"}
```

## Template: Fill Missing Keys

When LLM output is truncated or incomplete, apply a template to ensure all expected keys exist:

```php
$template = [
    'mentioned'    => false,
    'occurrences'  => [],
    'position'     => null,
    'tone'         => 'non',
    'comment'      => null,
    'other_brands' => [],
];

// Truncated JSON — missing keys filled from template
$data = JsonRepair::fixWithTemplate(
    '{"mentioned": true, "tone": "negative"',
    $template
);
// → ['mentioned' => true, 'tone' => 'negative', 'occurrences' => [], 'position' => null, ...]

// Extract + fix + decode + template in one call
$data = JsonRepair::extractAndDecode(
    '<think>reasoning</think> {"mentioned": true, "tone": "negative"}',
    $template
);
```

## What It Fixes

| Problem | Input | Output |
|---|---|---|
| Unquoted keys | `{name: "John"}` | `{"name":"John"}` |
| Single quotes | `{'a': 'b'}` | `{"a":"b"}` |
| Smart/curly quotes | `{"a": "b"}` | `{"a":"b"}` |
| Backtick quotes | `` {`a`: `b`} `` | `{"a":"b"}` |
| Trailing commas | `{"a": 1,}` | `{"a":1}` |
| Leading commas | `{,"a": 1}` | `{"a":1}` |
| Missing commas | `{"a": 1 "b": 2}` | `{"a":1,"b":2}` |
| Missing colons | `{"a" 1}` | `{"a":1}` |
| Missing brackets | `{"a": 1` | `{"a":1}` |
| Redundant brackets | `{"a": 1}}` | `{"a":1}` |
| Block comments | `{"a": 1 /* x */}` | `{"a":1}` |
| Line comments | `{"a": 1 // x}` | `{"a":1}` |
| Hash comments | `{"a": 1 # x}` | `{"a":1}` |
| JSONP | `callback({"a": 1});` | `{"a":1}` |
| Markdown fences | `` ```json{"a":1}``` `` | `{"a":1}` |
| Python True/False/None | `{a: True, b: None}` | `{"a":true,"b":null}` |
| JS undefined/NaN | `undefined` | `null` |
| String concatenation | `"a" + "b"` | `"ab"` |
| Ellipsis | `[1, 2, ...]` | `[1,2]` |
| Leading zero numbers | `0123` | `"0123"` |
| Truncated JSON | `{"a":` | `{"a":null}` |
| NDJSON | `{"a":1}\n{"b":2}` | `[{"a":1},{"b":2}]` |
| Regex literals | `/test/gi` | `"/test/gi"` |
| MongoDB types | `ObjectId("abc")` | `"abc"` |
| HTML entities | `"a &amp; b"` | `"a & b"` |
| `<think>` blocks | `<think>...</think> {}` | `{}` |
| Log prefixes | `[date] level: {}` | `{}` |

## Full API

### Static Facade

```php
use Teariot\JsonRepair\JsonRepair;

// Core
JsonRepair::fix(string $input, bool $beautify = false): string
JsonRepair::decode(string $input, bool $assoc = true, int $depth = 512, int $flags = 0): mixed
JsonRepair::tryFix(string $input, bool $beautify = false): string      // never throws
JsonRepair::needsRepair(string $input): bool

// Extract from text
JsonRepair::extractAndFix(string $raw, bool $beautify = false): string
JsonRepair::extractAndDecode(string $raw, ?array $template = null, bool $assoc = true): mixed

// Template
JsonRepair::fixWithTemplate(string $input, array $template): array

// Streaming (memory-efficient for large files)
JsonRepair::stream($source, int $chunkSize = 65536, bool $beautify = false): Generator
JsonRepair::streamCollect($source, int $chunkSize = 65536, bool $beautify = false): string
```

### Helper Functions

```php
use function Teariot\JsonRepair\json_repair;
use function Teariot\JsonRepair\json_repair_decode;
use function Teariot\JsonRepair\json_try_repair;
use function Teariot\JsonRepair\json_extract_and_fix;
use function Teariot\JsonRepair\json_extract_and_decode;
use function Teariot\JsonRepair\json_fix_with_template;
use function Teariot\JsonRepair\json_repair_stream;
```

## Streaming

Process large JSON files without loading everything into memory:

```php
$handle = fopen('large.json', 'r');
foreach (JsonRepair::stream($handle) as $chunk) {
    echo $chunk;
}
fclose($handle);

// Or collect into a string
$handle = fopen('large.json', 'r');
$json = JsonRepair::streamCollect($handle);
fclose($handle);
```

## Requirements

- PHP >= 8.0
- ext-json
- ext-mbstring
- ext-ctype

## Testing

```bash
composer install
vendor/bin/phpunit
```

## License

MIT
