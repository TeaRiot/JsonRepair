<?php

declare(strict_types=1);

namespace Teariot\JsonRepair\Tests;

use PHPUnit\Framework\TestCase;
use Teariot\JsonRepair\Streaming\ChunkedFixer;

class ChunkedFixerTest extends TestCase
{
    public function testResource(): void
    {
        $mem = fopen('php://memory', 'r+');
        fwrite($mem, '{"a":1,"b":2}');
        rewind($mem);
        $this->assertSame('{"a":1,"b":2}', (new ChunkedFixer())->collect($mem));
        fclose($mem);
    }

    public function testIterable(): void
    {
        $this->assertSame('{"a":1}', (new ChunkedFixer())->collect(['{"a":', '1}']));
    }

    public function testRepairs(): void
    {
        $this->assertSame(
            '{"a":1,"b":"hello"}',
            (new ChunkedFixer())->collect(['{a:', "1,b:'hello'}"])
        );
    }

    public function testYieldsChunks(): void
    {
        $mem = fopen('php://memory', 'r+');
        fwrite($mem, '{"a":1}');
        rewind($mem);
        $chunks = iterator_to_array((new ChunkedFixer())->stream($mem));
        fclose($mem);
        $this->assertNotEmpty($chunks);
        $this->assertSame('{"a":1}', implode('', $chunks));
    }

    public function testTrailingComma(): void
    {
        $this->assertSame('[1,2,3]', (new ChunkedFixer())->collect(['[1,2,3,]']));
    }

    public function testComments(): void
    {
        $this->assertSame('{"a":1}', (new ChunkedFixer())->collect(['{"a":1/* x */}']));
    }

    public function testSingleQuotes(): void
    {
        $this->assertSame('{"a":"hello"}', (new ChunkedFixer())->collect(["{'a':'hello'}"]));
    }

    public function testEmptyStream(): void
    {
        $mem = fopen('php://memory', 'r+');
        rewind($mem);
        $chunks = iterator_to_array((new ChunkedFixer())->stream($mem));
        fclose($mem);
        $this->assertEmpty($chunks);
    }

    public function testWhitespaceOnly(): void
    {
        $this->assertEmpty(iterator_to_array((new ChunkedFixer())->stream(['  ', ' '])));
    }

    public function testBeautifyOption(): void
    {
        $this->assertSame('{"a":1}', (new ChunkedFixer(65536, true))->collect(['{"a":1}']));
    }

    public function testLargeDocument(): void
    {
        $items = [];
        for ($i = 0; $i < 100; $i++) {
            $items[] = '{"id":' . $i . '}';
        }
        $json = '[' . implode(',', $items) . ']';
        $mem  = fopen('php://memory', 'r+');
        fwrite($mem, $json);
        rewind($mem);
        $result = (new ChunkedFixer(256))->collect($mem);
        fclose($mem);
        $this->assertCount(100, json_decode($result, true));
    }

    public function testDeepNesting(): void
    {
        $this->assertSame(
            '{"a":{"b":{"c":[1,2,3]}}}',
            (new ChunkedFixer())->collect(['{"a":{"b":{"c":[1,2,3]}}}'])
        );
    }

    public function testSmallChunks(): void
    {
        $mem = fopen('php://memory', 'r+');
        fwrite($mem, '{"hello":"world"}');
        rewind($mem);
        $this->assertSame('{"hello":"world"}', (new ChunkedFixer(4))->collect($mem));
        fclose($mem);
    }
}
