<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\ParentBridge;

use Kennofizet\AppHub\Modules\Bridge\Support\ParentBridgeManifest;

/** Demo parent-bridge payloads from host config — used while DEV has not approved parent scopes. */
final class ParentBridgeDemoFixtures
{
    /**
     * Demo payloads for actions declared in the child manifest and configured on the host.
     *
     * @param array<string, mixed>|null $manifest
     * @return array<string, mixed> action name => demo payload
     */
    public static function forManifest(?array $manifest): array
    {
        $block = ParentBridgeManifest::normalizeBlock($manifest);
        if ($block['actions'] === []) {
            return [];
        }

        $out = [];
        foreach ($block['actions'] as $row) {
            $name = strtolower(trim((string) ($row['name'] ?? '')));
            if ($name === '') {
                continue;
            }

            $demo = self::forAction($name);
            if ($demo !== null) {
                $out[$name] = $demo;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|list<array<string, mixed>>|null
     */
    public static function forAction(string $action): mixed
    {
        if (ParentBridgeRegistry::action($action) === null) {
            return null;
        }

        $demo = ParentBridgeRegistry::demoDataFor($action);
        if ($demo === null) {
            return null;
        }

        if (!is_array($demo)) {
            return $demo;
        }

        return self::markDemo(self::cloneValue($demo));
    }

    /**
     * @return mixed
     */
    private static function cloneValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = self::cloneValue($item);
            }

            return $out;
        }

        return $value;
    }

    /**
     * @param array<string, mixed>|list<array<string, mixed>> $value
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    private static function markDemo(array $value): array
    {
        if (array_is_list($value)) {
            $out = [];
            foreach ($value as $row) {
                $out[] = is_array($row) ? self::markDemoRow($row) : $row;
            }

            return $out;
        }

        if (!isset($value['_demo_fixture'])) {
            $value['_demo_fixture'] = true;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function markDemoRow(array $row): array
    {
        if (!isset($row['_demo_fixture'])) {
            $row['_demo_fixture'] = true;
        }

        return $row;
    }
}
