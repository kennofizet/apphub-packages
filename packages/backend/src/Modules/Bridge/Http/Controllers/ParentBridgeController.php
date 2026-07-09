<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kennofizet\AppHub\Http\Controllers\Controller;
use Kennofizet\AppHub\Modules\Bridge\ParentBridge\ParentBridgeCatalog;
use Kennofizet\AppHub\Modules\Bridge\ParentBridge\ParentBridgeDispatcher;
use Kennofizet\AppHub\Modules\Bridge\ParentBridge\ParentBridgeRegistry;
use Kennofizet\AppHub\Modules\Bridge\ParentBridge\ParentBridgeSecurityGate;
use Kennofizet\PackagesCore\Core\Model\BaseModelActions;

class ParentBridgeController extends Controller
{
    public function __construct(
        private readonly ParentBridgeDispatcher $dispatcher,
        private readonly ParentBridgeSecurityGate $security,
    ) {
    }

    public function catalog(): JsonResponse
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        return response()->json(ParentBridgeCatalog::forHost());
    }

    /** Install-dialog prompts for parent.* scopes (from host config). */
    public function scopePrompts(): JsonResponse
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        return $this->apiResponseWithContext([
            'prompts' => ParentBridgeRegistry::scopePrompts(),
        ]);
    }

    public function call(Request $request): JsonResponse
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $validated = $request->validate([
            'action' => 'required|string|max:128',
            'args' => 'nullable|array',
            'app_slug' => ['required', 'string', 'max:64', 'regex:' . self::SLUG_PATTERN],
            'bridge_scope' => 'required|string|max:128',
            'session_id' => 'nullable|uuid',
        ]);

        $user = BaseModelActions::currentUser();
        $userId = (int) (BaseModelActions::currentUserId() ?? 0);
        if ($user === null || $userId < 1) {
            return $this->apiErrorResponse('Authentication required', 401);
        }

        $denied = $this->security->authorizeCall(
            $user,
            $userId,
            $validated['action'],
            is_array($validated['args'] ?? null) ? $validated['args'] : [],
            $validated['app_slug'],
            $validated['bridge_scope'],
            isset($validated['session_id']) ? (string) $validated['session_id'] : null,
        );
        if ($denied !== null) {
            return response()->json($denied, $this->statusForError((string) ($denied['error'] ?? '')));
        }

        $result = $this->dispatcher->dispatchCall(
            $user,
            $validated['action'],
            is_array($validated['args'] ?? null) ? $validated['args'] : [],
        );

        return response()->json($result, ($result['ok'] ?? false) === true ? 200 : $this->statusForError((string) ($result['error'] ?? '')));
    }

    public function event(Request $request): JsonResponse
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:128',
            'payload' => 'nullable|array',
            'app_slug' => ['required', 'string', 'max:64', 'regex:' . self::SLUG_PATTERN],
            'bridge_scope' => 'required|string|max:128',
            'session_id' => 'nullable|uuid',
        ]);

        $user = BaseModelActions::currentUser();
        $userId = (int) (BaseModelActions::currentUserId() ?? 0);
        if ($user === null || $userId < 1) {
            return $this->apiErrorResponse('Authentication required', 401);
        }

        $denied = $this->security->authorizeEvent(
            $user,
            $userId,
            $validated['name'],
            is_array($validated['payload'] ?? null) ? $validated['payload'] : [],
            $validated['app_slug'],
            $validated['bridge_scope'],
            isset($validated['session_id']) ? (string) $validated['session_id'] : null,
        );
        if ($denied !== null) {
            return response()->json($denied, $this->statusForError((string) ($denied['error'] ?? '')));
        }

        $result = $this->dispatcher->dispatchEvent(
            $user,
            $validated['name'],
            is_array($validated['payload'] ?? null) ? $validated['payload'] : [],
        );

        return response()->json($result, ($result['ok'] ?? false) === true ? 200 : $this->statusForError((string) ($result['error'] ?? '')));
    }

    private function statusForError(string $code): int
    {
        return match ($code) {
            'ACTION_NOT_ALLOWED', 'SCOPE_NOT_GRANTED', 'FORBIDDEN' => 403,
            'VALIDATION_ERROR' => 422,
            'RATE_LIMITED' => 429,
            default => 501,
        };
    }
}
