<?php

declare(strict_types=1);

namespace Teariot\JsonRepair\Template;

class TemplateApplier
{
    /**
     * @param array $data
     * @param array $template
     * @return array
     */
    public function apply(array $data, array $template): array
    {
        foreach ($template as $key => $defaultValue) {
            if (!array_key_exists($key, $data)) {
                $data[$key] = $defaultValue;
                continue;
            }

            if (is_array($defaultValue) && $this->isAssoc($defaultValue)
                && is_array($data[$key]) && $this->isAssoc($data[$key])
            ) {
                $data[$key] = $this->apply($data[$key], $defaultValue);
            }
        }

        return $data;
    }

    /**
     * @param array $data
     * @param string $templateJson
     * @return array
     */
    public function applyFromJson(array $data, string $templateJson): array
    {
        $template = json_decode($templateJson, true);
        if (!is_array($template)) {
            return $data;
        }
        return $this->apply($data, $template);
    }

    /**
     * @param array $arr
     * @return bool
     */
    private function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
