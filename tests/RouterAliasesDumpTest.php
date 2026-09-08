<?php

namespace Arris\AppRouter\Tests;

use Arris\AppRouter;
use Arris\AppRouter\Helper;

class RouterAliasesDumpTest extends AppRouterTestCase
{
    public function testDumpAliasesCLI(): void
    {
        $this->setUpRouter();

        AppRouter::addAlias('id', '\d+');
        AppRouter::addAlias('slug', '[a-z-]+');

        $output = Helper::dumpAliasesCLI(AppRouter::getAliases());

        $this->assertStringContainsString('id', $output);
        $this->assertStringContainsString('\d+', $output);
        $this->assertStringContainsString('slug', $output);
        $this->assertStringContainsString('[a-z-]+', $output);
    }

    public function testDumpAliasesWeb(): void
    {
        $this->setUpRouter();

        AppRouter::addAlias('id', '\d+');

        $output = Helper::dumpAliasesWeb(AppRouter::getAliases());

        $this->assertStringContainsString('<table', $output);
        $this->assertStringContainsString('id', $output);
        $this->assertStringContainsString('\d+', $output);
    }

    public function testDumpAliasesCLIEmpty(): void
    {
        $this->setUpRouter();

        $output = Helper::dumpAliasesCLI(AppRouter::getAliases());

        $this->assertStringContainsString('none', $output);
    }

    public function testDumpAliasesWebEmpty(): void
    {
        $this->setUpRouter();

        $output = Helper::dumpAliasesWeb(AppRouter::getAliases());

        $this->assertStringContainsString('<table', $output);
    }

    public function testAliasesStoredViaArrayForm(): void
    {
        $this->setUpRouter();

        AppRouter::addAlias(['id' => '\d+', 'slug' => '[a-z-]+']);

        $this->assertCount(2, AppRouter::getAliases());
        $output = Helper::dumpAliasesCLI(AppRouter::getAliases());
        $this->assertStringContainsString('id', $output);
        $this->assertStringContainsString('slug', $output);
    }
}
