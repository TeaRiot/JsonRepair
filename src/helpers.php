<?php

declare(strict_types=1);

namespace Teariot\JsonRepair;

use Teariot\JsonRepair\Exceptions\MalformedJsonException;

/**
 * @param string $input
 * @param bool $beautify
 * @return string
 */
function json_repair(string $input, bool $beautify = false): string
{
    return JsonRepair::fix($input, $beautify);
}

/**
 * @param string $input
 * @param bool $assoc
 * @return mixed
 */
function json_repair_decode(string $input, bool $assoc = true)
{
    return JsonRepair::decode($input, $assoc);
}

/**
 * @param string $input
 * @param bool $beautify
 * @return string
 */
function json_try_repair(string $input, bool $beautify = false): string
{
    return JsonRepair::tryFix($input, $beautify);
}

/**
 * @param string $raw
 * @param bool $beautify
 * @return string
 */
function json_extract_and_fix(string $raw, bool $beautify = false): string
{
    return JsonRepair::extractAndFix($raw, $beautify);
}

/**
 * @param string $raw
 * @param array|null $template
 * @param bool $assoc
 * @return mixed
 */
function json_extract_and_decode(string $raw, ?array $template = null, bool $assoc = true)
{
    return JsonRepair::extractAndDecode($raw, $template, $assoc);
}

/**
 * @param string $input
 * @param array $template
 * @return array
 */
function json_fix_with_template(string $input, array $template): array
{
    return JsonRepair::fixWithTemplate($input, $template);
}

/**
 * @param $source
 * @param int $chunkSize
 * @param bool $beautify
 * @return \Generator
 */
function json_repair_stream($source, int $chunkSize = 65536, bool $beautify = false): \Generator
{
    return JsonRepair::stream($source, $chunkSize, $beautify);
}
