<?php

namespace Arris\AppRouter\Tests;

class Handler
{
    public static bool $called = false;

    public static function home(): void
    {
        self::$called = true;
    }
}
