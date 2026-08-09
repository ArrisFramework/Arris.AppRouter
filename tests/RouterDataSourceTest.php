<?php

namespace Arris\AppRouter\Tests;

use Arris\AppRouter;

class RouterDataSourceTest extends AppRouterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRouter();
    }

    public function testInitWithCustomDataSource(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/hello/world', 'REQUEST_METHOD' => 'GET']);

        $captured = null;
        AppRouter::get('/hello/{name}', function (string $name) use (&$captured): void {
            $captured = $name;
        });

        AppRouter::dispatch();

        $this->assertSame('world', $captured);
    }

    public function testInitWithMethodDataSource(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/submit', 'REQUEST_METHOD' => 'DELETE']);

        $called = false;
        AppRouter::delete('/submit', function () use (&$called): void {
            $called = true;
        });

        AppRouter::dispatch();

        $this->assertTrue($called);
    }

    public function testInitDefaultsToGetMethod(): void
    {
        // customDataSource без REQUEST_METHOD - должен дефолтиться в GET
        $this->setUpRouter(['REQUEST_URI' => '/hello']);

        $called = false;
        AppRouter::get('/hello', function () use (&$called): void {
            $called = true;
        });

        AppRouter::dispatch();

        $this->assertTrue($called);
    }

    public function testConstructorWithCustomDataSource(): void
    {
        new AppRouter(
            customDataSource: ['REQUEST_URI' => '/ctor/route', 'REQUEST_METHOD' => 'GET']
        );

        $captured = null;
        AppRouter::get('/ctor/{name}', function (string $name) use (&$captured): void {
            $captured = $name;
        });

        AppRouter::dispatch();

        $this->assertSame('route', $captured);
    }

    public function testRoutingInfoAvailableAfterDispatch(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/post/7', 'REQUEST_METHOD' => 'GET']);

        AppRouter::get('/post/{id}', function (string $id): void {
        });

        AppRouter::dispatch();

        $info = AppRouter::getRoutingInfo();

        $this->assertSame(1, $info[0]);
        $this->assertSame('7', $info[2]['id']);
    }

    public function testGetRoutersNames(): void
    {
        AppRouter::get('/a', fn() => null, 'a');
        AppRouter::get('/b', fn() => null, 'b');

        $names = AppRouter::getRoutersNames();

        $this->assertArrayHasKey('a', $names);
        $this->assertArrayHasKey('b', $names);
        $this->assertSame('/a', $names['a']);
    }

    public function testSetDefaultNamespace(): void
    {
        AppRouter::setDefaultNamespace('App\\Controllers');

        AppRouter::get('/home', 'HomeController');

        $rules = AppRouter::getRoutingRules();
        $rule = reset($rules);

        $this->assertSame('App\\Controllers', $rule['namespace']);
    }

    public function testSetDefaultNamespaceAppliedOnDispatch(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/home', 'REQUEST_METHOD' => 'GET']);

        AppRouter::setDefaultNamespace(__NAMESPACE__);

        AppRouter::get('/home', 'Handler@home');

        AppRouter::dispatch();

        $this->assertTrue(Handler::$called);
    }
}
