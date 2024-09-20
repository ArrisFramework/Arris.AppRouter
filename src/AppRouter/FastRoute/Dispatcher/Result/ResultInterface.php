<?php

namespace Arris\AppRouter\FastRoute\Dispatcher\Result;

interface ResultInterface
{
    public function offsetExists($offset): bool;

    #[\ReturnTypeWillChange]
    public function offsetGet($offset);

    public function offsetSet($offset, $value): void;

    public function offsetUnset($offset): void;
}