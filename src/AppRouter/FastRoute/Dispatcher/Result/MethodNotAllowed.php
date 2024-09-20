<?php
declare(strict_types=1);

namespace Arris\AppRouter\FastRoute\Dispatcher\Result;

use ArrayAccess;
use Arris\AppRouter\FastRoute\Dispatcher;
use OutOfBoundsException;
use RuntimeException;

/** @implements ArrayAccess<int, Dispatcher::METHOD_NOT_ALLOWED|non-empty-list<string>> */
final class MethodNotAllowed implements ArrayAccess, ResultInterface
{
    /**
     * @readonly
     * @var non-empty-list<string> $allowedMethods
     */
    public array $allowedMethods;

    public function offsetExists($offset): bool
    {
        return $offset === 0 || $offset === 1;
    }

    public function offsetGet($offset)
    {
        /*return match ($offset) {
            0 => Dispatcher::METHOD_NOT_ALLOWED,
            1 => $this->allowedMethods,
            default => throw new OutOfBoundsException(),
        };*/
        switch ($offset) {
            case 0: {
                return Dispatcher::METHOD_NOT_ALLOWED;
                break;
            }
            case 1: {
                return $this->allowedMethods;
                break;
            }
            default: {
                throw new OutOfBoundsException();
            }
        }
    }

    public function offsetSet($offset, $value): void
    {
        throw new RuntimeException('Result cannot be changed');
    }

    public function offsetUnset($offset): void
    {
        throw new RuntimeException('Result cannot be changed');
    }
}
