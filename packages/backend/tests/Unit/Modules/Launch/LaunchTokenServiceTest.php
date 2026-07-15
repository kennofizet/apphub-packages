<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Tests\Unit\Modules\Launch;

use Kennofizet\AppHub\Modules\Launch\Services\LaunchTokenService;
use PHPUnit\Framework\TestCase;

final class LaunchTokenServiceTest extends TestCase
{
    public function test_hash_token_is_stable_sha256_hex(): void
    {
        $service = new LaunchTokenService();
        $plain = 'abc123def456ghi789jkl012mno345pqr678stu901vwx234yzab567cde890fgh';

        $this->assertSame(hash('sha256', $plain), $service->hashToken($plain));
        $this->assertSame(64, strlen($service->hashToken($plain)));
    }

    public function test_has_scope_checks_granted_list(): void
    {
        $service = new LaunchTokenService();

        $this->assertTrue($service->hasScope(['scopes_granted' => ['user.read']], 'user.read'));
        $this->assertFalse($service->hasScope(['scopes_granted' => []], 'user.read'));
    }

    public function test_has_user_read_access_accepts_read_or_profile(): void
    {
        $service = new LaunchTokenService();

        $this->assertTrue($service->hasUserReadAccess(['scopes_granted' => ['user.read']]));
        $this->assertTrue($service->hasUserReadAccess(['scopes_granted' => ['user.profile']]));
        $this->assertFalse($service->hasUserReadAccess(['scopes_granted' => ['desktop.notify']]));
    }

    public function test_find_session_for_refresh_rejects_invalid_session_id(): void
    {
        $service = new LaunchTokenService();

        $this->assertNull($service->findSessionForRefresh(1, 'demo-app', 'not-a-uuid'));
        $this->assertNull($service->findSessionForRefresh(0, 'demo-app', '11111111-1111-4111-8111-111111111111'));
    }
}
