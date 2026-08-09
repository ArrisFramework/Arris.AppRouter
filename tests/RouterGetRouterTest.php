<?php

namespace Arris\AppRouter\Tests;

use Arris\AppRouter;

class RouterGetRouterTest extends AppRouterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRouter();
    }

    public function testSimpleNamedRoute(): void
    {
        AppRouter::get('/user/{name}', fn() => null, 'user_profile');

        $this->assertSame('/user/{name}', AppRouter::getRouter('user_profile'));
    }

    public function testGetRouterWithParts(): void
    {
        AppRouter::get('/user/{name}', fn() => null, 'user_profile');

        $this->assertSame('/user/wombat', AppRouter::getRouter('user_profile', ['name' => 'wombat']));
    }

    public function testPartsValueWithDollarIsNotTreatedAsBackreference(): void
    {
        // регрессия: раньше preg_replace($pattern, $value, $route) интерпретировал '$1' как backreference
        AppRouter::get('/user/{name}', fn() => null, 'user_profile');

        $this->assertSame('/user/x$1y', AppRouter::getRouter('user_profile', ['name' => 'x$1y']));
    }

    public function testPartsValueWithBackslashIsPreserved(): void
    {
        AppRouter::get('/user/{name}', fn() => null, 'user_profile');

        $this->assertSame('/user/a\\b', AppRouter::getRouter('user_profile', ['name' => 'a\\b']));
    }

    public function testPlaceholderWithRegexTypeIsReplaced(): void
    {
        // регрессия: плейсхолдер {name:\w+} раньше не заменялся из-за сломанного regex-паттерна
        AppRouter::get('/user/{name:\w+}', fn() => null, 'user_profile');

        $this->assertSame('/user/wombat', AppRouter::getRouter('user_profile', ['name' => 'wombat']));
    }

    public function testMissingPartsLeavePlaceholderIntact(): void
    {
        AppRouter::get('/user/{name}', fn() => null, 'user_profile');

        $this->assertSame('/user/{name}', AppRouter::getRouter('user_profile', ['other' => 'x']));
    }

    public function testOptionalSlashReplacedToMandatory(): void
    {
        AppRouter::get('/user/{name}[/]', fn() => null, 'user_profile');

        $this->assertSame('/user/wombat/', AppRouter::getRouter('user_profile', ['name' => 'wombat']));
    }

    public function testEmptyNameReturnsDefaultRoute(): void
    {
        $this->assertSame('/', AppRouter::getRouter(''));
    }

    public function testUnknownNameReturnsDefaultRoute(): void
    {
        $this->assertSame('/', AppRouter::getRouter('nonexistent'));
    }

    public function testWildcardReturnsAllNamedRoutes(): void
    {
        AppRouter::get('/a', fn() => null, 'route_a');
        AppRouter::get('/b', fn() => null, 'route_b');

        $all = AppRouter::getRouter('*');

        $this->assertArrayHasKey('route_a', $all);
        $this->assertArrayHasKey('route_b', $all);
        $this->assertSame('/a', $all['route_a']);
    }
}
