<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Tests\Unit\Modules\Catalog;

use Kennofizet\AppHub\Modules\Catalog\Support\AppStatus;
use PHPUnit\Framework\TestCase;

final class AppStatusTest extends TestCase
{
    public function test_can_launch_active_and_draft_only(): void
    {
        $this->assertTrue(AppStatus::canLaunch(AppStatus::ACTIVE));
        $this->assertTrue(AppStatus::canLaunch(AppStatus::DRAFT));
        $this->assertFalse(AppStatus::canLaunch(AppStatus::DISABLED));
    }

    public function test_store_listed_is_active_only(): void
    {
        $this->assertTrue(AppStatus::isStoreListed(AppStatus::ACTIVE));
        $this->assertFalse(AppStatus::isStoreListed(AppStatus::DRAFT));
    }

    public function test_is_valid_rejects_unknown_status(): void
    {
        $this->assertTrue(AppStatus::isValid(AppStatus::DRAFT));
        $this->assertFalse(AppStatus::isValid('archived'));
    }
}
