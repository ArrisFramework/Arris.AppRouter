<?php

namespace Arris\AppRouter\FastRoute\DataGenerator;

use Arris\AppRouter\FastRoute\DataGenerator\RegexBasedAbstract;
use function count;
use function implode;

class CharCountBased extends RegexBasedAbstract
{
    protected function getApproxChunkSize():int
    {
        return 30;
    }

    protected function processChunk(array $regexToRoutesMap): array
    {
        $routeMap = [];
        $regexes = [];

        $suffixLen = 0;
        $suffix = '';
        $count = count($regexToRoutesMap);
        foreach ($regexToRoutesMap as $regex => $route) {
            $suffixLen++;
            $suffix .= "\t";

            $regexes[] = '(?:' . $regex . '/(\t{' . $suffixLen . '})\t{' . ($count - $suffixLen) . '})';
            $routeMap[$suffix] = [$route->handler, $route->variables, $route->extraParameters];
        }

        $regex = '~^(?|' . implode('|', $regexes) . ')$~';

        return ['regex' => $regex, 'suffix' => '/' . $suffix, 'routeMap' => $routeMap];
    }
}
