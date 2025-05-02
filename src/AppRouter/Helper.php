<?php

namespace Arris\AppRouter;

use Closure;

class Helper implements AppRouterHelperInterface
{
    public static function dumpRoutingRulesWeb(array $routingRules, bool $withMiddlewares = true): string
    {
        $table = "<table border='1' cellpadding='5' cellspacing='0' width='100%'>";

        $table .= "<thead><tr>
        <th style='font-size: small'>Method</th>
        <th>Route</th>
        <th>Handler</th>
        <th>Name</th>";

        if ($withMiddlewares) {
            $table .= "<th>Before Middlewares</th>
        <th>After Middlewares</th>";
        }

        $table .= "</tr></thead><tbody>";

        foreach ($routingRules as $route) {
            // HTTP Method + Route
            $methodRoute = "{$route['httpMethod']} {$route['route']}";

            // Handler
            $handler = '';
            if (is_array($route['handler']) && count($route['handler']) === 2) {
                $handler = "{$route['handler'][0]}@{$route['handler'][1]}";
            } elseif ($route['handler'] instanceof Closure) {
                $handler = 'Closure' . '<br>' . $route['backtrace']['file'] . '#L=' . $route['backtrace']['line'];
            } elseif (isset($route['handler']['__invoke'])) {
                $handler = 'Invokable Class';
            }

            // Name
            $name = $route['name'] ?? '--';

            // Before Middlewares
            $beforeMiddlewares = [];

            if ($withMiddlewares) {
                $beforeMiddlewaresStack = $route['middlewares']['before']; /* @var \Arris\AppRouter\Stack $beforeMiddlewaresStack */

                if ($beforeMiddlewaresStack instanceof \Arris\AppRouter\Stack) {
                    foreach ($beforeMiddlewaresStack->toArray() as $middleware) {
                        if (is_array($middleware) && count($middleware) === 2) {
                            $beforeMiddlewares[] = "{$middleware[0]}@{$middleware[1]}";
                        }
                    } // foreach
                } // if instanceOf
            } // if $withMiddlewares

            $beforeMiddlewaresStr = implode('<br>', $beforeMiddlewares) ?: '-';

            // After Middlewares
            $afterMiddlewares = [];

            if ($withMiddlewares) {
                $afterMiddlewaresStack = $route['middlewares']['after']; /* @var \Arris\AppRouter\Stack $afterMiddlewaresStack */
                if ($afterMiddlewaresStack instanceof \Arris\AppRouter\Stack) {
                    foreach ($afterMiddlewaresStack->toArray() as $middleware) {
                        if (is_array($middleware)) {
                            $afterMiddlewares[] = "{$middleware[0]}@{$middleware[1]}";
                        }
                    } // foreach
                } // if instanceOf
            } // if $withMiddlewares

            $afterMiddlewaresStr = implode('<br>', $afterMiddlewares) ?: '-';

            $table .= "<tr>
            <td>{$route['httpMethod']}</td>
            <td>{$route['route']}</td>
            <td>{$handler}</td>
            <td>{$name}</td>";

            if ($withMiddlewares) {
                $table .= "<td>{$beforeMiddlewaresStr}</td>
            <td>{$afterMiddlewaresStr}</td>";
            }
            $table .= "</tr>";
        }

        $table .= "</tbody></table>";
        return $table;
    }

    public static function dumpRoutingRulesCLI(array $routingRules, bool $withMiddlewares = false):string
    {
        $output = "HTTP Method + Route\tHandler\tName\tBefore Middlewares\tAfter Middlewares\n";
        $output .= str_repeat("-", 150) . "\n";

        foreach ($routingRules as $route) {
            // HTTP Method + Route
            $methodRoute = "{$route['httpMethod']} {$route['route']}";

            // Handler
            $handler = '';
            if (is_array($route['handler']) && count($route['handler']) === 2) {
                $handler = "{$route['handler'][0]}@{$route['handler'][1]}";
            } elseif ($route['handler'] instanceof Closure) {
                $handler = 'Closure';
            } elseif (isset($route['handler']['__invoke'])) {
                $handler = 'Invokable Class';
            }

            // Name
            $name = $route['name'] ?? 'NULL';

            // Before Middlewares
            $beforeMiddlewares = [];
            if ($withMiddlewares) {
                $beforeMiddlewaresStack = $route['middlewares']['before']; /* @var \Arris\AppRouter\Stack $beforeMiddlewaresStack */

                if ($beforeMiddlewaresStack instanceof \Arris\AppRouter\Stack) {
                    foreach ($beforeMiddlewaresStack->toArray() as $middleware) {
                        if (is_array($middleware) && count($middleware) === 2) {
                            $beforeMiddlewares[] = "{$middleware[0]}@{$middleware[1]}";
                        }
                    } // foreach
                } // if instanceOf
            } // if $withMiddlewares

            $beforeMiddlewaresStr = implode(', ', $beforeMiddlewares) ?: 'None';

            // After Middlewares
            $afterMiddlewares = [];
            if ($withMiddlewares) {
                $afterMiddlewaresStack = $route['middlewares']['after']; /* @var \Arris\AppRouter\Stack $afterMiddlewaresStack */

                if ($afterMiddlewaresStack instanceof \Arris\AppRouter\Stack) {
                    foreach ($afterMiddlewaresStack->toArray() as $middleware) {
                        if (is_array($middleware)) {
                            $afterMiddlewares[] = "{$middleware[0]}@{$middleware[1]}";
                        }
                    } // foreach
                } // if instanceOf
            } // if $withMiddlewares

            $afterMiddlewaresStr = implode(', ', $afterMiddlewares) ?: 'None';

            $output .= sprintf("%-40s %-40s %-15s %-30s %-30s\n",
                $methodRoute,
                $handler,
                $name,
                $beforeMiddlewaresStr,
                $afterMiddlewaresStr
            );
        }

        return $output;
    }

    public static function explode($income, array $default = [ null, '__invoke' ], string $separator = '@'): array
    {
        return array_map(static function($first, $second) {
            return empty($second) ? $first : $second;
        }, $default, \explode($separator, $income));
    }

    public static function getClosureInternalName($closure): string
    {
        $name = "Closure(";

        try {
            $reflected = new \ReflectionFunction($closure);
            $args = implode(':',
                // создаем статичную функцию и сразу вызываем
                (static function ($r) {
                    return
                        array_map(
                        // обработчик
                            static function($v)
                            {
                                return is_object($v) ? $v->name : $v;
                            },
                            // входной массив
                            array_merge(
                                $r->getParameters(),
                                array_keys(
                                    $r->getStaticVariables()
                                )
                            )
                        );
                })
                ($reflected) // а это её аргумент
            ); // эта скобочка относится к implode
            // "value:value2:s1:s2"
            $name .= $reflected->getStartLine() . "-" . $reflected->getEndLine() . ")=" . $args;
        } catch (\ReflectionException $e) {
            $name .= md5(mt_rand(1, PHP_MAXPATHLEN)) . ")=.";
        }

        return $name;
    }

}