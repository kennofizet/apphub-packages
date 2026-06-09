<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Launch\Services;

final class LaunchDeniedException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 403,
    ) {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
