<?php

declare(strict_types=1);

namespace Teariot\JsonRepair;

use Generator;
use Teariot\JsonRepair\Exceptions\MalformedJsonException;
use Teariot\JsonRepair\Extractor\JsonExtractor;
use Teariot\JsonRepair\Fixer\JsonFixer;
use Teariot\JsonRepair\Streaming\ChunkedFixer;
use Teariot\JsonRepair\Template\TemplateApplier;

final class JsonRepair
{
    /**
     * @param string $input
     * @param bool $beautify
     * @return string
     */
    public static function fix(string $input, bool $beautify = false): string
    {
        return (new JsonFixer())->fix($input, $beautify);
    }

    /**
     * @param string $input
     * @param bool $assoc
     * @param int $depth
     * @param int $flags
     * @return mixed
     * @throws \JsonException
     */
    public static function decode(
        string $input,
        bool $assoc = true,
        int $depth = 512,
        int $flags = 0
    ) {
        $json = self::fix($input);
        return json_decode($json, $assoc, $depth, $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * @param string $input
     * @param bool $beautify
     * @return string
     */
    public static function tryFix(string $input, bool $beautify = false): string
    {
        try {
            return self::fix($input, $beautify);
        } catch (MalformedJsonException $e) {
            return $input;
        }
    }

    /**
     * @param string $input
     * @return bool
     */
    public static function needsRepair(string $input): bool
    {
        json_decode($input);
        if (json_last_error() === JSON_ERROR_NONE) {
            return false;
        }
        try {
            $fixed = self::fix($input);
            json_decode($fixed);
            return json_last_error() === JSON_ERROR_NONE;
        } catch (MalformedJsonException $e) {
            return false;
        }
    }

    /**
     * @param string $raw
     * @param bool $beautify
     * @return string
     */
    public static function extractAndFix(string $raw, bool $beautify = false): string
    {
        $extracted = (new JsonExtractor())->extract($raw);
        if ($extracted === null) {
            throw new MalformedJsonException('No JSON found in input', 0);
        }
        return self::fix($extracted, $beautify);
    }

    /**
     * @param string $raw
     * @param array|null $template
     * @param bool $assoc
     * @return array|mixed
     * @throws \JsonException
     */
    public static function extractAndDecode(
        string $raw,
        ?array $template = null,
        bool $assoc = true
    ) {
        $json = self::extractAndFix($raw);
        $data = json_decode($json, $assoc, 512, JSON_THROW_ON_ERROR);

        if ($template !== null && is_array($data) && $assoc) {
            $data = (new TemplateApplier())->apply($data, $template);
        }

        return $data;
    }

    /**
     * @param string $input
     * @param array $template
     * @return array
     * @throws \JsonException
     */
    public static function fixWithTemplate(string $input, array $template): array
    {
        $json = self::fix($input);
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            return $template;
        }

        return (new TemplateApplier())->apply($data, $template);
    }

    /**
     * @param $source
     * @param int $chunkSize
     * @param bool $beautify
     * @return Generator
     */
    public static function stream($source, int $chunkSize = 65536, bool $beautify = false): Generator
    {
        return (new ChunkedFixer($chunkSize, $beautify))->stream($source);
    }

    /**
     * @param $source
     * @param int $chunkSize
     * @param bool $beautify
     * @return string
     */
    public static function streamCollect($source, int $chunkSize = 65536, bool $beautify = false): string
    {
        return (new ChunkedFixer($chunkSize, $beautify))->collect($source);
    }
}
