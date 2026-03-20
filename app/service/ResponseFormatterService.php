<?php

declare(strict_types=1);

namespace app\service;

class ResponseFormatterService
{
    /**
     * @param array<mixed> $data
     */
    public function format(array $data, string $format): string
    {
        return match ($format) {
            'json' => $this->toJson($data),
            'xml' => $this->toXml($data),
            'yaml' => $this->toYaml($data),
            default => $this->toJson($data),
        };
    }

    /**
     * @param array<mixed> $data
     */
    private function toJson(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT) ?: '{}';
    }

    /**
     * @param array<mixed> $data
     */
    private function toXml(array $data): string
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<response>\n";
        $xml .= $this->arrayToXml($data, 1);
        return $xml . "</response>";
    }

    /**
     * @param array<mixed> $data
     */
    private function arrayToXml(array $data, int $indent): string
    {
        $xml = '';
        $spaces = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            $key = $this->sanitizeXmlKey($key);
            if ($value === null) {
                $xml .= "{$spaces}<{$key}/>\n";
                continue;
            }
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            if (is_array($value)) {
                $xml .= "{$spaces}<{$key}>\n";
                $xml .= $this->arrayToXml($value, $indent + 1);
                $xml .= "{$spaces}</{$key}>\n";
            } else {
                $strValue = is_scalar($value) ? (string) $value : '';
                $xml .= "{$spaces}<{$key}>" . htmlspecialchars($strValue) . "</{$key}>\n";
            }
        }

        return $xml;
    }

    private function sanitizeXmlKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $key) ?? $key;
    }

    /** @param array<mixed> $data */
    private function toYaml(array $data): string
    {
        return $this->arrayToYaml($data, 0);
    }

    /** @param array<mixed> $data */
    private function arrayToYaml(array $data, int $indent): string
    {
        $yaml = '';
        $spaces = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            if (is_array($value)) {
                $yaml .= "{$spaces}{$key}:\n";
                $yaml .= $this->arrayToYaml($value, $indent + 1);
            } else {
                $yaml .= "{$spaces}{$key}: " . $this->formatYamlValue($value) . "\n";
            }
        }

        return $yaml;
    }

    private function formatYamlValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_string($value) && (str_contains($value, ':') || str_contains($value, '#'))) {
            return "'" . str_replace("'", "''", $value) . "'";
        }
        return is_scalar($value) ? (string) $value : '';
    }

    public function getContentType(string $format): string
    {
        return match ($format) {
            'json' => 'application/json',
            'xml' => 'application/xml',
            'yaml' => 'application/x-yaml',
            default => 'application/json',
        };
    }

    public function detectFormat(string $acceptHeader): string
    {
        if (str_contains($acceptHeader, 'application/xml') || str_contains($acceptHeader, 'text/xml')) {
            return 'xml';
        }
        if (str_contains($acceptHeader, 'application/x-yaml') || str_contains($acceptHeader, 'text/yaml')) {
            return 'yaml';
        }
        return 'json';
    }
}
