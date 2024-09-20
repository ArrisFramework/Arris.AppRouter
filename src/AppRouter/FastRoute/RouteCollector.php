<?php

namespace Arris\AppRouter\FastRoute;

use Arris\AppRouter\FastRoute\ConfigureRoutes;
use Arris\AppRouter\FastRoute\GenerateUri;
use function array_key_exists;
use function array_reverse;
use function is_string;

/**
 * @phpstan-import-type ProcessedData from ConfigureRoutes
 * @phpstan-import-type ExtraParameters from DataGenerator
 * @phpstan-import-type RoutesForUriGeneration from GenerateUri
 * @phpstan-import-type ParsedRoutes from RouteParser
 * @final
 */
class RouteCollector implements ConfigureRoutes
{
    public const ALL_HTTP_METHODS = [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'HEAD',
        'OPTIONS'
    ];

    protected string $currentGroupPrefix = '';

    /** @var RoutesForUriGeneration */
    private array $namedRoutes = [];

    protected RouteParser $routeParser;

    protected DataGenerator $dataGenerator;

    public function __construct(
        RouteParser $routeParser,
        DataGenerator $dataGenerator
    ) {
        $this->routeParser = $routeParser;
        $this->dataGenerator = $dataGenerator;
    }

    /** @inheritDoc */
    public function addRoute($httpMethod, string $route, $handler, array $extraParameters = []): void
    {
        $route = $this->currentGroupPrefix . $route;
        $parsedRoutes = $this->routeParser->parse($route);

        $extraParameters = [self::ROUTE_REGEX => $route] + $extraParameters;

        foreach ((array) $httpMethod as $method) {
            foreach ($parsedRoutes as $parsedRoute) {
                $this->dataGenerator->addRoute($method, $parsedRoute, $handler, $extraParameters);
            }
        }

        if (array_key_exists(self::ROUTE_NAME, $extraParameters)) {
            $this->registerNamedRoute($extraParameters[self::ROUTE_NAME], $parsedRoutes);
        }
    }

    /** @param ParsedRoutes $parsedRoutes */
    private function registerNamedRoute($name, array $parsedRoutes): void
    {
        if (! is_string($name) || $name === '') {
            throw BadRouteException::invalidRouteName($name);
        }

        if (array_key_exists($name, $this->namedRoutes)) {
            throw BadRouteException::namedRouteAlreadyDefined($name);
        }

        $this->namedRoutes[$name] = array_reverse($parsedRoutes);
    }



    /**
     * Create a route group with a common prefix.
     *
     * All routes created at the passed callback will have the given group prefix prepended.
     *
     * @param string $prefix
     * @param callable $callback
     */
    public function addGroup(string $prefix, callable $callback):void
    {
        $previousGroupPrefix = $this->currentGroupPrefix;
        $this->currentGroupPrefix = $previousGroupPrefix . $prefix;
        $callback($this);
        $this->currentGroupPrefix = $previousGroupPrefix;
    }

    /**
     * Adds a GET route to the collection
     *
     * This is simply an alias of $this->addRoute('GET', $route, $handler)
     *
     * @param string $route
     * @param mixed $handler
     * @param array $extraParameters
     */
    public function get(string $route, $handler, array $extraParameters = []): void
    {
        $this->addRoute('GET', $route, $handler, $extraParameters);
    }

    /**
     * Adds a POST route to the collection
     *
     * This is simply an alias of $this->addRoute('POST', $route, $handler)
     *
     * @param string $route
     * @param mixed $handler
     * @param array $extraParameters
     */
    public function post(string $route, $handler, array $extraParameters = []): void
    {
        $this->addRoute('POST', $route, $handler, $extraParameters);
    }

    /**
     * Adds a PUT route to the collection
     *
     * This is simply an alias of $this->addRoute('PUT', $route, $handler)
     *
     * @param string $route
     * @param mixed $handler
     * @param array $extraParameters
     */
    public function put(string $route, $handler, array $extraParameters = []): void
    {
        $this->addRoute('PUT', $route, $handler, $extraParameters);
    }

    /**
     * Adds a DELETE route to the collection
     *
     * This is simply an alias of $this->addRoute('DELETE', $route, $handler)
     *
     * @param string $route
     * @param mixed $handler
     * @param array $extraParameters
     */
    public function delete(string $route, $handler, array $extraParameters = []): void
    {
        $this->addRoute('DELETE', $route, $handler, $extraParameters);
    }

    /**
     * Adds a PATCH route to the collection
     *
     * This is simply an alias of $this->addRoute('PATCH', $route, $handler)
     *
     * @param string $route
     * @param mixed $handler
     * @param array $extraParameters
     */
    public function patch(string $route, $handler, array $extraParameters = []): void
    {
        $this->addRoute('PATCH', $route, $handler, $extraParameters);
    }

    /**
     * Adds a HEAD route to the collection
     *
     * This is simply an alias of $this->addRoute('HEAD', $route, $handler)
     *
     * @param string $route
     * @param mixed $handler
     * @param array $extraParameters
     */
    public function head(string $route, $handler, array $extraParameters = []): void
    {
        $this->addRoute('HEAD', $route, $handler, $extraParameters);
    }

    /** @inheritDoc */
    public function options(string $route, $handler, array $extraParameters = []): void
    {
        $this->addRoute('OPTIONS', $route, $handler, $extraParameters);
    }

    /** @inheritDoc */
    public function processedRoutes(): array
    {
        $data =  $this->dataGenerator->getData();
        $data[] = $this->namedRoutes;

        return $data;
    }

    /**
     * @deprecated
     *
     * @see ConfigureRoutes::processedRoutes()
     *
     * @return ProcessedData
     */
    public function getData(): array
    {
        return $this->dataGenerator->getData();
    }

    public function any(string $route, $handler, array $extraParameters = []): void
    {
        foreach (self::ALL_HTTP_METHODS as $method) {
            $this->addRoute($method, $route, $handler, $extraParameters);
        }
    }
}
