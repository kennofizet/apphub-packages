<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\ParentBridge;

final class ParentBridgeHandlerGuard
{
    public static function isAllowedClass(string $class): bool
    {
        $class = trim($class);
        if ($class === '' || !class_exists($class)) {
            return false;
        }

        $allowed = ParentBridgeRegistry::allowedHandlerNamespaces();
        if ($allowed === []) {
            return true;
        }

        foreach ($allowed as $prefix) {
            if (!is_string($prefix) || $prefix === '') {
                continue;
            }
            if (str_starts_with($class, rtrim($prefix, '\\') . '\\') || $class === rtrim($prefix, '\\')) {
                return true;
            }
        }

        return false;
    }
}
