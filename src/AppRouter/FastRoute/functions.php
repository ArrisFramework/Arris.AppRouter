<?php

namespace Arris\AppRouter\FastRoute;

if (!function_exists('Arris\AppRouter\FastRoute\simpleDispatcher')) {
    /**
     * @param callable $routeDefinitionCallback
     * @param array $options
     *
     * @return Dispatcher
     */
    function simpleDispatcher(callable $routeDefinitionCallback, array $options = [])
    {
        $options += [
            'routeParser' => 'Arris\\AppRouter\\FastRoute\\RouteParser\\Std',
            'dataGenerator' => 'Arris\\AppRouter\\FastRoute\\DataGenerator\\GroupCountBased',
            'dispatcher' => 'Arris\\AppRouter\\FastRoute\\Dispatcher\\GroupCountBased',
            'routeCollector' => 'Arris\\AppRouter\\FastRoute\\RouteCollector',
        ];

        /** @var RouteCollector $routeCollector */
        $routeCollector = new $options['routeCollector'](
            new $options['routeParser'], new $options['dataGenerator']
        );
        $routeDefinitionCallback($routeCollector);

        return new $options['dispatcher']($routeCollector->getData());
    }

    /**
     * @param callable $routeDefinitionCallback
     * @param array $options
     *
     * @return Dispatcher
     */
    function cachedDispatcher(callable $routeDefinitionCallback, array $options = [])
    {
        $options += [
            'routeParser' => '\\Arris\\AppRouter\\FastRoute\\RouteParser\\Std',
            'dataGenerator' => '\\Arris\\AppRouter\\FastRoute\\DataGenerator\\GroupCountBased',
            'dispatcher' => '\\Arris\\AppRouter\\FastRoute\\Dispatcher\\GroupCountBased',
            'routeCollector' => '\\Arris\\AppRouter\\FastRoute\\RouteCollector',
            'cacheDisabled' => false,
        ];

        if (!isset($options['cacheFile'])) {
            throw new \LogicException('Must specify "cacheFile" option');
        }

        if (!$options['cacheDisabled'] && \file_exists($options['cacheFile'])) {
            $dispatchData = require $options['cacheFile'];
            if (!\is_array($dispatchData)) {
                throw new \RuntimeException('Invalid cache file "' . $options['cacheFile'] . '"');
            }
            return new $options['dispatcher']($dispatchData);
        }

        $routeCollector = new $options['routeCollector'](
            new $options['routeParser'], new $options['dataGenerator']
        );
        $routeDefinitionCallback($routeCollector);

        /** @var RouteCollector $routeCollector */
        $dispatchData = $routeCollector->getData();
        if (!$options['cacheDisabled']) {
            \file_put_contents(
                $options['cacheFile'],
                '<?php return ' . var_export($dispatchData, true) . ';'
            );
        }

        return new $options['dispatcher']($dispatchData);
    }
}
