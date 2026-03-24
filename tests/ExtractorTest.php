<?php

declare(strict_types=1);

namespace Teariot\JsonRepair\Tests;

use PHPUnit\Framework\TestCase;
use Teariot\JsonRepair\Extractor\JsonExtractor;

class ExtractorTest extends TestCase
{
    private JsonExtractor $e;

    protected function setUp(): void
    {
        $this->e = new JsonExtractor();
    }

    // ── plain JSON ──────────────────────────────────────────────────────

    public function testPlainObject(): void
    {
        $this->assertSame('{"a":1}', $this->e->extract('{"a":1}'));
    }

    public function testPlainArray(): void
    {
        $this->assertSame('[1,2,3]', $this->e->extract('[1,2,3]'));
    }

    // ── text before / after ─────────────────────────────────────────────

    public function testTextBefore(): void
    {
        $this->assertSame('{"a":1}', $this->e->extract('Some text before {"a":1}'));
    }

    public function testTextAfter(): void
    {
        $this->assertSame('{"a":1}', $this->e->extract('{"a":1} and some text after'));
    }

    public function testTextBoth(): void
    {
        $this->assertSame('{"a":1}', $this->e->extract('prefix {"a":1} suffix'));
    }

    public function testLongTextBefore(): void
    {
        $input = "I analyzed the data and here is what I found:\n\n{\"result\": true}";
        $this->assertSame('{"result": true}', $this->e->extract($input));
    }

    public function testLLMRefusalAfterJson(): void
    {
        $input = '{"mentioned": false, "tone": "non"} I\'m sorry, but I cannot assist with that.';
        $this->assertSame('{"mentioned": false, "tone": "non"}', $this->e->extract($input));
    }

    // ── <think> blocks ──────────────────────────────────────────────────

    public function testThinkBlock(): void
    {
        $input = '<think>Some reasoning.</think>  {"a": true}';
        $this->assertSame('{"a": true}', $this->e->extract($input));
    }

    public function testThinkBlockMultiline(): void
    {
        $input = "<think>Line 1\nLine 2\nLine 3</think>\n{\"a\":1}";
        $this->assertSame('{"a":1}', $this->e->extract($input));
    }

    public function testUnclosedThinkBlock(): void
    {
        $this->assertNull($this->e->extract('<think>reasoning... {"a":1}'));
    }

    // ── log prefixes ────────────────────────────────────────────────────

    public function testLogPrefix(): void
    {
        $input = '[2026-03-24 13:17:52] production.WARNING: JSON error: {"a":1}';
        $this->assertSame('{"a":1}', $this->e->extract($input));
    }

    public function testLogPrefixLong(): void
    {
        $input = '[2026-03-23 00:17:50] production.WARNING: Regex-починка не помогла, отправляем в ИИ: {"mentioned": false}';
        $this->assertSame('{"mentioned": false}', $this->e->extract($input));
    }

    // ── markdown fences ─────────────────────────────────────────────────

    public function testMarkdownFence(): void
    {
        $input = "```json\n{\"a\":1}\n```";
        $this->assertSame('{"a":1}', $this->e->extract($input));
    }

    public function testMarkdownFencePlain(): void
    {
        $input = "```\n{\"a\":1}\n```";
        $this->assertSame('{"a":1}', $this->e->extract($input));
    }

    public function testMarkdownFenceWithTextAround(): void
    {
        $input = "Here is the result:\n```json\n{\"a\":1}\n```\nDone.";
        $this->assertSame('{"a":1}', $this->e->extract($input));
    }

    // ── nested structures ───────────────────────────────────────────────

    public function testNestedObject(): void
    {
        $input = 'text {"a": {"b": {"c": 1}}} more text';
        $this->assertSame('{"a": {"b": {"c": 1}}}', $this->e->extract($input));
    }

    public function testJsonWithBracesInStrings(): void
    {
        $input = 'prefix {"text": "value with {braces}"} suffix';
        $this->assertSame('{"text": "value with {braces}"}', $this->e->extract($input));
    }

    public function testSingleQuotedStringsWithBraces(): void
    {
        $input = "prefix {'text': 'value {x}'} suffix";
        $this->assertSame("{'text': 'value {x}'}", $this->e->extract($input));
    }

    // ── truncated JSON ──────────────────────────────────────────────────

    public function testTruncatedObject(): void
    {
        $input = 'prefix {"mentioned": false, "tone": "non';
        $result = $this->e->extract($input);
        $this->assertNotNull($result);
        $this->assertStringStartsWith('{"mentioned"', $result);
    }

    public function testTruncatedArray(): void
    {
        $input = 'data: [1, 2, 3';
        $result = $this->e->extract($input);
        $this->assertNotNull($result);
        $this->assertStringStartsWith('[1', $result);
    }

    // ── no JSON ─────────────────────────────────────────────────────────

    public function testNoJson(): void
    {
        $this->assertNull($this->e->extract('no json here'));
        $this->assertNull($this->e->extract(''));
        $this->assertNull($this->e->extract('   '));
    }

    // ── extractAll ──────────────────────────────────────────────────────

    public function testExtractAllSingle(): void
    {
        $results = $this->e->extractAll('text {"a":1} more');
        $this->assertCount(1, $results);
        $this->assertSame('{"a":1}', $results[0]);
    }

    public function testExtractAllMultiple(): void
    {
        $input = '{"a":1} some text {"b":2} end';
        $results = $this->e->extractAll($input);
        $this->assertCount(2, $results);
        $this->assertSame('{"a":1}', $results[0]);
        $this->assertSame('{"b":2}', $results[1]);
    }

    public function testExtractAllMixed(): void
    {
        $input = 'Result: {"x":1} and [1,2,3] done';
        $results = $this->e->extractAll($input);
        $this->assertCount(2, $results);
        $this->assertSame('{"x":1}', $results[0]);
        $this->assertSame('[1,2,3]', $results[1]);
    }

    public function testExtractAllEmpty(): void
    {
        $this->assertSame([], $this->e->extractAll('no json'));
    }

    // ── real-world cases from logs ──────────────────────────────────────

    public function testRealWorldLogWithBrands(): void
    {
        $input = '[2026-03-23 00:17:50] production.WARNING: Regex-починка не помогла, отправляем в ИИ: {   "mentioned": false,   "occurrences": [],   "position": null,   "tone": "non",   "comment": null,   "other_brands": ["Brand1", "Brand2"] }';
        $result = $this->e->extract($input);
        $this->assertNotNull($result);
        $decoded = json_decode($result, true);
        $this->assertSame(false, $decoded['mentioned']);
        $this->assertSame(['Brand1', 'Brand2'], $decoded['other_brands']);
    }

    public function testRealWorldThinkThenJson(): void
    {
        $input = '<think>**Identifying Brand Mentions**  I need to pinpoint mentions.</think>  {   "mentioned": false,   "occurrences": [],   "position": null,   "tone": "non",   "comment": null,   "other_brands": ["Test"] }';
        $result = $this->e->extract($input);
        $this->assertNotNull($result);
        $decoded = json_decode($result, true);
        $this->assertSame(false, $decoded['mentioned']);
    }

    public function testRealWorldPlainTextThenJson(): void
    {
        $input = "Based on my analysis of the text, here is the result:\n\n{\"mentioned\": true, \"occurrences\": [\"Brand\"], \"position\": 1, \"tone\": \"positive\", \"comment\": \"Good review\", \"other_brands\": []}";
        $result = $this->e->extract($input);
        $this->assertNotNull($result);
        $decoded = json_decode($result, true);
        $this->assertSame(true, $decoded['mentioned']);
        $this->assertSame('positive', $decoded['tone']);
    }

    public function testRealWorldJsonThenRefusal(): void
    {
        $input = '{   "mentioned": false,   "occurrences": [],   "position": null,   "tone": "non",   "I\'m sorry, but I cannot assist with that request.';
        $result = $this->e->extract($input);
        $this->assertNotNull($result);
        $this->assertStringStartsWith('{', $result);
    }
}
