<?php

namespace Arris\AppRouter\Tests;

use Arris\AppRouter;
use Arris\Exceptions\AppRouterNotFoundException;

class RouterCollapseSlashTest extends AppRouterTestCase
{
    public function testDoubleSlashNotMatchedByDefault(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/tavern//add', 'REQUEST_METHOD' => 'GET']);

        $called = false;
        AppRouter::get('/tavern/add', function () use (&$called): void {
            $called = true;
        });

        $this->expectException(AppRouterNotFoundException::class);
        AppRouter::dispatch();
    }

    public function testDoubleSlashCollapsedWhenOptionEnabled(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/tavern//add', 'REQUEST_METHOD' => 'GET']);
        AppRouter::setOption(AppRouter::OPTION_COLLAPSE_DOUBLE_SLASHES, true);

        $called = false;
        AppRouter::get('/tavern/add', function () use (&$called): void {
            $called = true;
        });

        AppRouter::dispatch();

        $this->assertTrue($called);
    }

    public function testSingleSlashUnaffectedWhenOptionEnabled(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/tavern/add', 'REQUEST_METHOD' => 'GET']);
        AppRouter::setOption(AppRouter::OPTION_COLLAPSE_DOUBLE_SLASHES, true);

        $called = false;
        AppRouter::get('/tavern/add', function () use (&$called): void {
            $called = true;
        });

        AppRouter::dispatch();

        $this->assertTrue($called);
    }

    public function testMultipleSlashesCollapsedToSingle(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/tavern///add', 'REQUEST_METHOD' => 'GET']);
        AppRouter::setOption(AppRouter::OPTION_COLLAPSE_DOUBLE_SLASHES, true);

        $called = false;
        AppRouter::get('/tavern/add', function () use (&$called): void {
            $called = true;
        });

        AppRouter::dispatch();

        $this->assertTrue($called);
    }

    public function testCollapseWorksWithExclusions(): void
    {
        $this->setUpRouter(['REQUEST_URI' => '/storage//file.txt', 'REQUEST_METHOD' => 'GET']);
        AppRouter::setOption(AppRouter::OPTION_COLLAPSE_DOUBLE_SLASHES, true);

        $called = false;
        AppRouter::get('/storage/{file}', function () use (&$called): void {
            $called = true;
        });

        AppRouter::exclude(globs: ['/storage/**']);

        // схлопнутый URI /storage/file.txt попадает под исключение — тихий return
        AppRouter::dispatch();

        $this->assertFalse($called);
    }
}
