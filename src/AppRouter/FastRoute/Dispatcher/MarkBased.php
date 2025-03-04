<?php

namespace Arris\AppRouter\FastRoute\Dispatcher;

use Arris\AppRouter\FastRoute\Dispatcher\Result\Matched;
use function preg_match;

/** @final */
class MarkBased extends RegexBasedAbstract
{
    /** @inheritDoc */
    protected function dispatchVariableRoute(array $routeData, string $uri): ?Matched
    {
        foreach ($routeData as $data) {
            if (preg_match($data['regex'], $uri, $matches) !== 1) {
                continue;
            }

            [$handler, $varNames, $extraParameters] = $data['routeMap'][$matches['MARK']];

            $vars = [];
            $i = 0;
            foreach ($varNames as $varName) {
                $vars[$varName] = $matches[++$i];
            }

            $result = new Matched();
            $result->handler = $handler;
            $result->variables = $vars;
            $result->extraParameters = $extraParameters;

            return $result;
        }

        return null;
    }
}
