<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\ParentBridge;

use Illuminate\Support\Facades\RateLimiter;
use Kennofizet\AppHub\Modules\Bridge\Services\AppBridgeConsentService;
use Kennofizet\AppHub\Modules\Bridge\ParentBridge\Contracts\ParentBridgePermissionChecker;
use Kennofizet\AppHub\Modules\Bridge\Support\ParentBridgeManifest;
use Kennofizet\AppHub\Modules\Catalog\Models\App;
use Kennofizet\AppHub\Modules\Catalog\Services\AppCatalogService;
use Kennofizet\AppHub\Modules\Catalog\Services\AppVersionService;
use Kennofizet\AppHub\Modules\Catalog\Support\AppSemver;
use Kennofizet\AppHub\Modules\Launch\Services\LaunchTokenService;
use Kennofizet\PackagesCore\Models\User;

final class ParentBridgeSecurityGate
{
    private const SLUG_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

    public function __construct(
        private readonly AppCatalogService $catalog,
        private readonly AppVersionService $versions,
        private readonly AppBridgeConsentService $consents,
        private readonly LaunchTokenService $launchTokens,
        private readonly ParentBridgePermissionChecker $permissionChecker,
        private readonly ParentBridgeAuditLogger $audit,
    ) {
    }

    /**
     * @param array<string, mixed> $args
     * @return array{ok: false, error: string, message: string}|null
     */
    public function authorizeCall(User $user, int $userId, string $action, array $args, string $appSlug, string $bridgeScope, ?string $sessionId): ?array
    {
        $gate = $this->authorizeCommon($user, $userId, $appSlug, $bridgeScope, $sessionId, $action, null, $args);
        if ($gate !== null) {
            return $gate;
        }

        $config = ParentBridgeRegistry::action($action);
        if (!is_array($config)) {
            return $this->error('NOT_IMPLEMENTED', 'Unknown action');
        }

        return $this->authorizeConfigEntry($user, $userId, $config, $bridgeScope, [
            'app_slug' => $appSlug,
            'bridge_scope' => $bridgeScope,
            'action' => $action,
            'session_id' => $sessionId,
        ], 'handler', ParentBridgeRegistry::rateLimitForAction($action), 'call', $action);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: false, error: string, message: string}|null
     */
    public function authorizeEvent(User $user, int $userId, string $name, array $payload, string $appSlug, string $bridgeScope, ?string $sessionId): ?array
    {
        $gate = $this->authorizeCommon($user, $userId, $appSlug, $bridgeScope, $sessionId, null, $name, $payload);
        if ($gate !== null) {
            return $gate;
        }

        $config = ParentBridgeRegistry::event($name);
        if (!is_array($config)) {
            return $this->error('NOT_IMPLEMENTED', 'Unknown event');
        }

        return $this->authorizeConfigEntry($user, $userId, $config, $bridgeScope, [
            'app_slug' => $appSlug,
            'bridge_scope' => $bridgeScope,
            'event' => $name,
            'session_id' => $sessionId,
        ], 'listener', ParentBridgeRegistry::rateLimitForEvent($name), 'event', $name);
    }

    /**
     * @param array<string, mixed>|null $argsOrPayload
     * @return array{ok: false, error: string, message: string}|null
     */
    private function authorizeCommon(
        User $user,
        int $userId,
        string $appSlug,
        string $bridgeScope,
        ?string $sessionId,
        ?string $action,
        ?string $event,
        ?array $argsOrPayload,
    ): ?array {
        if (!ParentBridgeRegistry::isEnabled()) {
            return $this->error('NOT_IMPLEMENTED', 'Parent bridge disabled');
        }

        if (!preg_match(self::SLUG_PATTERN, $appSlug)) {
            return $this->error('VALIDATION_ERROR', 'Invalid app slug');
        }

        $scope = trim($bridgeScope);
        if ($scope === '') {
            return $this->error('VALIDATION_ERROR', 'bridge_scope required');
        }

        if ($this->encodedSize($argsOrPayload ?? []) > ParentBridgeRegistry::maxArgsBytes()) {
            return $this->error('VALIDATION_ERROR', 'Payload exceeds maximum size');
        }

        $app = $this->catalog->findBySlug($appSlug);
        if ($app === null) {
            return $this->error('FORBIDDEN', 'App not found');
        }

        if (!$this->catalog->userCanLaunch($app, $userId, $this->zoneIds())) {
            return $this->error('FORBIDDEN', 'You cannot use this app');
        }

        $bundleVersion = $this->resolveSessionBundleVersion($userId, $appSlug, $sessionId, $app);
        $manifestBlock = ParentBridgeManifest::normalizeBlock(
            $this->versions->manifestForLaunchBundle($app, $bundleVersion),
        );
        $manifestEntry = $action !== null
            ? ParentBridgeManifest::findActionInBlock($manifestBlock, $action)
            : ParentBridgeManifest::findEventInBlock($manifestBlock, (string) $event);

        if ($manifestEntry === null) {
            return $this->error('ACTION_NOT_ALLOWED', 'Not declared in app manifest');
        }

        if ($manifestEntry['scope'] !== $scope) {
            return $this->error('SCOPE_NOT_GRANTED', 'bridge_scope mismatch');
        }

        if (!$this->consents->userHasScope($app, $userId, $scope, $bundleVersion)) {
            return $this->error('SCOPE_NOT_GRANTED', 'Install consent required');
        }

        $sessionError = $this->validateSession($userId, $appSlug, $scope, $sessionId);
        if ($sessionError !== null) {
            return $sessionError;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $permissionContext
     * @return array{ok: false, error: string, message: string}|null
     */
    private function authorizeConfigEntry(
        User $user,
        int $userId,
        array $config,
        string $bridgeScope,
        array $permissionContext,
        string $handlerKey,
        int $rateLimit,
        string $auditKind,
        string $auditName,
    ): ?array {
        if (($config['enabled'] ?? true) === false) {
            return $this->error('NOT_IMPLEMENTED', 'Disabled');
        }

        $configScope = trim((string) ($config['bridge_scope'] ?? ''));
        if ($configScope === '' || $configScope !== $bridgeScope) {
            return $this->error('SCOPE_NOT_GRANTED', 'bridge_scope mismatch');
        }

        $permission = $config['permission'] ?? null;
        if (ParentBridgeRegistry::requirePermissionInProduction()
            && ($permission === null || $permission === '')) {
            return $this->error('FORBIDDEN', 'Permission required in production');
        }

        if (!$this->permissionChecker->can($user, is_string($permission) ? $permission : null, $permissionContext)) {
            return $this->error('FORBIDDEN', 'Permission denied');
        }

        $handlerClass = $config[$handlerKey] ?? null;
        if (!is_string($handlerClass) || trim($handlerClass) === '') {
            return $this->error('NOT_IMPLEMENTED', 'No handler configured');
        }

        if (!ParentBridgeHandlerGuard::isAllowedClass($handlerClass)) {
            return $this->error('FORBIDDEN', 'Handler class not allowed');
        }

        if (!$this->checkRateLimit($userId, $auditKind, $auditName, $rateLimit)) {
            return $this->error('RATE_LIMITED', 'Too many requests');
        }

        $this->audit->log($auditKind, [
            'user_id' => $userId,
            'name' => $auditName,
            'app_slug' => $permissionContext['app_slug'] ?? null,
            'bridge_scope' => $bridgeScope,
            'session_id' => $permissionContext['session_id'] ?? null,
        ]);

        return null;
    }

    private function resolveSessionBundleVersion(
        int $userId,
        string $appSlug,
        ?string $sessionId,
        App $app,
    ): ?string {
        $sessionId = $sessionId !== null ? trim($sessionId) : '';
        if ($sessionId !== '') {
            $record = $this->launchTokens->findActiveSessionForUser($userId, $appSlug, $sessionId);
            if ($record !== null) {
                $fromSession = $record->bundle_version !== null
                    ? trim((string) $record->bundle_version)
                    : '';

                if ($fromSession !== '') {
                    return AppSemver::normalize($fromSession) ?: $fromSession;
                }
            }
        }

        $fromApp = AppSemver::normalize((string) ($app->version ?? ''));

        return $fromApp !== '' ? $fromApp : null;
    }

    /**
     * @return array{ok: false, error: string, message: string}|null
     */
    private function validateSession(int $userId, string $appSlug, string $scope, ?string $sessionId): ?array
    {
        $sessionId = $sessionId !== null ? trim($sessionId) : '';
        $requireSession = ParentBridgeRegistry::requireActiveSession();

        if ($sessionId === '') {
            return $requireSession
                ? $this->error('FORBIDDEN', 'Active launch session required')
                : null;
        }

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $sessionId)) {
            return $this->error('VALIDATION_ERROR', 'Invalid session_id');
        }

        $record = $this->launchTokens->findActiveSessionForUser($userId, $appSlug, $sessionId);
        if ($record === null) {
            return $this->error('FORBIDDEN', 'Launch session not active');
        }

        if (!$this->launchTokens->sessionHasScope($record, $scope)) {
            return $this->error('SCOPE_NOT_GRANTED', 'Scope not on launch session');
        }

        return null;
    }

    private function checkRateLimit(int $userId, string $kind, string $name, int $limit): bool
    {
        $key = sprintf('parent-bridge:%d:%s:%s', $userId, $kind, strtolower($name));

        return RateLimiter::attempt($key, $limit, static fn (): bool => true, 60);
    }

    /**
     * @return list<int>
     */
    private function zoneIds(): array
    {
        return AppCatalogService::normalizeUserZoneIds(
            request()->attributes->get('knf_core_user_zone_ids', []),
        );
    }

    /**
     * @param array<string, mixed> $value
     */
    private function encodedSize(array $value): int
    {
        $json = json_encode($value);

        return $json === false ? PHP_INT_MAX : strlen($json);
    }

    /**
     * @return array{ok: false, error: string, message: string}
     */
    private function error(string $code, string $message): array
    {
        return [
            'ok' => false,
            'error' => $code,
            'message' => $message,
        ];
    }
}
