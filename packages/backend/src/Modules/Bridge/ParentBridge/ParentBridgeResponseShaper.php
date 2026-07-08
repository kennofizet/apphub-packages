<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\ParentBridge;

final class ParentBridgeResponseShaper
{
    /**
     * @param array<string, mixed> $schema
     */
    public function shape(array $schema, mixed $result): mixed
    {
        $type = strtolower(trim((string) ($schema['type'] ?? '')));
        $nullable = ($schema['nullable'] ?? false) === true;

        if ($result === null) {
            return $nullable ? null : ($type === 'array' ? [] : null);
        }

        return match ($type) {
            'array' => $this->shapeArray($schema, $result),
            'object' => $this->shapeObject($schema, $result),
            'scalar' => is_scalar($result) ? $result : null,
            'void' => null,
            default => $result,
        };
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function shapeArray(array $schema, mixed $result): array
    {
        if (!is_array($result)) {
            return [];
        }

        $items = $schema['items'] ?? null;
        if (!is_array($items) || $items === []) {
            return array_values($result);
        }

        $allowed = array_values(array_filter($items, static fn ($key) => is_string($key) && $key !== ''));
        $out = [];

        foreach ($result as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = $this->pickFields($row, $allowed);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function shapeObject(array $schema, mixed $result): ?array
    {
        if (!is_array($result)) {
            return null;
        }

        $fields = $schema['fields'] ?? null;
        if (!is_array($fields) || $fields === []) {
            return $result;
        }

        $allowed = array_values(array_filter($fields, static fn ($key) => is_string($key) && $key !== ''));

        return $this->pickFields($result, $allowed);
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $allowed
     * @return array<string, mixed>
     */
    private function pickFields(array $row, array $allowed): array
    {
        $out = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $row)) {
                $out[$key] = $row[$key];
            }
        }

        return $out;
    }
}
