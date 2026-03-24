<?php

declare(strict_types=1);

namespace Teariot\JsonRepair\Fixer;

use Teariot\JsonRepair\Exceptions\MalformedJsonException;
use Teariot\JsonRepair\Lexer\CharInspector as C;

class JsonFixer
{
    private const CTRL_MAP = [
        "\x08" => '\\b',
        "\x0C" => '\\f',
        "\n"   => '\\n',
        "\r"   => '\\r',
        "\t"   => '\\t',
    ];

    private const ESC_MAP = [
        '"'  => '"',  '\\' => '\\', '/' => '/',
        'b'  => "\x08", 'f' => "\x0C", 'n' => "\n",
        'r'  => "\r",   't' => "\t",
    ];

    private const HTML_ENTITIES = [
        '&amp;'  => '&',
        '&lt;'   => '<',
        '&gt;'   => '>',
        '&quot;' => '"',
        '&#39;'  => "'",
        '&apos;' => "'",
        '&nbsp;' => ' ',
        '&tab;'  => "\t",
    ];

    private const KEYWORDS = [
        'true'      => 'true',
        'false'     => 'false',
        'null'      => 'null',
        'True'      => 'true',
        'False'     => 'false',
        'None'      => 'null',
        'undefined' => 'null',
        'NaN'       => '"NaN"',
        'Infinity'  => '"Infinity"',
        '-Infinity' => '"-Infinity"',
    ];

    private string $src  = '';
    private int    $pos  = 0;
    private string $out  = '';
    private bool   $beautify = false;

    /**
     * @param string $input
     * @param bool $beautify
     * @return string
     */
    public function fix(string $input, bool $beautify = false): string
    {
        $this->src      = $input;
        $this->pos      = 0;
        $this->out      = '';
        $this->beautify = $beautify;

        $this->stripOpeningFence(['```', '[```', '{```']);

        if (!$this->consumeValue()) {
            throw new MalformedJsonException('Unexpected end of input', strlen($this->src));
        }

        $this->stripClosingFence(['```', '```]', '```}']);

        $hadComma = $this->emit(',');
        if ($hadComma) {
            $this->skipGarbage();
        }

        if (C::valueLead($this->peek()) && C::trailingCommaOrLF($this->out)) {
            if (!$hadComma) {
                $this->out = C::injectBeforeTrailingWs($this->out, ',');
            }
            $this->mergeNDJSON();
        } elseif ($hadComma) {
            $this->out = C::chopLast($this->out, ',');
        }

        while ($this->peek() === '}' || $this->peek() === ']') {
            $this->pos++;
            $this->skipGarbage();
        }

        if ($this->pos < strlen($this->src)) {
            $this->fail('Unexpected symbol ' . json_encode($this->peek()));
        }

        return $this->out;
    }

    /**
     * @return bool
     */
    private function consumeValue(): bool
    {
        $this->skipGarbage();
        $ok = $this->consumeObject()
            || $this->consumeArray()
            || $this->consumeString()
            || $this->consumeNumber()
            || $this->consumeKeyword()
            || $this->consumeBareString(false)
            || $this->consumeRegex();
        $this->skipGarbage();
        return $ok;
    }

    /**
     * @return bool
     */
    private function consumeObject(): bool
    {
        if ($this->peek() !== '{') {
            return false;
        }
        $this->write('{');
        $this->pos++;
        $this->skipGarbage();

        if ($this->peek() === ',') {
            $this->pos++;
            $this->skipGarbage();
        }

        $first = true;
        while ($this->pos < strlen($this->src) && $this->peek() !== '}') {
            if (!$first) {
                if (!$this->emit(',')) {
                    $this->out = C::injectBeforeTrailingWs($this->out, ',');
                }
                $this->skipGarbage();
            } else {
                $first = false;
            }

            $this->skipDots();

            $gotKey = $this->consumeString() || $this->consumeBareString(true);
            if (!$gotKey) {
                $ch = $this->peek();
                if ($ch === '}' || $ch === '{' || $ch === ']' || $ch === '[' || $ch === '') {
                    $this->out = C::chopLast($this->out, ',');
                } else {
                    $this->fail('Expected object key');
                }
                break;
            }

            $this->skipGarbage();

            $gotColon  = $this->emit(':');
            $truncated = $this->pos >= strlen($this->src);

            if (!$gotColon) {
                if (C::valueLead($this->peek()) || $truncated) {
                    $this->out = C::injectBeforeTrailingWs($this->out, ':');
                } else {
                    $this->fail('Expected colon');
                }
            }

            if (!$this->consumeValue()) {
                if ($gotColon || $truncated) {
                    $this->write('null');
                } else {
                    $this->fail('Expected colon');
                }
            }
        }

        if ($this->peek() === '}') {
            $this->write('}');
            $this->pos++;
        } else {
            $this->out = C::injectBeforeTrailingWs($this->out, '}');
        }
        return true;
    }

    /**
     * @return bool
     */
    private function consumeArray(): bool
    {
        if ($this->peek() !== '[') {
            return false;
        }
        $this->write('[');
        $this->pos++;
        $this->skipGarbage();

        if ($this->peek() === ',') {
            $this->pos++;
            $this->skipGarbage();
        }

        $first = true;
        while ($this->pos < strlen($this->src) && $this->peek() !== ']') {
            if (!$first) {
                if (!$this->emit(',')) {
                    $this->out = C::injectBeforeTrailingWs($this->out, ',');
                }
            } else {
                $first = false;
            }

            $this->skipDots();

            if (!$this->consumeValue()) {
                $this->out = C::chopLast($this->out, ',');
                break;
            }
        }

        if ($this->peek() === ']') {
            $this->write(']');
            $this->pos++;
        } else {
            $this->out = C::injectBeforeTrailingWs($this->out, ']');
        }
        return true;
    }

    /**
     * @param bool $haltOnDelim
     * @param int $haltAt
     * @return bool
     */
    private function consumeString(bool $haltOnDelim = false, int $haltAt = -1): bool
    {
        $escapePrefixed = ($this->peek() === '\\');
        if ($escapePrefixed) {
            $this->pos++;
        }

        $opener = C::mb($this->src, $this->pos);
        if (!C::anyQuote($opener)) {
            return false;
        }

        $closerFn = $this->buildCloserTest($opener);
        $savePos  = $this->pos;
        $saveOut  = strlen($this->out);

        $buf = '"';
        $this->pos += strlen($opener);

        while (true) {
            if ($this->pos >= strlen($this->src)) {
                $prev = $this->prevVisible($this->pos - 1);
                if (!$haltOnDelim && C::structural($prev)) {
                    return $this->retryString($savePos, $saveOut, true);
                }
                $buf = C::injectBeforeTrailingWs($buf, '"');
                $this->write($buf);
                return true;
            }

            if ($this->pos === $haltAt) {
                $buf = C::injectBeforeTrailingWs($buf, '"');
                $this->write($buf);
                return true;
            }

            $ch = C::mb($this->src, $this->pos);

            if ($closerFn($ch)) {
                $qPos    = $this->pos;
                $qBufLen = strlen($buf);
                $buf .= '"';
                $this->pos += strlen($ch);
                $this->write($buf);
                $this->skipGarbage(false);

                if (
                    $haltOnDelim
                    || $this->pos >= strlen($this->src)
                    || C::structural($this->peek())
                    || C::anyQuote($this->peek())
                    || C::digit($this->peek())
                ) {
                    $this->mergeConcat();
                    return true;
                }

                $prevCh = $this->prevVisible($qPos - 1);
                if ($prevCh === ',') {
                    return $this->retryString($savePos, $saveOut, false, $this->prevVisibleIndex($qPos - 1));
                }
                if (C::structural($prevCh)) {
                    return $this->retryString($savePos, $saveOut, true);
                }

                $this->out = substr($this->out, 0, $saveOut);
                $this->pos = $qPos + 1;
                if ($this->beautify) {
                    $buf = substr($buf, 0, $qBufLen) . "\u{201D}" . substr($buf, $qBufLen + 1);
                } else {
                    $buf = substr($buf, 0, $qBufLen) . '\\' . substr($buf, $qBufLen);
                }
                continue;
            }

            if ($this->peek() === '\\') {
                $next = $this->src[$this->pos + 1] ?? '';
                if (isset(self::ESC_MAP[$next])) {
                    $buf .= substr($this->src, $this->pos, 2);
                    $this->pos += 2;
                } elseif ($next === 'u') {
                    $buf = $this->readUnicodeEscape($buf);
                } else {
                    $buf .= $next;
                    $this->pos += 2;
                }
            } elseif ($haltOnDelim && C::bareStringStop($this->peek())) {
                $cur = $this->peek();
                if ($cur !== "\n" && ($this->src[$this->pos - 1] ?? '') === ':'
                    && C::urlSchemeEnd(substr($this->src, $savePos + 1, $this->pos - $savePos + 1))
                ) {
                    while ($this->pos < strlen($this->src) && C::urlBody($this->peek())) {
                        $buf .= $this->peek();
                        $this->pos++;
                    }
                }
                $buf = C::injectBeforeTrailingWs($buf, '"');
                $this->write($buf);
                $this->mergeConcat();
                return true;
            } elseif ($this->peek() === '"' && ($this->src[$this->pos - 1] ?? '') !== '\\') {
                $buf .= $this->beautify ? "\u{201D}" : '\\"';
                $this->pos++;
            } elseif (C::control($this->peek())) {
                $buf .= self::CTRL_MAP[$this->peek()];
                $this->pos++;
            } elseif ($this->peek() === '&' && $this->looksLikeHtmlEntity()) {
                $buf .= $this->decodeHtmlEntity();
            } else {
                $raw = $this->peek();
                if (!C::printable($raw)) {
                    $this->fail('Non-printable byte ' . json_encode($raw));
                }
                $buf .= $raw;
                $this->pos++;
            }

            if ($escapePrefixed) {
                if ($this->peek() === '\\') {
                    $this->pos++;
                }
            }
        }
    }

    /**
     * @param int $posSnap
     * @param int $outSnap
     * @param bool $delim
     * @param int $haltAt
     * @return bool
     */
    private function retryString(int $posSnap, int $outSnap, bool $delim, int $haltAt = -1): bool
    {
        $this->pos = $posSnap;
        $this->out = substr($this->out, 0, $outSnap);
        return $this->consumeString($delim, $haltAt);
    }

    /**
     * @param string $opener
     * @return callable
     */
    private function buildCloserTest(string $opener): callable
    {
        if (C::dblQuoteStrict($opener)) {
            return static fn(string $c): bool => C::dblQuoteStrict($c);
        }
        if (C::sglQuoteStrict($opener)) {
            return static fn(string $c): bool => C::sglQuoteStrict($c);
        }
        if (C::sglQuoteLike($opener)) {
            return static fn(string $c): bool => C::sglQuoteLike($c);
        }
        return static fn(string $c): bool => C::dblQuoteLike($c);
    }

    /**
     * @return bool
     */
    private function mergeConcat(): bool
    {
        $any = false;
        $this->skipGarbage();
        while ($this->peek() === '+') {
            $any = true;
            $this->pos++;
            $this->skipGarbage();
            $this->out = C::chopLast($this->out, '"', true);
            $snap = strlen($this->out);
            if ($this->consumeString()) {
                $this->out = C::spliceOut($this->out, $snap, 1);
            } else {
                $this->out = C::injectBeforeTrailingWs($this->out, '"');
            }
        }
        return $any;
    }

    /**
     * @param string $buf
     * @return string
     */
    private function readUnicodeEscape(string $buf): string
    {
        $j = 2;
        while ($j < 6 && C::hex($this->src[$this->pos + $j] ?? '')) {
            $j++;
        }
        if ($j === 6) {
            $buf .= substr($this->src, $this->pos, 6);
            $this->pos += 6;
        } elseif ($this->pos + $j >= strlen($this->src)) {
            $this->pos = strlen($this->src);
        } else {
            $this->fail('Invalid unicode escape ' . json_encode(substr($this->src, $this->pos, 6)));
        }
        return $buf;
    }

    /**
     * @return bool
     */
    private function looksLikeHtmlEntity(): bool
    {
        return (bool) preg_match('/^&(?:#x?[0-9a-fA-F]+|[a-zA-Z]+);/', substr($this->src, $this->pos, 12));
    }

    /**
     * @return string
     */
    private function decodeHtmlEntity(): string
    {
        if (!preg_match('/^(&(?:#x?[0-9a-fA-F]+|[a-zA-Z]+);)/', substr($this->src, $this->pos, 12), $m)) {
            $this->pos++;
            return '&';
        }
        $entity = $m[1];
        $this->pos += strlen($entity);

        if (isset(self::HTML_ENTITIES[$entity])) {
            $decoded = self::HTML_ENTITIES[$entity];
            if ($decoded === '"') {
                return $this->beautify ? "\u{201D}" : '\\"';
            }
            return $decoded;
        }

        $decoded = html_entity_decode($entity, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($decoded === $entity) {
            return $entity;
        }

        if (isset(self::CTRL_MAP[$decoded])) {
            return self::CTRL_MAP[$decoded];
        }

        return $decoded;
    }

    /**
     * @return bool
     */
    private function consumeNumber(): bool
    {
        $start = $this->pos;

        if ($this->peek() === '-') {
            $this->pos++;
            if ($this->numberEnded()) {
                $this->write(substr($this->src, $start, $this->pos - $start) . '0');
                return true;
            }
            if (!C::digit($this->peek())) {
                $this->pos = $start;
                return false;
            }
        }

        $this->eatDigits();

        if ($this->peek() === '.') {
            $this->pos++;
            if ($this->numberEnded()) {
                $this->write(substr($this->src, $start, $this->pos - $start) . '0');
                return true;
            }
            if (!C::digit($this->peek())) {
                $this->pos = $start;
                return false;
            }
            $this->eatDigits();
        }

        if ($this->peek() === 'e' || $this->peek() === 'E') {
            $this->pos++;
            if ($this->peek() === '+' || $this->peek() === '-') {
                $this->pos++;
            }
            if ($this->numberEnded()) {
                $this->write(substr($this->src, $start, $this->pos - $start) . '0');
                return true;
            }
            if (!C::digit($this->peek())) {
                $this->pos = $start;
                return false;
            }
            $this->eatDigits();
        }

        if (!$this->numberEnded()) {
            $this->pos = $start;
            return false;
        }

        if ($this->pos > $start) {
            $raw = substr($this->src, $start, $this->pos - $start);
            $this->write(preg_match('/^0\d/', $raw) ? "\"{$raw}\"" : $raw);
            return true;
        }
        return false;
    }

    /**
     * @return void
     */
    private function eatDigits(): void
    {
        while (C::digit($this->peek())) {
            $this->pos++;
        }
    }

    /**
     * @return bool
     */
    private function numberEnded(): bool
    {
        return $this->pos >= strlen($this->src)
            || C::structural($this->peek())
            || C::ws($this->src, $this->pos);
    }

    /**
     * @return bool
     */
    private function consumeKeyword(): bool
    {
        foreach (self::KEYWORDS as $lit => $replacement) {
            $len = strlen($lit);
            if (substr($this->src, $this->pos, $len) === $lit) {
                $after = $this->src[$this->pos + $len] ?? '';
                if ($after !== '' && C::identPart($after)) {
                    continue;
                }
                $this->write($replacement);
                $this->pos += $len;
                return true;
            }
        }
        return false;
    }

    /**
     * @param bool $keyMode
     * @return bool
     */
    private function consumeBareString(bool $keyMode): bool
    {
        $start = $this->pos;

        if (C::identStart($this->peek())) {
            while ($this->pos < strlen($this->src) && C::identPart($this->src[$this->pos])) {
                $this->pos++;
            }
            $j = $this->pos;
            while (C::ws($this->src, $j)) {
                $j++;
            }
            if (($this->src[$j] ?? '') === '(') {
                $this->pos = $j + 1;
                $this->consumeValue();
                if ($this->peek() === ')') {
                    $this->pos++;
                }
                if ($this->peek() === ';') {
                    $this->pos++;
                }
                return true;
            }
        }

        while (
            $this->pos < strlen($this->src)
            && !C::bareStringStop($this->peek())
            && !C::anyQuote($this->peek())
            && (!$keyMode || $this->peek() !== ':')
        ) {
            $this->pos++;
        }

        if (
            ($this->src[$this->pos - 1] ?? '') === ':'
            && C::urlSchemeEnd(substr($this->src, $start, $this->pos - $start + 2))
        ) {
            while ($this->pos < strlen($this->src) && C::urlBody($this->peek())) {
                $this->pos++;
            }
        }

        if ($this->pos > $start) {
            while (C::ws($this->src, $this->pos - 1) && $this->pos > $start) {
                $this->pos--;
            }
            $token = substr($this->src, $start, $this->pos - $start);
            $this->write($token === 'undefined' ? 'null' : json_encode($token, JSON_UNESCAPED_SLASHES));

            if ($this->peek() === '"') {
                $this->pos++;
            }
            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    private function consumeRegex(): bool
    {
        if ($this->peek() !== '/') {
            return false;
        }
        $start = $this->pos;
        $this->pos++;

        while ($this->pos < strlen($this->src)
            && ($this->peek() !== '/' || ($this->src[$this->pos - 1] ?? '') === '\\')
        ) {
            $this->pos++;
        }
        $this->pos++;

        while ($this->pos < strlen($this->src) && strpos('gimsuy', $this->peek()) !== false) {
            $this->pos++;
        }

        $this->write('"' . substr($this->src, $start, $this->pos - $start) . '"');
        return true;
    }

    /**
     * @return void
     */
    private function mergeNDJSON(): void
    {
        $first = true;
        $ok    = true;
        while ($ok) {
            if (!$first) {
                if (!$this->emit(',')) {
                    $this->out = C::injectBeforeTrailingWs($this->out, ',');
                }
            } else {
                $first = false;
            }
            $ok = $this->consumeValue();
        }
        $this->out = C::chopLast($this->out, ',');
        $this->out = "[\n{$this->out}\n]";
    }

    /**
     * @param bool $allowLF
     * @return bool
     */
    private function skipGarbage(bool $allowLF = true): bool
    {
        $origin = $this->pos;
        $this->eatWhitespace($allowLF);
        do {
            $skipped = $this->eatComment();
            if ($skipped) {
                $this->eatWhitespace($allowLF);
            }
        } while ($skipped);
        return $this->pos > $origin;
    }

    /**
     * @param bool $allowLF
     * @return bool
     */
    private function eatWhitespace(bool $allowLF): bool
    {
        $buf = '';
        while (true) {
            $check = $allowLF
                ? C::ws($this->src, $this->pos)
                : C::wsNoLF($this->src, $this->pos);
            if ($check) {
                $buf .= $this->src[$this->pos];
                $this->pos++;
            } elseif (C::exoticWs($this->src, $this->pos)) {
                $buf .= ' ';
                $ch = C::mb($this->src, $this->pos);
                $this->pos += strlen($ch);
            } else {
                break;
            }
        }
        if ($buf !== '') {
            $this->out .= $buf;
            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    private function eatComment(): bool
    {
        if ($this->peek() === '/' && ($this->src[$this->pos + 1] ?? '') === '*') {
            while ($this->pos < strlen($this->src)
                && !(($this->src[$this->pos] ?? '') === '*' && ($this->src[$this->pos + 1] ?? '') === '/')) {
                $this->pos++;
            }
            $this->pos += 2;
            return true;
        }
        if ($this->peek() === '/' && ($this->src[$this->pos + 1] ?? '') === '/') {
            while ($this->pos < strlen($this->src) && $this->src[$this->pos] !== "\n") {
                $this->pos++;
            }
            return true;
        }
        if ($this->peek() === '#') {
            while ($this->pos < strlen($this->src) && $this->src[$this->pos] !== "\n") {
                $this->pos++;
            }
            return true;
        }
        return false;
    }

    /**
     * @param array $markers
     * @return bool
     */
    private function stripOpeningFence(array $markers): bool
    {
        $snap = strlen($this->out);
        $this->eatWhitespace(true);

        foreach ($markers as $m) {
            if (substr($this->src, $this->pos, strlen($m)) === $m) {
                $this->out = substr($this->out, 0, $snap) ?: '';
                $this->pos += strlen($m);
                if (C::identStart($this->peek())) {
                    while ($this->pos < strlen($this->src) && C::identPart($this->src[$this->pos])) {
                        $this->pos++;
                    }
                }
                while ($this->pos < strlen($this->src) && (
                    C::ws($this->src, $this->pos) || C::exoticWs($this->src, $this->pos)
                )) {
                    $this->pos++;
                }
                return true;
            }
        }
        return false;
    }

    /**
     * @param array $markers
     * @return bool
     */
    private function stripClosingFence(array $markers): bool
    {
        $snap = strlen($this->out);
        $this->eatWhitespace(true);

        foreach ($markers as $m) {
            if (substr($this->src, $this->pos, strlen($m)) === $m) {
                $this->out = rtrim(substr($this->out, 0, $snap) ?: '');
                $this->pos += strlen($m);
                while ($this->pos < strlen($this->src) && (
                    C::ws($this->src, $this->pos) || C::exoticWs($this->src, $this->pos)
                )) {
                    $this->pos++;
                }
                return true;
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    private function skipDots(): bool
    {
        $this->skipGarbage();
        if (
            ($this->src[$this->pos] ?? '') === '.'
            && ($this->src[$this->pos + 1] ?? '') === '.'
            && ($this->src[$this->pos + 2] ?? '') === '.'
        ) {
            $this->pos += 3;
            $this->skipGarbage();
            if ($this->peek() === ',') {
                $this->pos++;
            }
            return true;
        }
        return false;
    }

    /**
     * @return string
     */
    private function peek(): string
    {
        return $this->src[$this->pos] ?? '';
    }

    /**
     * @param string $s
     * @return void
     */
    private function write(string $s): void
    {
        $this->out .= $s;
    }

    /**
     * @param string $byte
     * @return bool
     */
    private function emit(string $byte): bool
    {
        if ($this->peek() === $byte) {
            $this->out .= $byte;
            $this->pos++;
            return true;
        }
        return false;
    }

    /**
     * @param int $from
     * @return string
     */
    private function prevVisible(int $from): string
    {
        $i = $from;
        while ($i > 0 && C::ws($this->src, $i)) {
            $i--;
        }
        return $this->src[$i] ?? '';
    }

    /**
     * @param int $from
     * @return int
     */
    private function prevVisibleIndex(int $from): int
    {
        $i = $from;
        while ($i > 0 && C::ws($this->src, $i)) {
            $i--;
        }
        return $i;
    }

    /**
     * @param string $reason
     * @return void
     */
    private function fail(string $reason): void
    {
        throw new MalformedJsonException($reason, $this->pos);
    }
}
