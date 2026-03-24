<?php

declare(strict_types=1);

namespace Teariot\JsonRepair\Tests;

use PHPUnit\Framework\TestCase;
use Teariot\JsonRepair\Exceptions\MalformedJsonException;

use function Teariot\JsonRepair\json_repair;
use function Teariot\JsonRepair\json_repair_decode;
use function Teariot\JsonRepair\json_try_repair;
use function Teariot\JsonRepair\json_extract_and_fix;
use function Teariot\JsonRepair\json_extract_and_decode;
use function Teariot\JsonRepair\json_fix_with_template;
use function Teariot\JsonRepair\json_repair_stream;

class HelpersTest extends TestCase
{
    public function testJsonRepair(): void
    {
        $this->assertSame('{"a":1}', json_repair('{a:1}'));
    }

    public function testJsonRepairBeautify(): void
    {
        $result = json_repair('{"a":"He said "hi" to me"}', true);
        $this->assertStringContainsString("\u{201D}", $result);
    }

    public function testJsonRepairThrows(): void
    {
        $this->expectException(MalformedJsonException::class);
        json_repair('');
    }

    public function testJsonRepairDecode(): void
    {
        $data = json_repair_decode('{x: 42}');
        $this->assertSame(42, $data['x']);
    }

    public function testJsonTryRepair(): void
    {
        $this->assertSame('{"a":1}', json_try_repair('{a:1}'));
        $this->assertSame('', json_try_repair(''));
    }

    public function testJsonExtractAndFix(): void
    {
        $this->assertSame('{"a":1}', json_extract_and_fix('text {"a":1} end'));
    }

    public function testJsonExtractAndFixFromThink(): void
    {
        $result = json_extract_and_fix('<think>reasoning</think> {"ok":true}');
        $this->assertSame('{"ok":true}', $result);
    }

    public function testJsonExtractAndDecode(): void
    {
        $data = json_extract_and_decode('prefix {"x":42}');
        $this->assertSame(42, $data['x']);
    }

    public function testJsonExtractAndDecodeWithTemplate(): void
    {
        $data = json_extract_and_decode('text {"x":1}', ['x' => 0, 'y' => null]);
        $this->assertSame(1, $data['x']);
        $this->assertNull($data['y']);
    }

    public function testJsonFixWithTemplate(): void
    {
        $data = json_fix_with_template('{a:1}', ['a' => 0, 'b' => 'default']);
        $this->assertSame(1, $data['a']);
        $this->assertSame('default', $data['b']);
    }

    public function testJsonRepairStream(): void
    {
        $result = '';
        foreach (json_repair_stream(['{"a":1}']) as $chunk) {
            $result .= $chunk;
        }
        $this->assertSame('{"a":1}', $result);
    }
}
