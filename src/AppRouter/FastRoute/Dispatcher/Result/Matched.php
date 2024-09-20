<?php
declare(strict_types=1);

namespace Arris\AppRouter\FastRoute\Dispatcher\Result;

use ArrayAccess;
use Arris\AppRouter\FastRoute\DataGenerator;
use Arris\AppRouter\FastRoute\Dispatcher;
use OutOfBoundsException;
use RuntimeException;

/**
 * @phpstan-import-type ExtraParameters from DataGenerator
 * @implements ArrayAccess<int, Dispatcher::FOUND|mixed|array<string, string>>
 */
final class Matched implements ArrayAccess, ResultInterface
{
    /** @readonly */
    public $handler;

    /**
     * @readonly
     * @var array<string, string> $variables
     */
    public array $variables = [];

    /**
     * @readonly
     * @var ExtraParameters
     */
    public array $extraParameters = [];

    public function offsetExists($offset): bool
    {
        return $offset >= 0 && $offset <= 2;
    }

    public function offsetGet($offset)
    {
        switch ($offset) {
            case 0: {
                return Dispatcher::FOUND;
                break;
            }
            case 1: {
                return $this->handler;
                break;
            }
            case 2: {
                return $this->variables;
                break;
            }
            case 3: {
                return $this->extraParameters;
                break;
            }
            default: {
                throw new OutOfBoundsException();
            }
        }

        /*
        return match ($offset) {
            0 => Dispatcher::FOUND,
            1 => $this->handler,
            2 => $this->variables,
            default => throw new OutOfBoundsException()
        };*/
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
