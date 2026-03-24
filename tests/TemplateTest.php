<?php

declare(strict_types=1);

namespace Teariot\JsonRepair\Tests;

use PHPUnit\Framework\TestCase;
use Teariot\JsonRepair\Template\TemplateApplier;

class TemplateTest extends TestCase
{
    private TemplateApplier $applier;

    protected function setUp(): void
    {
        $this->applier = new TemplateApplier();
    }

    public function testFillMissingKeys(): void
    {
        $data     = ['mentioned' => true, 'tone' => 'negative'];
        $template = [
            'mentioned'    => false,
            'occurrences'  => [],
            'position'     => null,
            'tone'         => 'non',
            'comment'      => null,
            'other_brands' => [],
        ];

        $result = $this->applier->apply($data, $template);

        $this->assertSame(true, $result['mentioned']);
        $this->assertSame('negative', $result['tone']);
        $this->assertSame([], $result['occurrences']);
        $this->assertNull($result['position']);
        $this->assertNull($result['comment']);
        $this->assertSame([], $result['other_brands']);
    }

    public function testDoNotOverrideExisting(): void
    {
        $data     = ['a' => 'hello', 'b' => [1, 2]];
        $template = ['a' => 'default', 'b' => [], 'c' => 'new'];

        $result = $this->applier->apply($data, $template);

        $this->assertSame('hello', $result['a']);
        $this->assertSame([1, 2], $result['b']);
        $this->assertSame('new', $result['c']);
    }

    public function testRecursiveNested(): void
    {
        $data     = ['meta' => ['name' => 'test']];
        $template = ['meta' => ['name' => '', 'version' => '1.0', 'active' => true]];

        $result = $this->applier->apply($data, $template);

        $this->assertSame('test', $result['meta']['name']);
        $this->assertSame('1.0', $result['meta']['version']);
        $this->assertSame(true, $result['meta']['active']);
    }

    public function testEmptyData(): void
    {
        $template = ['a' => 1, 'b' => 'x'];
        $result   = $this->applier->apply([], $template);

        $this->assertSame(1, $result['a']);
        $this->assertSame('x', $result['b']);
    }

    public function testEmptyTemplate(): void
    {
        $data   = ['a' => 1];
        $result = $this->applier->apply($data, []);

        $this->assertSame(['a' => 1], $result);
    }

    public function testApplyFromJson(): void
    {
        $data = ['tone' => 'positive'];
        $json = '{"mentioned": false, "occurrences": [], "tone": "non", "comment": null}';

        $result = $this->applier->applyFromJson($data, $json);

        $this->assertSame('positive', $result['tone']);
        $this->assertSame(false, $result['mentioned']);
        $this->assertSame([], $result['occurrences']);
        $this->assertNull($result['comment']);
    }

    public function testApplyFromInvalidJson(): void
    {
        $data   = ['a' => 1];
        $result = $this->applier->applyFromJson($data, 'not json');

        $this->assertSame(['a' => 1], $result);
    }

    public function testNullValuesPreserved(): void
    {
        $data     = ['a' => null];
        $template = ['a' => 'default', 'b' => null];

        $result = $this->applier->apply($data, $template);

        $this->assertNull($result['a']);
        $this->assertNull($result['b']);
    }

    public function testArrayValuesNotMergedRecursively(): void
    {
        $data     = ['tags' => ['php']];
        $template = ['tags' => ['default']];

        $result = $this->applier->apply($data, $template);

        // Sequential arrays are NOT merged — data wins
        $this->assertSame(['php'], $result['tags']);
    }
}
