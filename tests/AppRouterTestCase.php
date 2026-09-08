<?php

namespace Arris\AppRouter\Tests;

use Arris\AppRouter;
use PHPUnit\Framework\TestCase;

abstract class AppRouterTestCase extends TestCase
{
    /**
     * Полный сброс статического состояния AppRouter между тестами.
     *
     * init() обнуляет не всё: $rules, $route_names, $dispatcher, $routeInfo,
     * $instances, $current_namespace, $current_prefix и прочие накопленные
     * свойства переживают повторный вызов init(). Поэтому после init()
     * добиваем оставшееся через Reflection.
     *
     * @param array $serverData эмулятор $_SERVER (REQUEST_URI, REQUEST_METHOD)
     */
    protected function setUpRouter(array $serverData = ['REQUEST_URI' => '/', 'REQUEST_METHOD' => 'GET']): void
    {
        AppRouter::init(customDataSource: $serverData);

        $reflection = new \ReflectionClass(AppRouter::class);

        $defaults = [
            'dispatcher'                 => null,
            'rules'                      => [],
            'current_namespace'          => '',
            'current_prefix'             => '',
            'routeInfo'                  => null,
            'instances_middlewares'      => [],
            'instances'                  => [],
            'routeRule'                  => [],
            'options'                    => [],
            'option_allow_empty_groups'  => false,
            'option_allow_empty_handlers'=> false,
            'option_use_aliases'         => false,
            'option_collapse_slashes'    => false,
        ];

        foreach ($defaults as $name => $value) {
            $property = $reflection->getProperty($name);
            $property->setValue(null, $value);
        }

        AppRouter::$route_names = [];
        AppRouter::$stack_aliases = [];
        AppRouter::$route_parts = [];
    }
}
