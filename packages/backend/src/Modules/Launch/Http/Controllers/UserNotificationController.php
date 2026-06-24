<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kennofizet\AppHub\Http\Controllers\Controller;
use Kennofizet\AppHub\Modules\Launch\Services\UserNotificationService;
use RuntimeException;

class UserNotificationController extends Controller
{
    public function __construct(private readonly UserNotificationService $notifications)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $userId = (int) self::currentUserId();
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $cursor = $request->query('cursor');
        $cursor = is_string($cursor) && $cursor !== '' ? $cursor : null;

        $result = $this->notifications->cursorInboxForUser($userId, $cursor, $perPage);

        return $this->apiSuccessWithMeta($result['items'], $result['meta']);
    }

    public function summary(): JsonResponse
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $userId = (int) self::currentUserId();

        return $this->apiResponseWithContext([
            'unread_count' => $this->notifications->unreadCountForUser($userId),
        ]);
    }

    public function dismiss(Request $request): JsonResponse
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'integer|min:1',
        ]);

        $userId = (int) self::currentUserId();
        $updated = $this->notifications->dismissForUser($userId, $validated['ids']);

        return $this->apiResponseWithContext([
            'dismissed' => $updated,
            'unread_count' => $this->notifications->unreadCountForUser($userId),
        ]);
    }

    public function readAll(): JsonResponse
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $userId = (int) self::currentUserId();
        $updated = $this->notifications->dismissAllForUser($userId);

        return $this->apiResponseWithContext([
            'dismissed' => $updated,
            'unread_count' => 0,
        ]);
    }
}
