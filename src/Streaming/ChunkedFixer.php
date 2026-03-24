<?php

declare(strict_types=1);

namespace Teariot\JsonRepair\Streaming;

use Generator;
use InvalidArgumentException;
use Teariot\JsonRepair\Fixer\JsonFixer;

class ChunkedFixer
{
    private int $bufferSize;
    private bool $beautify;

    public function __construct(int $bufferSize = 65536, bool $beautify = false)
    {
        $this->bufferSize = $bufferSize;
        $this->beautify   = $beautify;
    }

    /**
     * @param $source
     * @return Generator
     */
    public function stream($source): Generator
    {
        $fixer  = new JsonFixer();
        $buffer = '';

        foreach ($this->chunks($source) as $chunk) {
            $buffer .= $chunk;

            while (true) {
                $pair = $this->extractClosed($buffer);
                if ($pair === null) {
                    break;
                }
                [$value, $buffer] = $pair;
                yield $fixer->fix($value, $this->beautify);
            }
        }

        $buffer = trim($buffer);
        if ($buffer !== '') {
            yield $fixer->fix($buffer, $this->beautify);
        }
    }

    /**
     * @param $source
     * @return string
     */
    public function collect($source): string
    {
        $result = '';
        foreach ($this->stream($source) as $piece) {
            $result .= $piece;
        }
        return $result;
    }

    /**
     * @param $source
     * @return Generator
     */
    private function chunks($source): Generator
    {
        if (is_resource($source)) {
            while (!feof($source)) {
                $data = fread($source, $this->bufferSize);
                if ($data !== false && $data !== '') {
                    yield $data;
                }
            }
        } elseif (is_iterable($source)) {
            foreach ($source as $item) {
                yield $item;
            }
        } else {
            throw new InvalidArgumentException('Expected a resource or iterable');
        }
    }

    /**
     * @param string $buf
     * @return array|null
     */
    private function extractClosed(string $buf): ?array
    {
        $len = strlen($buf);
        $i   = 0;

        while ($i < $len && ($buf[$i] === ' ' || $buf[$i] === "\t" || $buf[$i] === "\n" || $buf[$i] === "\r")) {
            $i++;
        }
        if ($i >= $len) {
            return null;
        }

        $start = $i;
        $open  = $buf[$i];

        if ($open !== '{' && $open !== '[') {
            return null;
        }
        $close    = $open === '{' ? '}' : ']';
        $depth    = 0;
        $inStr    = false;
        $escaped  = false;

        while ($i < $len) {
            $c = $buf[$i];

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
            if ($c === '"') {
                $inStr = !$inStr;
            } elseif (!$inStr) {
                if ($c === $open) {
                    $depth++;
                } elseif ($c === $close) {
                    $depth--;
                    if ($depth === 0) {
                        return [
                            substr($buf, $start, $i - $start + 1),
                            substr($buf, $i + 1),
                        ];
                    }
                }
            }
            $i++;
        }

        return null;
    }
}
