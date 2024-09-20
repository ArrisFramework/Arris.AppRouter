<?php
declare(strict_types=1);

namespace Arris\AppRouter\FastRoute\Dispatcher\Result;

use ArrayAccess;
use Arris\AppRouter\FastRoute\Dispatcher;
use OutOfBoundsException;
use RuntimeException;

/** @implements ArrayAccess<int, Dispatcher::NOT_FOUND> */
final class NotMatched implements ArrayAccess, ResultInterface
{
    public function offsetExists($offset): bool
    {
        return $offset === 0;
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        /*return match ($offset) {
            0 => Dispatcher::NOT_FOUND,
            default => throw new OutOfBoundsException(),
        };*/
        switch ($offset) {
            case 0: {
                return Dispatcher::NOT_FOUND;
                break;
            }
            default: {
                throw new OutOfBoundsException();
                break;
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
