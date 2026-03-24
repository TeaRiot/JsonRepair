<?php

declare(strict_types=1);

namespace Teariot\JsonRepair\Tests;

use PHPUnit\Framework\TestCase;
use Teariot\JsonRepair\Exceptions\MalformedJsonException;
use Teariot\JsonRepair\JsonRepair;

class IntegrationTest extends TestCase
{
    private const TEMPLATE = [
        'mentioned'    => false,
        'occurrences'  => [],
        'position'     => null,
        'tone'         => 'non',
        'comment'      => null,
        'other_brands' => [],
    ];

    public function testExtractAndFixFromThinkBlock(): void
    {
        $raw = '<think>**Analyzing** some reasoning here.</think>  {"mentioned": true, "occurrences": ["Brand"], "tone": "negative"}';
        $json = JsonRepair::extractAndFix($raw);
        $decoded = json_decode($json, true);
        $this->assertSame(true, $decoded['mentioned']);
        $this->assertSame('negative', $decoded['tone']);
    }

    public function testExtractAndDecodeWithTemplate(): void
    {
        $raw = '<think>Some thinking</think> {"mentioned": true, "tone": "negative"}';
        $data = JsonRepair::extractAndDecode($raw, self::TEMPLATE);

        $this->assertSame(true, $data['mentioned']);
        $this->assertSame('negative', $data['tone']);
        $this->assertSame([], $data['occurrences']);
        $this->assertNull($data['position']);
        $this->assertNull($data['comment']);
        $this->assertSame([], $data['other_brands']);
    }

    public function testExtractAndFixFromLog(): void
    {
        $raw = '[2026-03-23 00:17:50] production.WARNING: Regex-починка не помогла: {"mentioned": false, "tone": "non"}';
        $json = JsonRepair::extractAndFix($raw);
        $decoded = json_decode($json, true);
        $this->assertSame(false, $decoded['mentioned']);
    }

    public function testExtractBrokenJsonWithMissingQuote(): void
    {
        $raw = 'prefix {   "mentioned": false,   "other_brands": ["Brand1", "Brand2", Brand3"] }';
        $json = JsonRepair::extractAndFix($raw);
        $decoded = json_decode($json, true);
        $this->assertNotNull($decoded);
        $this->assertSame(false, $decoded['mentioned']);
    }

    public function testExtractTruncatedJsonWithTemplate(): void
    {
        $raw = '{"mentioned": false, "tone": "non", "comment": null,';
        $data = JsonRepair::fixWithTemplate($raw, self::TEMPLATE);

        $this->assertSame(false, $data['mentioned']);
        $this->assertSame('non', $data['tone']);
        $this->assertNull($data['comment']);
        $this->assertSame([], $data['occurrences']);
        $this->assertNull($data['position']);
        $this->assertSame([], $data['other_brands']);
    }

    public function testFixWithTemplateNoMissingKeys(): void
    {
        $input = '{"mentioned": true, "occurrences": ["X"], "position": 1, "tone": "pos", "comment": "ok", "other_brands": ["Y"]}';
        $data = JsonRepair::fixWithTemplate($input, self::TEMPLATE);

        $this->assertSame(true, $data['mentioned']);
        $this->assertSame(['X'], $data['occurrences']);
        $this->assertSame(1, $data['position']);
        $this->assertSame('pos', $data['tone']);
        $this->assertSame('ok', $data['comment']);
        $this->assertSame(['Y'], $data['other_brands']);
    }

    public function testExtractAndFixNoJsonThrows(): void
    {
        $this->expectException(MalformedJsonException::class);
        JsonRepair::extractAndFix('no json here at all');
    }

    public function testRealWorldLLMRefusal(): void
    {
        $raw = '{   "mentioned": false,   "occurrences": [],   "position": null,   "tone": "non",   "I\'m sorry, but I cannot assist with that request.';
        $data = JsonRepair::fixWithTemplate($raw, self::TEMPLATE);

        $this->assertSame(false, $data['mentioned']);
        $this->assertSame([], $data['occurrences']);
        $this->assertNull($data['position']);
        $this->assertSame('non', $data['tone']);
        // Template fills missing keys
        $this->assertNull($data['comment']);
        $this->assertSame([], $data['other_brands']);
    }

    public function testRealWorldMultilineThink(): void
    {
        $raw = "<think>**Identifying Brand Mentions**  I need to pinpoint mentions of brand names.\nSome more reasoning here.\n**Evaluating tone**  The tone seems neutral.</think>  {   \"mentioned\": false,   \"occurrences\": [],   \"position\": null,   \"tone\": \"non\",   \"comment\": null,   \"other_brands\": [\"TestBrand\"] }";
        $data = JsonRepair::extractAndDecode($raw, self::TEMPLATE);

        $this->assertSame(false, $data['mentioned']);
        $this->assertSame(['TestBrand'], $data['other_brands']);
    }
}
