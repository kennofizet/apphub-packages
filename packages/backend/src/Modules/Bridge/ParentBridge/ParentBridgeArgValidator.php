<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\ParentBridge;

final class ParentBridgeArgValidator
{
    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null
     */
    public function validateArgs(array $schema, array $input): ?array
    {
        if ($this->encodedSize($input) > ParentBridgeRegistry::maxArgsBytes()) {
            return null;
        }

        $out = [];

        foreach ($schema as $key => $rule) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            if (!is_array($rule)) {
                continue;
            }

            $hasKey = array_key_exists($key, $input);
            $optional = ($rule['optional'] ?? false) === true;
            $required = ($rule['required'] ?? false) === true || !$optional;

            if (!$hasKey) {
                if ($required) {
                    return null;
                }
                continue;
            }

            $value = $this->validateValue($rule, $input[$key]);
            if ($value === null && $required) {
                return null;
            }

            if ($value !== null || $hasKey) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null
     */
    public function validatePayload(array $schema, array $input): ?array
    {
        return $this->validateArgs($schema, $input);
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function validateValue(array $rule, mixed $value): mixed
    {
        $type = strtolower(trim((string) ($rule['type'] ?? 'string')));

        return match ($type) {
            'integer' => $this->validateInteger($rule, $value),
            'boolean' => $this->validateBoolean($value),
            'object' => $this->validateObject($rule, $value),
            'pagination_search' => $this->validatePaginationSearch($rule, $value),
            default => $this->validateString($rule, $value),
        };
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function validateString(array $rule, mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $string = trim((string) $value);
        $max = (int) ($rule['max'] ?? 2048);
        if ($max > 0 && mb_strlen($string) > $max) {
            return null;
        }

        $pattern = $rule['pattern'] ?? null;
        if (is_string($pattern) && $pattern !== '' && preg_match($pattern, $string) !== 1) {
            return null;
        }

        return $string;
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function validateInteger(array $rule, mixed $value): ?int
    {
        if (!is_int($value) && !is_numeric($value)) {
            return null;
        }

        $int = (int) $value;
        if (isset($rule['min']) && $int < (int) $rule['min']) {
            return null;
        }
        if (isset($rule['max']) && $int > (int) $rule['max']) {
            return null;
        }

        return $int;
    }

    private function validateBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }
        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function validateObject(array $rule, mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $fields = $rule['fields'] ?? null;
        if (!is_array($fields)) {
            return $value;
        }

        $nestedSchema = [];
        foreach ($fields as $field) {
            if (is_string($field) && $field !== '') {
                $nestedSchema[$field] = ['type' => 'string', 'optional' => true];
            }
        }

        return $this->validateArgs($nestedSchema, $value);
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function validatePaginationSearch(array $rule, mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $maxPerPage = max(1, min(100, (int) ($rule['max_per_page'] ?? 100)));
        $page = isset($value['page']) ? $this->validateInteger(['min' => 1, 'max' => 10_000], $value['page']) : 1;
        $perPage = isset($value['per_page'])
            ? $this->validateInteger(['min' => 1, 'max' => $maxPerPage], $value['per_page'])
            : min(20, $maxPerPage);

        if ($page === null || $perPage === null) {
            return null;
        }

        $out = [
            'page' => $page,
            'per_page' => $perPage,
        ];

        if (isset($value['search'])) {
            $search = $this->validateString(['max' => 255], $value['search']);
            if ($search !== null && $search !== '') {
                $out['search'] = $search;
            }
        }

        if (isset($value['sort'])) {
            $sort = $this->validateString(['max' => 64, 'pattern' => '/^[a-zA-Z0-9_.:-]+$/'], $value['sort']);
            if ($sort !== null && $sort !== '') {
                $out['sort'] = $sort;
            }
        }

        if (isset($value['columns']) && is_array($value['columns'])) {
            $columns = [];
            foreach ($value['columns'] as $column) {
                $col = $this->validateString(['max' => 64, 'pattern' => '/^[a-zA-Z0-9_.-]+$/'], $column);
                if ($col !== null && $col !== '') {
                    $columns[] = $col;
                }
                if (count($columns) >= 32) {
                    break;
                }
            }
            if ($columns !== []) {
                $out['columns'] = $columns;
            }
        }

        if (isset($value['filters']) && is_array($value['filters'])) {
            $filters = [];
            foreach ($value['filters'] as $filterKey => $filterValue) {
                if (!is_string($filterKey) || $filterKey === '') {
                    continue;
                }
                $key = $this->validateString(['max' => 64, 'pattern' => '/^[a-zA-Z0-9_.-]+$/'], $filterKey);
                if ($key === null) {
                    continue;
                }
                if (is_bool($filterValue)) {
                    $filters[$key] = $filterValue;
                } elseif (is_int($filterValue) || is_float($filterValue)) {
                    $filters[$key] = $filterValue;
                } else {
                    $stringValue = $this->validateString(['max' => 255], $filterValue);
                    if ($stringValue !== null) {
                        $filters[$key] = $stringValue;
                    }
                }
                if (count($filters) >= 20) {
                    break;
                }
            }
            if ($filters !== []) {
                $out['filters'] = $filters;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $value
     */
    private function encodedSize(array $value): int
    {
        $json = json_encode($value);

        return $json === false ? PHP_INT_MAX : strlen($json);
    }
}
