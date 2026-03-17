<?php

namespace app\service;

class ResponseFormatterService
{
    public function format(array $data, string $format): string
    {
        return match ($format) {
            'json' => $this->toJson($data),
            'xml' => $this->toXml($data),
            'yaml' => $this->toYaml($data),
            default => $this->toJson($data),
        };
    }

    private function toJson(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT);
    }

    private function toXml(array $data): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>\n';
        $xml .= "<response>\n";
        $xml .= $this->arrayToXml($data, 1);
        $xml .= "</response>";
        return $xml;
    }

    private function arrayToXml(array $data, int $indent): string
    {
        $xml = '';
        $spaces = str_repeat('  ', $indent);
        
        foreach ($data as $key => $value) {
            $key = $this->sanitizeXmlKey($key);
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            if (is_array($value)) {
                $xml .= "{$spaces}<{$key}>\n";
                $xml .= $this->arrayToXml($value, $indent + 1);
                $xml .= "{$spaces}</{$key}>\n";
            } else {
                $xml .= "{$spaces}<{$key}>" . htmlspecialchars((string)$value) . "</{$key}>\n";
            }
        }
        
        return $xml;
    }

    private function sanitizeXmlKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
    }

    private function toYaml(array $data): string
    {
        return $this->arrayToYaml($data, 0);
    }

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

    private function formatYamlValue($value): string
    {
        if (is_string($value) && (strpos($value, ':') !== false || strpos($value, '#') !== false)) {
            return "'" . str_replace("'", "''", $value) . "'";
        }
        return (string) $value;
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
        if (strpos($acceptHeader, 'application/xml') !== false || strpos($acceptHeader, 'text/xml') !== false) {
            return 'xml';
        }
        if (strpos($acceptHeader, 'application/x-yaml') !== false || strpos($acceptHeader, 'text/yaml') !== false) {
            return 'yaml';
        }
        return 'json';
    }
}
