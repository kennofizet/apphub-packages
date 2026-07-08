<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Modules\Bridge\ParentBridge;

use Kennofizet\PackagesCore\Models\User;
use Kennofizet\AppHub\Modules\Bridge\ParentBridge\Contracts\ParentBridgeAction;
use Kennofizet\AppHub\Modules\Bridge\ParentBridge\Contracts\ParentBridgeEventListener;

final class ParentBridgeDispatcher
{
    public function __construct(
        private readonly ParentBridgeArgValidator $validator,
        private readonly ParentBridgeResponseShaper $shaper,
    ) {
    }

    /**
     * @param array<string, mixed> $args
     * @return array{ok: bool, result?: mixed, error?: string, message?: string}
     */
    public function dispatchCall(User $user, string $action, array $args): array
    {
        $config = ParentBridgeRegistry::action($action);
        if ($config === null) {
            return $this->error('NOT_IMPLEMENTED', 'Unknown action');
        }

        $validatedArgs = $this->validator->validateArgs(
            is_array($config['args'] ?? null) ? $config['args'] : [],
            $args,
        );
        if ($validatedArgs === null) {
            return $this->error('VALIDATION_ERROR', 'Invalid arguments');
        }

        $handlerClass = $config['handler'] ?? null;
        if (!is_string($handlerClass) || trim($handlerClass) === '') {
            return $this->error('NOT_IMPLEMENTED', 'No handler configured');
        }

        $handler = app($handlerClass);
        if (!$handler instanceof ParentBridgeAction) {
            return $this->error('NOT_IMPLEMENTED', 'Invalid handler');
        }

        $result = $handler->handle($user, $validatedArgs);
        $shaped = $this->shaper->shape(
            is_array($config['returns'] ?? null) ? $config['returns'] : [],
            $result,
        );

        return ['ok' => true, 'result' => $shaped];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, error?: string, message?: string}
     */
    public function dispatchEvent(User $user, string $name, array $payload): array
    {
        $config = ParentBridgeRegistry::event($name);
        if ($config === null) {
            return $this->error('NOT_IMPLEMENTED', 'Unknown event');
        }

        $validatedPayload = $this->validator->validatePayload(
            is_array($config['payload'] ?? null) ? $config['payload'] : [],
            $payload,
        );
        if ($validatedPayload === null) {
            return $this->error('VALIDATION_ERROR', 'Invalid payload');
        }

        $listenerClass = $config['listener'] ?? null;
        if (!is_string($listenerClass) || trim($listenerClass) === '') {
            return $this->error('NOT_IMPLEMENTED', 'No listener configured');
        }

        $listener = app($listenerClass);
        if (!$listener instanceof ParentBridgeEventListener) {
            return $this->error('NOT_IMPLEMENTED', 'Invalid listener');
        }

        $listener->handle($user, $validatedPayload);

        return ['ok' => true];
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
