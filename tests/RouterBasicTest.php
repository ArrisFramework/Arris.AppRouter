<?php

namespace Arris\AppRouter\Tests;

use Arris\AppRouter;

class RouterBasicTest extends AppRouterTestCase
{
    public function testSimpleGetRouteDispatches(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/hello', 'REQUEST_METHOD' => 'GET']);

        $called = false;
        AppRouter::get('/hello', function () use (&$called): void {
            $called = true;
        });

        AppRouter::dispatch();

        $this->assertTrue($called);
    }

    public function testRouteWithNamedParameter(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/user/wombat', 'REQUEST_METHOD' => 'GET']);

        $captured = null;
        AppRouter::get('/user/{name}', function (string $name) use (&$captured): void {
            $captured = $name;
        });

        AppRouter::dispatch();

        $this->assertSame('wombat', $captured);
    }

    public function testRouteWithMultipleParameters(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/post/42/edit', 'REQUEST_METHOD' => 'GET']);

        $captured = [];
        AppRouter::get('/post/{id}/edit', function (string $id) use (&$captured): void {
            $captured[] = $id;
        });

        AppRouter::dispatch();

        $this->assertSame(['42'], $captured);
    }

    public function testPostMethodRouting(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/submit', 'REQUEST_METHOD' => 'POST']);

        $called = false;
        AppRouter::post('/submit', function () use (&$called): void {
            $called = true;
        });

        AppRouter::dispatch();

        $this->assertTrue($called);
    }

    public function testUriWithoutLeadingSlash(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/foo/bar', 'REQUEST_METHOD' => 'GET']);

        $called = false;
        AppRouter::get('/foo/bar', function () use (&$called): void {
            $called = true;
        });

        AppRouter::dispatch();

        $this->assertTrue($called);
    }

    public function testUriWithQueryStringIsStripped(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/hello?foo=bar&baz=qux', 'REQUEST_METHOD' => 'GET']);

        $called = false;
        AppRouter::get('/hello', function () use (&$called): void {
            $called = true;
        });

        AppRouter::dispatch();

        $this->assertTrue($called);
    }

    public function testUriWithTrailingSlash(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/hello/', 'REQUEST_METHOD' => 'GET']);

        $called = false;
        AppRouter::get('/hello[/]', function () use (&$called): void {
            $called = true;
        });

        AppRouter::dispatch();

        $this->assertTrue($called);
    }

    public function testDefaultRequestUriWhenMissing(): void
    {
        $this->setUpRouter(['REQUEST_METHOD' => 'GET']);

        $called = false;
        AppRouter::get('/', function () use (&$called): void {
            $called = true;
        });

        AppRouter::dispatch();

        $this->assertTrue($called);
    }
}
