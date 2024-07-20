<?php

namespace Arris\AppRouter\FastRoute\Dispatcher;

use Arris\AppRouter\FastRoute\Dispatcher\RegexBasedAbstract;

class GroupPosBased extends RegexBasedAbstract
{
    public function __construct($data)
    {
        list($this->staticRouteMap, $this->variableRouteData) = $data;
    }

    protected function dispatchVariableRoute($routeData, $uri)
    {
        foreach ($routeData as $data) {
            if (!preg_match($data['regex'], $uri, $matches)) {
                continue;
            }

            // find first non-empty match
            // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedFor
            for ($i = 1; '' === $matches[$i]; ++$i) { };

            list($handler, $varNames) = $data['routeMap'][$i];

            $vars = [];
            foreach ($varNames as $varName) {
                $vars[$varName] = $matches[$i++];
            }
            return [self::FOUND, $handler, $vars];
        }

        return [self::NOT_FOUND];
    }
}
