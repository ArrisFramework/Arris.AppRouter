<?php

namespace Arris\AppRouter\Tests;

use Arris\AppRouter;
use Arris\Exceptions\AppRouterHandlerError;
use Arris\Exceptions\AppRouterMethodNotAllowedException;
use Arris\Exceptions\AppRouterNotFoundException;

class RouterExceptionTest extends AppRouterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRouter();
    }

    public function testDispatchThrowsNotFoundException(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/does/not/exist', 'REQUEST_METHOD' => 'GET']);

        AppRouter::get('/exists', fn() => null);

        $this->expectException(AppRouterNotFoundException::class);
        AppRouter::dispatch();
    }

    public function testDispatchThrowsMethodNotAllowed(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/submit', 'REQUEST_METHOD' => 'GET']);

        AppRouter::post('/submit', fn() => null);

        $this->expectException(AppRouterMethodNotAllowedException::class);
        AppRouter::dispatch();
    }

    public function testNotFoundRequestData(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/missing', 'REQUEST_METHOD' => 'GET']);

        try {
            AppRouter::dispatch();
            $this->fail('Expected AppRouterNotFoundException');
        } catch (AppRouterNotFoundException $e) {
            $this->assertSame(404, $e->getCode());
        }
    }

    public function testMethodNotAllowedRequestData(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/submit', 'REQUEST_METHOD' => 'PUT']);

        AppRouter::post('/submit', fn() => null);

        try {
            AppRouter::dispatch();
            $this->fail('Expected AppRouterMethodNotAllowedException');
        } catch (AppRouterMethodNotAllowedException $e) {
            $this->assertSame(405, $e->getCode());
        }
    }

    public function testEmptyHandlerThrowsHandlerError(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/empty', 'REQUEST_METHOD' => 'GET']);

        AppRouter::get('/empty', []);

        $this->expectException(AppRouterHandlerError::class);
        AppRouter::dispatch();
    }
}
