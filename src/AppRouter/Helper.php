<?php

namespace Arris\AppRouter;

use Closure;

class Helper implements AppRouterHelperInterface
{
    public static function dumpRoutingRulesWeb(array $routingRules, bool $withMiddlewares = true, bool $withIcons = false, bool $withFooter = false): string
    {
        $table = "<table border='1' cellpadding='5' cellspacing='0' width='100%' style='width:100%; border-collapse: collapse;'>";
        $table .= "<thead>";

        $table .= "<tr>
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
                $handler = 'Closure at' . '<br>' . $route['backtrace']['file'] . '#L=' . $route['backtrace']['line'];
            } elseif (isset($route['handler']['__invoke'])) {
                $handler = 'Invokable Class';
            }

            // Name
            $name = $route['name'] ?? '--';

            // Before Middlewares
            $beforeMiddlewaresStr = $withMiddlewares ? self::formatMiddlewares($route['middlewares']['before']) : '-';

            // After Middlewares
            $afterMiddlewaresStr = $withMiddlewares ? self::formatMiddlewares($route['middlewares']['after']) : '-';

            $colorStyle = self::getMethodColor($route['httpMethod']);
            $methodIcon = $withIcons ? self::getMethodIcon($route['httpMethod']) : "";

            $table .= "<tr style='{$colorStyle}'>
            <td>{$methodIcon}{$route['httpMethod']}</td>
            <td>{$route['route']}</td>
            <td>{$handler}</td>
            <td>{$name}</td>";

            if ($withMiddlewares) {
                $table .= "<td>{$beforeMiddlewaresStr}</td>
            <td>{$afterMiddlewaresStr}</td>";
            }
            $table .= "</tr>";
        }

        if ($withFooter) {
            $table .= "<tfoot><tr>
        <th style='font-size: small'>Method</th>
        <th>Route</th>
        <th>Handler</th>
        <th>Name</th>";

            if ($withMiddlewares) {
                $table .= "<th>Before Middlewares</th>
        <th>After Middlewares</th>";
            }

            $table .= "</tr></tfoot>";
        }

        $table .= "</tbody></table>";
        return $table;
    }

    /**
     * Стилизация строки для dumpRoutingRulesWeb
     *
     * @param string $method
     * @return string
     */
    private static function getMethodColor(string $method): string
    {
        return match(strtoupper($method)) {
            'GET'    => 'background: #E6F7FF; color: #003366;',
            'POST'   => 'background: #E8F5E9; color: #1B5E20;',
            'PUT',
            'PATCH'  => 'background: #FFF3E0; color: #E65100;', // Один стиль для PUT и PATCH
            'DELETE' => 'background: #FFEBEE; color: #C62828;',
            default  => 'background: #F5F5F5; color: #212121;'
        };
    }

    /**
     * Форматирует миддлвары в строку
     *
     * @param Stack|null $middlewares
     * @param string $separator
     * @param string $default
     * @return string
     */
    private static function formatMiddlewares(\Arris\AppRouter\Stack $middlewares = null, string $separator = '<br>', string $default = '-'):string
    {
        $result = [];
        if ($middlewares instanceof \Arris\AppRouter\Stack) {
            foreach ($middlewares->toArray() as $middleware) {
                if (is_array($middleware)) {
                    $result[] = $middleware[0] . "@" . ( $middleware[1] ?? '__invoke' );
                }
                if (is_callable($middleware)) {
                    $result[] = 'Closure';
                }
            }
        }
        return implode($separator, $result) ?: $default;
    }

    /**
     * Иконка для метода
     *
     * @param string $method
     * @return string
     */
    private static function getMethodIcon(string $method): string {
        $icons = [
            'GET'    => '🔵 ',
            'POST'   => '🟢 ',
            'DELETE' => '🔴 '
        ];
        return $icons[strtoupper($method)] ?? '';
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