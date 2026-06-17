<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Tests\Unit\Core;

use Kennofizet\AppHub\Core\Model\BaseModelResponse;
use PHPUnit\Framework\TestCase;

final class BaseModelResponseTest extends TestCase
{
    public function test_success_envelope(): void
    {
        $this->assertSame(
            ['success' => true, 'data' => ['slug' => 'demo']],
            BaseModelResponse::success(['slug' => 'demo']),
        );
    }

    public function test_error_envelope(): void
    {
        $this->assertSame(
            ['success' => false, 'error' => 'Denied'],
            BaseModelResponse::error('Denied'),
        );
    }
}
