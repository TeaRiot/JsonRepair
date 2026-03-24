<?php

declare(strict_types=1);

namespace Teariot\JsonRepair\Exceptions;

use RuntimeException;

class MalformedJsonException extends RuntimeException
{
    private int $cursor;

    public function __construct(string $reason, int $cursor)
    {
        $this->cursor = $cursor;
        parent::__construct("{$reason} (at offset {$cursor})");
    }

    public function getCursor(): int
    {
        return $this->cursor;
    }
}
