<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Core\Model;

/**
 * App Hub JSON envelope — { success, data } / { success, error }.
 * Mirrors packages-core response helpers but keeps Hub frontend contract.
 */
final class BaseModelResponse
{
    public static function success(mixed $data = null): array
    {
        return [
            'success' => true,
            'data' => $data ?? [],
        ];
    }

    public static function error(string $error, ?array $data = null): array
    {
        $payload = [
            'success' => false,
            'error' => $error,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return $payload;
    }
}
