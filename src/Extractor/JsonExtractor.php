<?php

declare(strict_types=1);

namespace Teariot\JsonRepair\Extractor;

class JsonExtractor
{
    /**
     * @param string $raw
     * @return string|null
     */
    public function extract(string $raw): ?string
    {
        $text = $this->stripNoise($raw);
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        $startBrace   = strpos($text, '{');
        $startBracket = strpos($text, '[');

        if ($startBrace === false && $startBracket === false) {
            return null;
        }

        if ($startBrace === false) {
            $start = $startBracket;
        } elseif ($startBracket === false) {
            $start = $startBrace;
        } else {
            $start = min($startBrace, $startBracket);
        }

        $open  = $text[$start];
        $close = $open === '{' ? '}' : ']';

        $end = $this->findClosing($text, $start, $open, $close);

        if ($end !== null) {
            return substr($text, $start, $end - $start + 1);
        }

        return substr($text, $start);
    }

    /**
     * @param string $raw
     * @return array
     */
    public function extractAll(string $raw): array
    {
        $text    = $this->stripNoise($raw);
        $text    = trim($text);
        $results = [];
        $offset  = 0;
        $len     = strlen($text);

        while ($offset < $len) {
            $brace   = strpos($text, '{', $offset);
            $bracket = strpos($text, '[', $offset);

            if ($brace === false && $bracket === false) {
                break;
            }

            if ($brace === false) {
                $start = $bracket;
            } elseif ($bracket === false) {
                $start = $brace;
            } else {
                $start = min($brace, $bracket);
            }

            $open  = $text[$start];
            $close = $open === '{' ? '}' : ']';
            $end   = $this->findClosing($text, $start, $open, $close);

            if ($end !== null) {
                $results[] = substr($text, $start, $end - $start + 1);
                $offset    = $end + 1;
            } else {
                $results[] = substr($text, $start);
                break;
            }
        }

        return $results;
    }

    /**
     * @param string $text
     * @return string
     */
    private function stripNoise(string $text): string
    {
        $text = $this->stripThinkBlocks($text);
        $text = $this->stripMarkdownFences($text);
        $text = $this->stripLogPrefix($text);
        return $text;
    }

    /**
     * @param string $text
     * @return string
     */
    private function stripThinkBlocks(string $text): string
    {
        $text = preg_replace('/<think>.*?<\/think>/s', '', $text);
        $text = preg_replace('/<think>.*$/s', '', $text);
        return $text;
    }

    /**
     * @param string $text
     * @return string
     */
    private function stripMarkdownFences(string $text): string
    {
        $text = preg_replace('/```(?:json|JSON)?\s*\n?/m', '', $text);
        return $text;
    }

    /**
     * @param string $text
     * @return string
     */
    private function stripLogPrefix(string $text): string
    {
        $text = preg_replace(
            '/^\s*\[[\d\-\s:]+\]\s*\S+\.\w+:\s*[^{[\n]*(?=[{[\[])/s',
            '',
            $text
        );

        if (preg_match('/^[^{[\[]*?(?=[{[\[])/', $text, $m) && strlen($m[0]) > 0) {
            $prefix = $m[0];
            if (!preg_match('/["\']$/', rtrim($prefix))) {
                $text = substr($text, strlen($prefix));
            }
        }

        return $text;
    }

    /**
     * @param string $text
     * @param int $start
     * @param string $open
     * @param string $close
     * @return int|null
     */
    private function findClosing(string $text, int $start, string $open, string $close): ?int
    {
        $len     = strlen($text);
        $depth   = 0;
        $inStr   = false;
        $strChar = '';
        $escaped = false;
        $i       = $start;

        while ($i < $len) {
            $c = $text[$i];

            if ($escaped) {
                $escaped = false;
                $i++;
                continue;
            }

            if ($c === '\\' && $inStr) {
                $escaped = true;
                $i++;
                continue;
            }

            if ($inStr) {
                if ($c === $strChar) {
                    $inStr = false;
                }
                $i++;
                continue;
            }

            if ($c === '"' || $c === "'") {
                $inStr   = true;
                $strChar = $c;
            } elseif ($c === $open) {
                $depth++;
            } elseif ($c === $close) {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }

            $i++;
        }

        return null;
    }
}
