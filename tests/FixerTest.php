<?php

declare(strict_types=1);

namespace Teariot\JsonRepair\Tests;

use PHPUnit\Framework\TestCase;
use Teariot\JsonRepair\Exceptions\MalformedJsonException;
use Teariot\JsonRepair\Fixer\JsonFixer;

/**
 * Covers every repair strategy of JsonFixer.
 */
class FixerTest extends TestCase
{
    private JsonFixer $fixer;

    protected function setUp(): void
    {
        $this->fixer = new JsonFixer();
    }

    private function fix(string $in, bool $beautify = false): string
    {
        return $this->fixer->fix($in, $beautify);
    }

    // ════════════════════════════════════════════════════════════════════
    //  Pass-through: valid JSON stays intact
    // ════════════════════════════════════════════════════════════════════

    /** @dataProvider validJsonProvider */
    public function testValidJsonPassthrough(string $json): void
    {
        $this->assertSame($json, $this->fix($json));
    }

    public function validJsonProvider(): iterable
    {
        yield 'empty object'     => ['{}'];
        yield 'empty array'      => ['[]'];
        yield 'simple object'    => ['{"a":1}'];
        yield 'nested object'    => ['{"a":{"b":{"c":3}}}'];
        yield 'simple array'     => ['[1,2,3]'];
        yield 'nested arrays'    => ['[[1],[2]]'];
        yield 'string value'     => ['"hello"'];
        yield 'integer'          => ['42'];
        yield 'negative'         => ['-3.14'];
        yield 'exponent lower'   => ['2.3e5'];
        yield 'exponent upper'   => ['2.3E5'];
        yield 'exponent plus'    => ['2.3e+5'];
        yield 'exponent minus'   => ['2.3e-5'];
        yield 'true'             => ['true'];
        yield 'false'            => ['false'];
        yield 'null'             => ['null'];
        yield 'whitespace'       => ['  { "a" : 1 }  '];
        yield 'escaped newline'  => ['"hello\\nworld"'];
        yield 'escaped tab'      => ['"hello\\tworld"'];
        yield 'escaped bs'       => ['"hello\\\\"'];
        yield 'escaped slash'    => ['"hello\\/"'];
        yield 'escaped dquote'   => ['"hello\\"world"'];
        yield 'unicode escape'   => ['"hello\\u00e9"'];
    }

    // ════════════════════════════════════════════════════════════════════
    //  Quote repairs
    // ════════════════════════════════════════════════════════════════════

    public function testUnquotedKeys(): void
    {
        $this->assertSame('{"a":1}', $this->fix('{a:1}'));
        $this->assertSame('{"a":1,"b":2}', $this->fix('{a:1,b:2}'));
    }

    public function testMissingEndQuote(): void
    {
        $this->assertSame('["hello"]', $this->fix('["hello]'));
        $this->assertSame('["hello"]', $this->fix('["hello'));
    }

    public function testSingleQuotes(): void
    {
        $this->assertSame('{"a":"hello"}', $this->fix("{'a':'hello'}"));
    }

    public function testSmartQuotes(): void
    {
        $this->assertSame(
            '{"a":"hello"}',
            $this->fix("{\u{201C}a\u{201D}:\u{201C}hello\u{201D}}")
        );
    }

    public function testBacktickQuotes(): void
    {
        $this->assertSame('{"a":"hello"}', $this->fix('{`a`:`hello`}'));
    }

    public function testMixedQuotes(): void
    {
        $this->assertSame(
            '{"a":"hello","b":"world"}',
            $this->fix("{\"a\":'hello','b':\"world\"}")
        );
    }

    // ════════════════════════════════════════════════════════════════════
    //  Comma repairs
    // ════════════════════════════════════════════════════════════════════

    public function testTrailingCommaObject(): void
    {
        $this->assertSame('{"a":1}', $this->fix('{"a":1,}'));
    }

    public function testTrailingCommaArray(): void
    {
        $this->assertSame('[1,2,3]', $this->fix('[1,2,3,]'));
    }

    public function testTrailingCommaRoot(): void
    {
        $this->assertSame('{"a":1}', $this->fix('{"a":1},'));
    }

    public function testLeadingCommaObject(): void
    {
        $this->assertSame('{"a":1}', $this->fix('{,"a":1}'));
    }

    public function testLeadingCommaArray(): void
    {
        $this->assertSame('[1,2,3]', $this->fix('[,1,2,3]'));
    }

    public function testMissingCommaObject(): void
    {
        $this->assertSame('{"a":1, "b":2}', $this->fix('{"a":1 "b":2}'));
    }

    public function testMissingCommaArray(): void
    {
        $this->assertSame('[1, 2, 3]', $this->fix('[1 2 3]'));
    }

    public function testMissingCommaStrings(): void
    {
        $this->assertSame('["a", "b"]', $this->fix('["a" "b"]'));
    }

    // ════════════════════════════════════════════════════════════════════
    //  Colon repairs
    // ════════════════════════════════════════════════════════════════════

    public function testMissingColon(): void
    {
        $this->assertSame('{"a": 1}', $this->fix('{"a" 1}'));
    }

    // ════════════════════════════════════════════════════════════════════
    //  Bracket repairs
    // ════════════════════════════════════════════════════════════════════

    public function testMissingCloseBrace(): void
    {
        $this->assertSame('{"a":1}', $this->fix('{"a":1'));
    }

    public function testMissingCloseBracket(): void
    {
        $this->assertSame('[1,2,3]', $this->fix('[1,2,3'));
    }

    public function testMissingNestedCloseBrace(): void
    {
        $this->assertSame('{"a":{"b":2}}', $this->fix('{"a":{"b":2}'));
    }

    public function testRedundantBraces(): void
    {
        $this->assertSame('{"a":1}', $this->fix('{"a":1}}'));
        $this->assertSame('[1,2]', $this->fix('[1,2]]'));
    }

    // ════════════════════════════════════════════════════════════════════
    //  Comments
    // ════════════════════════════════════════════════════════════════════

    public function testBlockComment(): void
    {
        $this->assertSame('{"a":1}', $this->fix('{"a":1/* comment */}'));
    }

    public function testLineComment(): void
    {
        $this->assertSame("{\"a\":1\n}", $this->fix("{\"a\":1// comment\n}"));
    }

    public function testHashComment(): void
    {
        $this->assertSame("{\"a\":1 \n}", $this->fix("{\"a\":1 # comment\n}"));
    }

    public function testMultipleComments(): void
    {
        $this->assertSame(
            "{\n\"a\":1\n}",
            $this->fix("/* start */{\n/* middle */\"a\":1\n/* end */}")
        );
    }

    // ════════════════════════════════════════════════════════════════════
    //  JSONP
    // ════════════════════════════════════════════════════════════════════

    public function testJsonpCallback(): void
    {
        $this->assertSame('{}', $this->fix('callback({})'));
        $this->assertSame('{}', $this->fix('callback_123({})'));
    }

    public function testJsonpSemicolon(): void
    {
        $this->assertSame('{}', $this->fix('callback({});'));
    }

    // ════════════════════════════════════════════════════════════════════
    //  Markdown fences
    // ════════════════════════════════════════════════════════════════════

    public function testMarkdownFenceJson(): void
    {
        $this->assertSame('{"a":1}', $this->fix("```json\n{\"a\":1}\n```"));
    }

    public function testMarkdownFencePlain(): void
    {
        $this->assertSame('{"a":1}', $this->fix("```\n{\"a\":1}\n```"));
    }

    // ════════════════════════════════════════════════════════════════════
    //  Python constants
    // ════════════════════════════════════════════════════════════════════

    public function testPythonTrue(): void
    {
        $this->assertSame('true', $this->fix('True'));
    }

    public function testPythonFalse(): void
    {
        $this->assertSame('false', $this->fix('False'));
    }

    public function testPythonNone(): void
    {
        $this->assertSame('null', $this->fix('None'));
    }

    public function testPythonMixed(): void
    {
        $this->assertSame(
            '{"a":true,"b":false,"c":null}',
            $this->fix('{a:True,b:False,c:None}')
        );
    }

    // ════════════════════════════════════════════════════════════════════
    //  JS specials
    // ════════════════════════════════════════════════════════════════════

    public function testUndefined(): void
    {
        $this->assertSame('null', $this->fix('undefined'));
    }

    public function testNaN(): void
    {
        $this->assertSame('"NaN"', $this->fix('NaN'));
    }

    public function testInfinity(): void
    {
        $this->assertSame('"Infinity"', $this->fix('Infinity'));
    }

    // ════════════════════════════════════════════════════════════════════
    //  Bare strings
    // ════════════════════════════════════════════════════════════════════

    public function testBareValue(): void
    {
        $this->assertSame('{"a":"hello"}', $this->fix('{"a":hello}'));
    }

    public function testBareKey(): void
    {
        $this->assertSame('{"hello":"world"}', $this->fix('{hello:"world"}'));
    }

    // ════════════════════════════════════════════════════════════════════
    //  String concatenation
    // ════════════════════════════════════════════════════════════════════

    public function testConcat(): void
    {
        $this->assertSame('"helloworld"', $this->fix('"hello" + "world"'));
    }

    public function testConcatSpaced(): void
    {
        $this->assertSame('"hello world"', $this->fix('"hello " + "world"'));
    }

    // ════════════════════════════════════════════════════════════════════
    //  Ellipsis
    // ════════════════════════════════════════════════════════════════════

    public function testEllipsisArray(): void
    {
        $this->assertSame('[1,2,3]', $this->fix('[1,2,3,...]'));
    }

    public function testEllipsisObject(): void
    {
        $this->assertSame('{"a":1}', $this->fix('{"a":1,...}'));
    }

    // ════════════════════════════════════════════════════════════════════
    //  Truncated JSON
    // ════════════════════════════════════════════════════════════════════

    public function testTruncatedObjectValue(): void
    {
        $this->assertSame('{"a":null}', $this->fix('{"a":'));
    }

    public function testTruncatedObjectKey(): void
    {
        $this->assertSame('{"a":null}', $this->fix('{"a"'));
    }

    public function testTruncatedArray(): void
    {
        $this->assertSame('[1,2]', $this->fix('[1,2'));
    }

    public function testTruncatedString(): void
    {
        $this->assertSame('["hello"]', $this->fix('["hello'));
    }

    // ════════════════════════════════════════════════════════════════════
    //  Numbers
    // ════════════════════════════════════════════════════════════════════

    public function testLeadingZero(): void
    {
        $this->assertSame('"0123"', $this->fix('0123'));
    }

    public function testLeadingZeroInObject(): void
    {
        $this->assertSame('{"a":"0123"}', $this->fix('{"a":0123}'));
    }

    public function testTrailingDot(): void
    {
        $this->assertSame('2.0', $this->fix('2.'));
    }

    public function testTrailingE(): void
    {
        $this->assertSame('2e0', $this->fix('2e'));
    }

    public function testLoneMinus(): void
    {
        $this->assertSame('-0', $this->fix('-'));
    }

    // ════════════════════════════════════════════════════════════════════
    //  NDJSON
    // ════════════════════════════════════════════════════════════════════

    public function testNdjson(): void
    {
        $result = $this->fix("{\"a\":1}\n{\"b\":2}");
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);
    }

    public function testNdjsonTrailingNewline(): void
    {
        $result = $this->fix("{\"a\":1}\n{\"b\":2}\n");
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);
    }

    // ════════════════════════════════════════════════════════════════════
    //  MongoDB wrappers
    // ════════════════════════════════════════════════════════════════════

    public function testObjectId(): void
    {
        $this->assertSame('{"_id":"123"}', $this->fix('{"_id":ObjectId("123")}'));
    }

    public function testISODate(): void
    {
        $this->assertSame('{"d":"2023-01-01"}', $this->fix('{"d":ISODate("2023-01-01")}'));
    }

    public function testNumberLong(): void
    {
        $this->assertSame('{"n":42}', $this->fix('{"n":NumberLong(42)}'));
    }

    // ════════════════════════════════════════════════════════════════════
    //  Regex
    // ════════════════════════════════════════════════════════════════════

    public function testRegex(): void
    {
        $this->assertSame('"/test/gi"', $this->fix('/test/gi'));
    }

    public function testRegexNoFlags(): void
    {
        $this->assertSame('"/test/"', $this->fix('/test/'));
    }

    // ════════════════════════════════════════════════════════════════════
    //  URLs
    // ════════════════════════════════════════════════════════════════════

    public function testQuotedUrl(): void
    {
        $this->assertSame(
            '{"url":"https://example.com"}',
            $this->fix('{"url":"https://example.com"}')
        );
    }

    public function testBareUrl(): void
    {
        $this->assertSame(
            '{"url":"https://example.com"}',
            $this->fix('{url:https://example.com}')
        );
    }

    // ════════════════════════════════════════════════════════════════════
    //  HTML entities
    // ════════════════════════════════════════════════════════════════════

    public function testHtmlAmp(): void
    {
        $this->assertSame('{"t":"a & b"}', $this->fix('{"t":"a &amp; b"}'));
    }

    public function testHtmlLtGt(): void
    {
        $this->assertSame('{"t":"<>"}', $this->fix('{"t":"&lt;&gt;"}'));
    }

    public function testHtmlNumericEntity(): void
    {
        $result = $this->fix('{"t":"&#65;"}'); // &#65; = 'A'
        $this->assertSame('{"t":"A"}', $result);
    }

    // ════════════════════════════════════════════════════════════════════
    //  Beautify mode
    // ════════════════════════════════════════════════════════════════════

    public function testBeautify(): void
    {
        $result = $this->fix('{"msg": "He said "hello" to me"}', true);
        $this->assertStringContainsString("\u{201D}", $result);
    }

    // ════════════════════════════════════════════════════════════════════
    //  Special whitespace
    // ════════════════════════════════════════════════════════════════════

    public function testNonBreakingSpace(): void
    {
        $this->assertSame('{ "a":1}', $this->fix("{\xC2\xA0\"a\":1}"));
    }

    // ════════════════════════════════════════════════════════════════════
    //  Error cases
    // ════════════════════════════════════════════════════════════════════

    public function testEmptyInput(): void
    {
        $this->expectException(MalformedJsonException::class);
        $this->fix('');
    }

    public function testWhitespaceOnly(): void
    {
        $this->expectException(MalformedJsonException::class);
        $this->fix('   ');
    }

    public function testExceptionCursor(): void
    {
        try {
            $this->fix('');
        } catch (MalformedJsonException $e) {
            $this->assertSame(0, $e->getCursor());
            return;
        }
        $this->fail('Expected MalformedJsonException');
    }

    // ════════════════════════════════════════════════════════════════════
    //  Complex / mixed
    // ════════════════════════════════════════════════════════════════════

    public function testComplexNested(): void
    {
        $this->assertSame(
            '{"a":[1,{"b":"hello","c":[true,false]},3]}',
            $this->fix('{a:[1,{b:"hello",c:[true,false]},3]}')
        );
    }

    public function testMultipleIssuesAtOnce(): void
    {
        $this->assertSame(
            '{"a":"hello","b":true,"c":[1,2,3]}',
            $this->fix("{a:'hello',b:True,c:[1,2,3,]}")
        );
    }

    public function testDeepNesting(): void
    {
        $this->assertSame(
            '{"a":{"b":{"c":{"d":1}}}}',
            $this->fix('{a:{b:{c:{d:1}}}}')
        );
    }

    public function testReusable(): void
    {
        $this->assertSame('{"a":1}', $this->fix('{"a":1}'));
        $this->assertSame('[1,2]', $this->fix('[1,2]'));
        $this->assertSame('"ok"', $this->fix('"ok"'));
    }

    public function testMissingValueNull(): void
    {
        $this->assertSame('{"a":null}', $this->fix('{"a":}'));
    }

    public function testNestedMissingValue(): void
    {
        $this->assertSame('{"a":{"b":null}}', $this->fix('{"a":{"b":}}'));
    }

    /**
     * Everything the fixer outputs must be parseable by json_decode.
     */
    public function testOutputAlwaysValid(): void
    {
        $cases = [
            '{a:1}', "{'a':'b'}", '[1,2,3,]', '{"a":1,}',
            '{a:1,b:2,c:3}', '[1,2,3', '{"a":1',
            '{"a":True,"b":False,"c":None}',
            'callback({"ok":true})', "```\n[1,2]\n```",
            '{"_id":ObjectId("abc")}',
            '{a: "test &amp; value"}',
        ];

        foreach ($cases as $input) {
            $result  = $this->fix($input);
            $decoded = json_decode($result);
            $this->assertNotNull(
                $decoded,
                "Produced invalid JSON from: {$input}\nGot: {$result}"
            );
        }
    }
}
