<?php

namespace Arris\AppRouter\Tests;

use Arris\AppRouter;
use Arris\Exceptions\AppRouterNotFoundException;

class RouterExclusionTest extends AppRouterTestCase
{
    public function testRegexExclusionSkipsDispatch(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/storage/file.txt', 'REQUEST_METHOD' => 'GET']);

        $called = false;
        AppRouter::get('/storage/{file}', function () use (&$called): void {
            $called = true;
        });

        AppRouter::exclude(regexes: ['/storage/.*']);

        AppRouter::dispatch();

        $this->assertFalse($called);
    }

    public function testGlobExclusionSkipsDispatch(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/files/photo.jpg', 'REQUEST_METHOD' => 'GET']);

        $called = false;
        AppRouter::get('/files/{name}', function () use (&$called): void {
            $called = true;
        });

        AppRouter::exclude(globs: ['/files/*']);

        AppRouter::dispatch();

        $this->assertFalse($called);
    }

    public function testGlobDoubleStarMatchesNestedPath(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/storage/a/b/c.txt', 'REQUEST_METHOD' => 'GET']);

        $called = false;
        AppRouter::get('/storage/{path}', function () use (&$called): void {
            $called = true;
        });

        AppRouter::exclude(globs: ['/storage/**']);

        AppRouter::dispatch();

        $this->assertFalse($called);
    }

    public function testNonMatchingUriStillDispatches(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/public/index.html', 'REQUEST_METHOD' => 'GET']);

        $called = false;
        AppRouter::get('/public/{name}', function (string $name) use (&$called): void {
            $called = true;
        });

        AppRouter::exclude(globs: ['/storage/**']);

        AppRouter::dispatch();

        $this->assertTrue($called);
    }

    public function testNonMatchingRegexStillDispatches(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/api/users', 'REQUEST_METHOD' => 'GET']);

        $called = false;
        AppRouter::get('/api/{what}', function (string $what) use (&$called): void {
            $called = true;
        });

        AppRouter::exclude(regexes: ['/storage/.*']);

        AppRouter::dispatch();

        $this->assertTrue($called);
    }

    public function testExclusionSuppressesNotFoundException(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/storage/some/file', 'REQUEST_METHOD' => 'GET']);

        AppRouter::exclude(regexes: ['/storage/.*']);

        // не должно быть брошено AppRouterNotFoundException
        AppRouter::dispatch();

        $this->assertTrue(true);
    }

    public function testEmptyExclusionsDispatchNormally(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/', 'REQUEST_METHOD' => 'GET']);

        $called = false;
        AppRouter::get('/', function () use (&$called): void {
            $called = true;
        });

        AppRouter::exclude();

        AppRouter::dispatch();

        $this->assertTrue($called);
    }

    public function testExclusionsAccumulate(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/assets/css/style.css', 'REQUEST_METHOD' => 'GET']);

        $called = false;
        AppRouter::get('/assets/css/{file}', function () use (&$called): void {
            $called = true;
        });

        AppRouter::exclude(regexes: ['/storage/.*']);
        AppRouter::exclude(globs: ['/assets/**']);

        AppRouter::dispatch();

        $this->assertFalse($called);
    }

    public function testExclusionsResetOnInit(): void
    {
        $first = ['REQUEST_URI' => '/raw/path', 'REQUEST_METHOD' => 'GET'];
        $this->setUpRouter($first);

        $called = false;
        AppRouter::get('/raw/{p}', function (string $p) use (&$called): void {
            $called = true;
        });

        AppRouter::exclude(regexes: ['/raw/.*']);
        AppRouter::dispatch();
        $this->assertFalse($called);

        // повторная инициализация обнуляет исключения — роутинг снова работает
        $this->setUpRouter(['REQUEST_URI' => '/other/path', 'REQUEST_METHOD' => 'GET']);

        $called = false;
        AppRouter::get('/other/{p}', function (string $p) use (&$called): void {
            $called = true;
        });

        AppRouter::dispatch();
        $this->assertTrue($called);
    }
}
