<?php

declare(strict_types=1);

namespace Teariot\JsonRepair\Tests;

use PHPUnit\Framework\TestCase;
use Teariot\JsonRepair\Lexer\CharInspector as C;

class CharInspectorTest extends TestCase
{
    public function testDigit(): void
    {
        $this->assertTrue(C::digit('0'));
        $this->assertTrue(C::digit('9'));
        $this->assertFalse(C::digit('a'));
    }

    public function testHex(): void
    {
        $this->assertTrue(C::hex('a'));
        $this->assertTrue(C::hex('F'));
        $this->assertFalse(C::hex('g'));
        $this->assertFalse(C::hex(''));
    }

    public function testWs(): void
    {
        $this->assertTrue(C::ws(' ', 0));
        $this->assertTrue(C::ws("\n", 0));
        $this->assertTrue(C::ws("\t", 0));
        $this->assertFalse(C::ws('a', 0));
    }

    public function testWsNoLF(): void
    {
        $this->assertTrue(C::wsNoLF(' ', 0));
        $this->assertFalse(C::wsNoLF("\n", 0));
    }

    public function testAnyQuote(): void
    {
        $this->assertTrue(C::anyQuote('"'));
        $this->assertTrue(C::anyQuote("'"));
        $this->assertTrue(C::anyQuote('`'));
        $this->assertTrue(C::anyQuote("\u{201C}"));
        $this->assertFalse(C::anyQuote('a'));
    }

    public function testStructural(): void
    {
        foreach (str_split(",:[]/{}\n+()") as $ch) {
            $this->assertTrue(C::structural($ch), "Expected structural: {$ch}");
        }
        $this->assertFalse(C::structural('a'));
        $this->assertFalse(C::structural(''));
    }

    public function testIdentStart(): void
    {
        $this->assertTrue(C::identStart('a'));
        $this->assertTrue(C::identStart('_'));
        $this->assertTrue(C::identStart('$'));
        $this->assertFalse(C::identStart('1'));
    }

    public function testIdentPart(): void
    {
        $this->assertTrue(C::identPart('1'));
        $this->assertTrue(C::identPart('a'));
    }

    public function testMb(): void
    {
        $this->assertSame('a', C::mb('abc', 0));
        $this->assertSame("\xC3\xA9", C::mb("abc\xC3\xA9", 3));
        $this->assertSame('', C::mb('abc', 10));
    }

    public function testChopLast(): void
    {
        $this->assertSame('hello', C::chopLast('hello,', ','));
        $this->assertSame('hello', C::chopLast('hello', ','));
    }

    public function testInjectBeforeTrailingWs(): void
    {
        $this->assertSame('x}', C::injectBeforeTrailingWs('x', '}'));
        $this->assertSame('x} ', C::injectBeforeTrailingWs('x ', '}'));
    }

    public function testSpliceOut(): void
    {
        $this->assertSame('hlo', C::spliceOut('hello', 1, 2));
    }

    public function testTrailingCommaOrLF(): void
    {
        $this->assertTrue(C::trailingCommaOrLF('x,'));
        $this->assertTrue(C::trailingCommaOrLF("x\n"));
        $this->assertFalse(C::trailingCommaOrLF('x'));
    }

    public function testValueLead(): void
    {
        $this->assertTrue(C::valueLead('{'));
        $this->assertTrue(C::valueLead('['));
        $this->assertTrue(C::valueLead('"'));
        $this->assertTrue(C::valueLead('-'));
        $this->assertFalse(C::valueLead(')'));
    }

    public function testControl(): void
    {
        $this->assertTrue(C::control("\n"));
        $this->assertTrue(C::control("\t"));
        $this->assertFalse(C::control('a'));
    }

    public function testPrintable(): void
    {
        $this->assertTrue(C::printable(' '));
        $this->assertTrue(C::printable('a'));
        $this->assertFalse(C::printable(''));
    }

    public function testUrlSchemeEnd(): void
    {
        $this->assertTrue(C::urlSchemeEnd('https://'));
        $this->assertTrue(C::urlSchemeEnd('ftp://'));
        $this->assertFalse(C::urlSchemeEnd('abc://'));
    }
}
