<?php

namespace Arris\AppRouter\Tests;

use Arris\AppRouter;

class RouterGroupTest extends AppRouterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRouter();
    }

    public function testGroupPrefixApplied(): void
    {
        AppRouter::group('/api', callback: function (): void {
            AppRouter::get('/users', fn() => null);
        });

        $rules = AppRouter::getRoutingRules();
        $this->assertCount(1, $rules);

        $rule = reset($rules);
        $this->assertSame('/api/users', $rule['route']);
    }

    public function testNestedGroups(): void
    {
        AppRouter::group('/api', callback: function (): void {
            AppRouter::group('/v1', callback: function (): void {
                AppRouter::get('/users', fn() => null);
            });
        });

        $rules = AppRouter::getRoutingRules();
        $this->assertCount(1, $rules);

        $rule = reset($rules);
        $this->assertSame('/api/v1/users', $rule['route']);
    }

    public function testPrefixRestoredAfterGroup(): void
    {
        AppRouter::get('/before', fn() => null);

        AppRouter::group('/api', callback: function (): void {
            AppRouter::get('/inner', fn() => null);
        });

        AppRouter::get('/after', fn() => null);

        $routes = array_column(AppRouter::getRoutingRules(), 'route');
        sort($routes);

        $this->assertSame(['/after', '/api/inner', '/before'], $routes);
    }

    public function testNamespaceApplied(): void
    {
        AppRouter::group(namespace: 'App\\Handlers', callback: function (): void {
            AppRouter::get('/users', 'UsersHandler');
        });

        $rules = AppRouter::getRoutingRules();
        $this->assertCount(1, $rules);

        $rule = reset($rules);
        $this->assertSame('App\\Handlers', $rule['namespace']);
        $this->assertSame('/users', $rule['route']);
    }

    public function testNamespaceRestoredAfterGroup(): void
    {
        AppRouter::group(namespace: 'App\\Handlers', callback: function (): void {
            AppRouter::get('/inner', 'InnerHandler');
        });

        AppRouter::get('/outer', 'OuterHandler');

        $rules = array_column(AppRouter::getRoutingRules(), 'namespace');
        sort($rules);

        $this->assertSame(['', 'App\\Handlers'], $rules);
    }

    public function testExceptionInGroupRestoresPrefix(): void
    {
        // регрессия: раньше при исключении в callback префикс не восстанавливался
        $this->expectException(\RuntimeException::class);

        AppRouter::group('/api', callback: function (): void {
            throw new \RuntimeException('boom');
        });
    }

    public function testPrefixRestoredAfterGroupException(): void
    {
        // регрессия: после исключения в группе следующий роут не должен наследовать префикс группы
        try {
            AppRouter::group('/api', callback: function (): void {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
            // ожидаемо
        }

        AppRouter::get('/after', fn() => null);

        $rules = AppRouter::getRoutingRules();
        $this->assertCount(1, $rules);

        $rule = reset($rules);
        $this->assertSame('/after', $rule['route']);
    }

    public function testPrefixRestoredAfterNestedGroupException(): void
    {
        try {
            AppRouter::group('/api', callback: function (): void {
                AppRouter::group('/v1', callback: function (): void {
                    throw new \RuntimeException('boom');
                });
            });
        } catch (\RuntimeException) {
            // ожидаемо
        }

        AppRouter::get('/after', fn() => null);

        $rules = AppRouter::getRoutingRules();
        $this->assertCount(1, $rules);

        $rule = reset($rules);
        $this->assertSame('/after', $rule['route']);
    }

    public function testMiddlewareBeforeGroupAppliedToInnerRoutes(): void
    {
        $order = [];
        $middleware = function () use (&$order): void {
            $order[] = 'middleware';
        };

        $this->setUpRouter(['REQUEST_URI' => '/api/ping', 'REQUEST_METHOD' => 'GET']);

        AppRouter::group('/api', before: $middleware, callback: function () use (&$order): void {
            AppRouter::get('/ping', function () use (&$order): void {
                $order[] = 'handler';
            });
        });

        AppRouter::dispatch();

        $this->assertSame(['middleware', 'handler'], $order);
    }

    public function testEmptyGroupWithoutCallbackAndOption(): void
    {
        $result = AppRouter::group('/api', callback: null);

        $this->assertFalse($result);
    }
}
