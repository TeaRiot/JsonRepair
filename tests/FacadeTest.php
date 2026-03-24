<?php

declare(strict_types=1);

namespace Teariot\JsonRepair\Tests;

use PHPUnit\Framework\TestCase;
use Teariot\JsonRepair\Exceptions\MalformedJsonException;
use Teariot\JsonRepair\JsonRepair;

class FacadeTest extends TestCase
{
    public function testFix(): void
    {
        $this->assertSame('{"a":1}', JsonRepair::fix('{a:1}'));
    }

    public function testFixBeautify(): void
    {
        $result = JsonRepair::fix('{"m":"He said "hi" to me"}', true);
        $this->assertStringContainsString("\u{201D}", $result);
    }

    public function testDecode(): void
    {
        $data = JsonRepair::decode("{a: 'hello', b: 42}");
        $this->assertSame('hello', $data['a']);
        $this->assertSame(42, $data['b']);
    }

    public function testDecodeAssocFalse(): void
    {
        $data = JsonRepair::decode("{a: 1}", false);
        $this->assertIsObject($data);
        $this->assertSame(1, $data->a);
    }

    public function testTryFixSuccess(): void
    {
        $this->assertSame('{"a":1}', JsonRepair::tryFix('{a:1}'));
    }

    public function testTryFixFailure(): void
    {
        $this->assertSame('', JsonRepair::tryFix(''));
    }

    public function testNeedsRepairTrue(): void
    {
        $this->assertTrue(JsonRepair::needsRepair('{a:1}'));
    }

    public function testNeedsRepairFalse(): void
    {
        $this->assertFalse(JsonRepair::needsRepair('{"a":1}'));
    }

    public function testNeedsRepairHopeless(): void
    {
        $this->assertFalse(JsonRepair::needsRepair(''));
    }

    public function testStream(): void
    {
        $chunks = ['{"a":', '1}'];
        $result = JsonRepair::streamCollect($chunks);
        $this->assertSame('{"a":1}', $result);
    }

    public function testStreamResource(): void
    {
        $mem = fopen('php://memory', 'r+');
        fwrite($mem, '{"x":42}');
        rewind($mem);
        $result = JsonRepair::streamCollect($mem);
        fclose($mem);
        $this->assertSame('{"x":42}', $result);
    }

    public function testStreamGenerator(): void
    {
        $chunks = [];
        foreach (JsonRepair::stream(['[1,2,3,]']) as $piece) {
            $chunks[] = $piece;
        }
        $this->assertSame('[1,2,3]', implode('', $chunks));
    }
}
