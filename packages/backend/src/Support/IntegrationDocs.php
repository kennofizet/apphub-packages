<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Support;

final class IntegrationDocs
{
    public static function read(): array
    {
        $path = dirname(__DIR__) . '/Resources/integration-docs.json';
        if (!is_readable($path)) {
            throw new \RuntimeException('unavailable');
        }

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /** Publisher-safe subset — no host_dev, agent, or backend security. */
    public static function forPublisher(array $doc): array
    {
        $routes = array_values(array_filter(
            $doc['http']['routes'] ?? [],
            static fn (array $route): bool => in_array('publisher', $route['audience'] ?? [], true),
        ));

        return [
            'schema_version' => $doc['schema_version'] ?? '1.0.0',
            'package' => $doc['package'] ?? [],
            'audiences' => [
                'publisher' => $doc['audiences']['publisher'] ?? [],
            ],
            'http' => [
                'base_path' => $doc['http']['base_path'] ?? '',
                'routes' => $routes,
            ],
        ];
    }
}
