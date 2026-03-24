<?php

declare(strict_types=1);

namespace Teariot\JsonRepair\Lexer;

final class CharInspector
{
    private const NBSP          = 0xA0;
    private const EN_QUAD       = 0x2000;
    private const HAIR_SPACE    = 0x200A;
    private const NNBSP         = 0x202F;
    private const MATH_SPACE    = 0x205F;
    private const IDEO_SPACE    = 0x3000;

    /**
     * @param string $ch
     * @return bool
     */
    public static function digit(string $ch): bool
    {
        return $ch >= '0' && $ch <= '9';
    }

    /**
     * @param string $ch
     * @return bool
     */
    public static function hex(string $ch): bool
    {
        return $ch !== '' && strlen($ch) === 1 && ctype_xdigit($ch);
    }

    /**
     * @param string $src
     * @param int $pos
     * @return bool
     */
    public static function ws(string $src, int $pos): bool
    {
        $byte = $src[$pos] ?? null;
        if ($byte === null) {
            return false;
        }
        $o = ord($byte);
        return $o === 0x20 || $o === 0x0A || $o === 0x09 || $o === 0x0D;
    }

    /**
     * @param string $src
     * @param int $pos
     * @return bool
     */
    public static function wsNoLF(string $src, int $pos): bool
    {
        $byte = $src[$pos] ?? null;
        if ($byte === null) {
            return false;
        }
        $o = ord($byte);
        return $o === 0x20 || $o === 0x09 || $o === 0x0D;
    }

    /**
     * @param string $src
     * @param int $pos
     * @return bool
     */
    public static function exoticWs(string $src, int $pos): bool
    {
        if (!isset($src[$pos])) {
            return false;
        }
        $ch = mb_substr($src, $pos, 1, 'UTF-8');
        if ($ch === '' || $ch === false) {
            return false;
        }
        $cp = mb_ord($ch, 'UTF-8');
        if ($cp === false) {
            return false;
        }
        return $cp === self::NBSP
            || ($cp >= self::EN_QUAD && $cp <= self::HAIR_SPACE)
            || $cp === self::NNBSP
            || $cp === self::MATH_SPACE
            || $cp === self::IDEO_SPACE;
    }

    /**
     * @param string $ch
     * @return bool
     */
    public static function anyQuote(string $ch): bool
    {
        return self::dblQuoteLike($ch) || self::sglQuoteLike($ch);
    }

    /**
     * @param string $ch
     * @return bool
     */
    public static function dblQuoteLike(string $ch): bool
    {
        return $ch === '"' || $ch === "\u{201C}" || $ch === "\u{201D}";
    }

    /**
     * @param string $ch
     * @return bool
     */
    public static function dblQuoteStrict(string $ch): bool
    {
        return $ch === '"';
    }

    /**
     * @param string $ch
     * @return bool
     */
    public static function sglQuoteLike(string $ch): bool
    {
        return $ch === "'"
            || $ch === "\u{2018}" || $ch === "\u{2019}"
            || $ch === "\u{0060}" || $ch === "\u{00B4}";
    }

    /**
     * @param string $ch
     * @return bool
     */
    public static function sglQuoteStrict(string $ch): bool
    {
        return $ch === "'";
    }

    /**
     * @param string $ch
     * @return bool
     */
    public static function structural(string $ch): bool
    {
        return $ch !== '' && strpos(",:[]/{}\n+()", $ch) !== false;
    }

    /**
     * @param string $ch
     * @return bool
     */
    public static function bareStringStop(string $ch): bool
    {
        return $ch !== '' && strpos(",[]/{}\n+", $ch) !== false;
    }

    /**
     * @param string $ch
     * @return bool
     */
    public static function control(string $ch): bool
    {
        return $ch === "\n" || $ch === "\r" || $ch === "\t"
            || $ch === "\x08" || $ch === "\x0C";
    }

    /**
     * @param string $ch
     * @return bool
     */
    public static function printable(string $ch): bool
    {
        return $ch !== '' && ord($ch) >= 0x20;
    }

    /**
     * @param string $ch
     * @return bool
     */
    public static function identStart(string $ch): bool
    {
        return ($ch >= 'a' && $ch <= 'z')
            || ($ch >= 'A' && $ch <= 'Z')
            || $ch === '_' || $ch === '$';
    }

    /**
     * @param string $ch
     * @return bool
     */
    public static function identPart(string $ch): bool
    {
        return self::identStart($ch) || ($ch >= '0' && $ch <= '9');
    }

    /**
     * @param string $fragment
     * @return bool
     */
    public static function urlSchemeEnd(string $fragment): bool
    {
        return (bool) preg_match('#^(https?|ftp|mailto|file|data|irc)://$#', $fragment);
    }

    /**
     * @param string $ch
     * @return bool
     */
    public static function urlBody(string $ch): bool
    {
        return $ch !== '' && (bool) preg_match('/^[A-Za-z0-9\-._~:\/?#@!$&\'()*+;=%]$/', $ch);
    }

    /**
     * @param string $ch
     * @return bool
     */
    public static function valueLead(string $ch): bool
    {
        return self::anyQuote($ch) || (bool) preg_match('/^[[\{\w\-]$/u', $ch);
    }

    /**
     * @param string $src
     * @param int $pos
     * @return string
     */
    public static function mb(string $src, int $pos): string
    {
        if (!isset($src[$pos])) {
            return '';
        }
        $b = ord($src[$pos]);
        if ($b < 0x80) {
            return $src[$pos];
        }
        if ($b < 0xC0) {
            return $src[$pos];
        }
        $len = $b < 0xE0 ? 2 : ($b < 0xF0 ? 3 : 4);
        return substr($src, $pos, $len);
    }

    /**
     * @param string $buf
     * @return bool
     */
    public static function trailingCommaOrLF(string $buf): bool
    {
        return (bool) preg_match('/[,\n][ \t\r]*$/', $buf);
    }

    /**
     * @param string $buf
     * @param string $needle
     * @param bool $andAfter
     * @return string
     */
    public static function chopLast(string $buf, string $needle, bool $andAfter = false): string
    {
        $p = strrpos($buf, $needle);
        if ($p === false) {
            return $buf;
        }
        return substr($buf, 0, $p) . ($andAfter ? '' : substr($buf, $p + 1));
    }

    /**
     * @param string $buf
     * @param string $ins
     * @return string
     */
    public static function injectBeforeTrailingWs(string $buf, string $ins): string
    {
        $end = strlen($buf);
        if (!self::ws($buf, $end - 1)) {
            return $buf . $ins;
        }
        while ($end > 0 && self::ws($buf, $end - 1)) {
            $end--;
        }
        return substr($buf, 0, $end) . $ins . substr($buf, $end);
    }

    /**
     * @param string $buf
     * @param int $offset
     * @param int $count
     * @return string
     */
    public static function spliceOut(string $buf, int $offset, int $count): string
    {
        return substr($buf, 0, $offset) . substr($buf, $offset + $count);
    }
}
