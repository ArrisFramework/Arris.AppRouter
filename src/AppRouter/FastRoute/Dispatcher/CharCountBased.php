<?php

namespace Arris\AppRouter\FastRoute\Dispatcher;

use Arris\AppRouter\FastRoute\Dispatcher\Result\Matched;
use function assert;
use function end;
use function preg_match;

/** @final */
class CharCountBased extends RegexBasedAbstract
{
    public function __construct($data)
    {
        parent::__construct($data);
    }

    protected function dispatchVariableRoute(array $routeData, string $uri): ?Matched
    {
        foreach ($routeData as $data) {
            assert(isset($data['suffix']));

            if (preg_match($data['regex'], $uri . $data['suffix'], $matches) !== 1) {
                continue;
            }

            [$handler, $varNames, $extraParameters] = $data['routeMap'][end($matches)];

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
