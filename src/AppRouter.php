<?php

namespace Arris;

use Arris\AppRouter\Stack;
use Arris\Exceptions\AppRouterHandlerError;
use Arris\Exceptions\AppRouterMethodNotAllowedException;
use Arris\Exceptions\AppRouterNotFoundException;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class AppRouter implements AppRouterInterface
{
    public const ALL_HTTP_METHODS = [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'HEAD'
    ];

    /**
     * @var Dispatcher
     */
    private static $dispatcher;

    /**
     * @var array
     */
    private static $rules;

    /**
     * @var string
     */
    private static $current_namespace = '';

    /**
     * @var LoggerInterface
     */
    private static $logger;

    /**
     * @var
     */
    private static $httpMethod;

    /**
     * @var string
     */
    private static $uri;

    /**
     * @var array
     */
    public static $route_names;

    /**
     * @var string
     */
    private static $current_prefix;

    /**
     * Current Routing Info
     *
     * @var array
     */
    private static array $routeInfo;

    private static Stack $stack_prefix;

    private static Stack $stack_namespace;

    private static Stack $stack_middlewares_before;

    private static $current_middleware_before = null;

    private static Stack $stack_middlewares_after;

    private static $current_middleware_after = null;

    private static $middlewares_namespace = '';

    public static function init(LoggerInterface $logger = null, array $options = [])
    {
        self::$logger
            = ($logger instanceof LoggerInterface)
            ? $logger
            : new NullLogger();

        self::$httpMethod = $_SERVER['REQUEST_METHOD'];

        $uri = $_SERVER['REQUEST_URI'];
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        self::$uri = rawurldecode($uri);

        if (array_key_exists('defaultNamespace', $options)) {
            self::setDefaultNamespace($options['defaultNamespace']);
        } elseif (array_key_exists('namespace', $options)) {
            self::setDefaultNamespace($options['namespace']);
        }

        if (array_key_exists('prefix', $options)) {
            self::$current_prefix = $options['prefix'];
        }

        self::$stack_prefix = new Stack();

        self::$stack_namespace = new Stack();

        self::$stack_middlewares_before = new Stack();

        self::$stack_middlewares_after = new Stack();

        // self::$stack_aliases = new Stack();
    }

    public static function setDefaultNamespace(string $namespace = '')
    {
        self::$current_namespace = $namespace;
    }

    /**
     * @todo: Указывает нэймспейс для миддлваров-посредников
     *
     * @param string $namespace
     * @return void
     */
    public static function setMiddlewaresNamespace(string $namespace = '')
    {
        self::$middlewares_namespace = $namespace;
    }

    public static function get($route, $handler, $name = null)
    {
        $httpMethod = 'GET';
        $key = self::getInternalRuleKey($httpMethod, $handler);

        if (!is_null($name)) {
            self::$route_names[$name] = self::$current_prefix . $route;
        }

        self::$rules[ $key ] = [
            'httpMethod'    =>  $httpMethod,
            'route'         =>  self::$current_prefix . $route,
            'handler'       =>  $handler,
            'namespace'     =>  self::$current_namespace,
            'name'          =>  $name,
            'middlewares'   =>  [
                'before'    =>  clone self::$stack_middlewares_before,
                'after'     =>  clone self::$stack_middlewares_after
            ]
        ];
    }

    public static function post($route, $handler, $name = null)
    {
        $httpMethod = 'POST';
        $key = self::getInternalRuleKey($httpMethod, $handler);

        if (!is_null($name)) {
            self::$route_names[$name] = self::$current_prefix . $route;
        }

        self::$rules[ $key ] = [
            'httpMethod'    =>  $httpMethod,
            'route'         =>  self::$current_prefix . $route,
            'handler'       =>  $handler,
            'namespace'     =>  self::$current_namespace,
            'name'          =>  $name,
            'middlewares'   =>  [
                'before'    =>  clone self::$stack_middlewares_before,
                'after'     =>  clone self::$stack_middlewares_after
            ]
        ];
    }

    public static function put($route, $handler, $name = null)
    {
        $httpMethod = 'PUT';
        $key = self::getInternalRuleKey($httpMethod, $handler);

        if (!is_null($name)) {
            self::$route_names[$name] = self::$current_prefix . $route;
        }

        self::$rules[ $key ] = [
            'httpMethod'    =>  $httpMethod,
            'route'         =>  self::$current_prefix . $route,
            'handler'       =>  $handler,
            'namespace'     =>  self::$current_namespace,
            'name'          =>  $name,
            'middlewares'   =>  [
                'before'    =>  clone self::$stack_middlewares_before,
                'after'     =>  clone self::$stack_middlewares_after
            ]
        ];
    }

    public static function patch($route, $handler, $name = null)
    {
        $httpMethod = 'PATCH';
        $key = self::getInternalRuleKey($httpMethod, $handler);

        if (!is_null($name)) {
            self::$route_names[$name] = self::$current_prefix . $route;
        }

        self::$rules[ $key ] = [
            'httpMethod'    =>  $httpMethod,
            'route'         =>  self::$current_prefix . $route,
            'handler'       =>  $handler,
            'namespace'     =>  self::$current_namespace,
            'name'          =>  $name,
            'middlewares'   =>  [
                'before'    =>  clone self::$stack_middlewares_before,
                'after'     =>  clone self::$stack_middlewares_after
            ]
        ];
    }

    public static function delete($route, $handler, $name = null)
    {
        $httpMethod = 'DELETE';
        $key = self::getInternalRuleKey($httpMethod, $handler);

        if (!is_null($name)) {
            self::$route_names[$name] = self::$current_prefix . $route;
        }

        self::$rules[ $key ] = [
            'httpMethod'    =>  $httpMethod,
            'route'         =>  self::$current_prefix . $route,
            'handler'       =>  $handler,
            'namespace'     =>  self::$current_namespace,
            'name'          =>  $name,
            'middlewares'   =>  [
                'before'    =>  clone self::$stack_middlewares_before,
                'after'     =>  clone self::$stack_middlewares_after
            ]
        ];
    }

    public static function head($route, $handler, $name = null)
    {
        $httpMethod = 'HEAD';
        $key = self::getInternalRuleKey($httpMethod, $handler);

        if (!is_null($name)) {
            self::$route_names[$name] = self::$current_prefix . $route;
        }

        self::$rules[ $key ] = [
            'httpMethod'    =>  $httpMethod,
            'route'         =>  self::$current_prefix . $route,
            'handler'       =>  $handler,
            'namespace'     =>  self::$current_namespace,
            'name'          =>  $name,
            'middlewares'   =>  [
                'before'    =>  clone self::$stack_middlewares_before,
                'after'     =>  clone self::$stack_middlewares_after
            ]
        ];
    }


    public static function any($route, $handler, $name = null)
    {
        foreach (self::ALL_HTTP_METHODS as $method) {
            if (!is_null($name)) {
                self::$route_names[$name] = self::$current_prefix . $route;
            }

            $key = self::getInternalRuleKey($method, $handler);

            self::$rules[ $key ] = [
                'httpMethod'    =>  $method,
                'route'         =>  self::$current_prefix . $route,
                'handler'       =>  $handler,
                'namespace'     =>  self::$current_namespace,
                'name'          =>  $name,
                'middlewares'   =>  [
                    'before'    =>  clone self::$stack_middlewares_before,
                    'after'     =>  clone self::$stack_middlewares_after
                ]
            ];
        }
    }


    public static function addRoute($httpMethod, $route, $handler, $name = null)
    {
        foreach ((array) $httpMethod as $method) {
            $httpMethod = $method;
            $key = self::getInternalRuleKey($httpMethod, $handler);

            if (!is_null($name)) {
                self::$route_names[$name] = self::$current_prefix . $route;
            }

            self::$rules[ $key ] = [
                'httpMethod'    =>  $method,
                'route'         =>  self::$current_prefix . $route,
                'handler'       =>  $handler,
                'namespace'     =>  self::$current_namespace,
                'name'          =>  $name,
                'middlewares'   =>  [
                    'before'    =>  clone self::$stack_middlewares_before,
                    'after'     =>  clone self::$stack_middlewares_after
                ]
            ];
        }


        if (!is_null($name)) {
            self::$route_names[$name] = self::$current_prefix . $route;
        }
    }

    /**
     * @param array $options [<br>
     *      string 'prefix' => '',<br>
     *      string 'namespace' => '',<br>
     *      callable 'before' = null,<br>
     *      callable 'after' => null<br>
     * ]
     * @param callable|null $callback
     * @param string $name
     * @return mixed|void
     */
    public static function group(array $options = [], callable $callback = null)
    {
        $_setPrefix = array_key_exists('prefix', $options);
        $_setNamespace = array_key_exists('namespace', $options);

        if ($_setPrefix) {
            self::$stack_prefix->push($options['prefix']);
            self::$current_prefix = self::$stack_prefix->implode();
        }

        if ($_setNamespace) {
            self::$stack_namespace->push($options['namespace']);
            self::$current_namespace = self::$stack_namespace->implode('\\');
        }

        $group_have_before_middleware = false;
        if (array_key_exists('before', $options) && self::is_handler($options['before'])) {
            self::$stack_middlewares_before->push($options['before']);
            self::$current_middleware_before = $options['before'];
            $group_have_before_middleware = true;
        }

        $group_have_after_middleware = false;
        if (array_key_exists('after', $options) && self::is_handler($options['after'])) {
            self::$stack_middlewares_after->push($options['after']);
            self::$current_middleware_after = $options['after'];
            $group_have_after_middleware = true;
        }

        //@todo: check is_callable + is_closure?
        if (!is_null($callback)) {
            $callback();
        }

        if ($group_have_before_middleware) {
            self::$current_middleware_before = self::$stack_middlewares_before->pop();
        }

        if ($group_have_after_middleware) {
            self::$current_middleware_after = self::$stack_middlewares_after->pop();
        }

        if ($_setNamespace) {
            self::$stack_namespace->pop();
            self::$current_namespace = self::$stack_namespace->implode('\\');
        }

        if ($_setPrefix) {
            self::$stack_prefix->pop();
            self::$current_prefix = self::$stack_prefix->implode();
        }
    }

    /**
     * Возвращает информацию о роуте по имени
     *
     * @param string $name
     * @param string $default
     * @return string|array
     */
    public static function getRouter($name = '', string $default = '/')
    {
        if ($name === '*') {
            return self::$route_names;
        }

        if ($name === '') {
            return $default;
        }

        if (array_key_exists($name, self::$route_names)) {
            $route = self::$route_names[ $name ];
            // убрать необязательные группу из роута `[...]`
            $route = preg_replace('/\[.+\]$/', '', $route);
            return $route;
        }

        return $default;
    }

    /**
     * @return array
     */
    public static function getRoutersNames()
    {
        return self::$route_names;
    }

    public static function dispatch()
    {
        self::$dispatcher = \FastRoute\simpleDispatcher(function (RouteCollector $r) {
            foreach (self::$rules as $rule) {
                $handler
                    = (is_string($rule['handler']) && !empty($rule['namespace']))
                    ? "{$rule['namespace']}\\{$rule['handler']}"
                    : $rule['handler'];

                // Чтобы передавать доп.параметры в правило - нужно расширять метод addRoute
                $r->addRoute($rule['httpMethod'], $rule['route'], $handler);
            }
        });

        // Fetch method and URI from somewhere
        self::$routeInfo = $routeInfo = (self::$dispatcher)->dispatch(self::$httpMethod, self::$uri);

        list($state, $handler, $method_parameters) = $routeInfo;

        // dispatch errors
        if ($state === Dispatcher::NOT_FOUND) {
            throw new AppRouterNotFoundException("URL not found", 404, null, [
                'method'    =>  self::$httpMethod,
                'uri'       =>  self::$uri
            ]);
        }

        if ($state === Dispatcher::METHOD_NOT_ALLOWED) {
            throw new AppRouterMethodNotAllowedException("Method " . self::$httpMethod . " not allowed for URI " . self::$uri, 405, null, [
                'uri'       => self::$uri,
                'method'    => self::$httpMethod,
                'info'      => self::$routeInfo
            ]);
        }

        $rules = self::getRoutingRules();
        // $rules_key = self::$httpMethod . ' ' . $handler;
        $rules_key = self::getInternalRuleKey(self::$httpMethod, $handler);
        $rule = array_key_exists($rules_key, self::$rules) ? self::$rules[$rules_key] : [];

        /**
         * @var Stack $middlewares_before
         */
        $middlewares_before = $rule['middlewares']['before'];
        if (!is_null($middlewares_before) && !$middlewares_before->isEmpty()) {
            do {
                $middleware_handler = $middlewares_before->pop();

                if (!is_null($middleware_handler)) {
                    $before = self::compileHandler($middleware_handler, 'middleware:before');

                    call_user_func_array($before, [ self::$uri, self::$routeInfo ] );

                    unset($before);
                }
            } while (!$middlewares_before->isEmpty());
        }

        $actor = self::compileHandler($handler);
        call_user_func_array($actor, $method_parameters);

        /**
         * @var Stack $middlewares_after
         */
        $middlewares_after = $rule['middlewares']['before'];
        if (!is_null($middlewares_after) && !$middlewares_after->isEmpty()) {
            do {
                $middleware_handler = $middlewares_after->pop();

                if (!is_null($middleware_handler)) {
                    $after = self::compileHandler($middleware_handler, 'middleware:before');

                    call_user_func_array($after, [ self::$uri, self::$routeInfo ] );

                    unset($after);
                }
            } while (!$middlewares_after->isEmpty());
        }

        unset($state, $rules, $rules_key, $rule);
    }

    /**
     * @inheritDoc
     *
     * @return array
     */
    public static function getRoutingInfo(): array
    {
        return self::$routeInfo;
    }

    /**
     * Возвращает список объявленных роутов: [ 'method: handler' => [ handler, namespace, name ]
     *
     * @return array
     */
    public static function getRoutingRules(): array
    {
        return self::$rules;
    }

    /**
     * Выясняет, является ли передаваемый аргумент допустимым хэндлером
     *
     * @param $handler
     * @param bool $validate_handlers
     *
     * @return bool
     */
    public static function is_handler($handler = null, bool $validate_handlers = false): bool
    {
        if (is_null($handler)) {
            return false;
        } elseif ($handler instanceof \Closure) {
            return true;
        } elseif (strpos($handler, '@') > 0) {
            // dynamic method
            list($class, $method) = explode('@', $handler, 2);

            if ($validate_handlers && !class_exists($class)) {
                return false;
            }

            if ($validate_handlers && !method_exists($class, $method)) {
                return false;
            }

            return true;
        } elseif (strpos($handler, '::')) {
            // static method
            list($class, $method) = explode('::', $handler, 2);

            if ($validate_handlers && !class_exists($class)){
                return false;
            }

            if ($validate_handlers && !method_exists($class, $method)){
                return false;
            }

            return true;
        } elseif (is_array($handler)) {
            // [ \Path\To\Class:class, "method" ]

            $class = $handler[0];
            $method = $handler[1] ?: '__invoke';

            if ($validate_handlers && !class_exists($class)) {
                return false;
            }

            if ($validate_handlers && !method_exists($class, $method)) {
                return false;
            }
        }
        else {
            // function
            if ($validate_handlers && !function_exists($handler)){
                return false;
            }

            return true;
        }
        return false;
    } // is_handler()

    /**
     * Компилирует хэндлер из строчки, замыкания или массива [класс, метод] в действующий хэндлер
     * с отловом ошибок несуществования роута
     *
     * @param $handler
     * @return array|\Closure
     */
    private static function compileHandler($handler)
    {
        if ($handler instanceof \Closure) {
            $actor = $handler;
        } elseif (strpos($handler, '@') > 0) {
            // dynamic method
            list($class, $method) = explode('@', $handler, 2);

            self::checkClassExists($class);
            self::checkMethodExists($class, $method);

            $actor = [ new $class, $method ];

        } elseif (strpos($handler, '::')) {
            // static method
            list($class, $method) = explode('::', $handler, 2);

            self::checkClassExists($class, 'Static');
            self::checkMethodExists($class, $method, 'Static');

            $actor = [ $class, $method ];

        } elseif (is_array($handler)) {
            // [ \Path\To\Class:class, "method" ]

            $class = $handler[0];
            $method = $handler[1] ?: '__invoke';

            self::checkClassExists($class);
            self::checkMethodExists($class, $method);

            $actor = [ $class, $method ];

        } else {
            // function
            self::checkFunctionExists($handler);

            $actor = $handler;
        }

        return $actor;
    }

    /**
     * Генерирует имя внутреннего ключа для массива именованных роутов
     * на основе метода и хэндлера:
     *
     * - Замыкание ([таймштамп] Closure(LineStart-LineEnd)=аргумент1:аргумент2:аргумент3 или [таймштамп] Closure(<md5(1, 4096)>)=.
     * - Метод класса, переданный строкой
     * - Метод класса, переданный массивом [ класс, метод ]
     * - функция
     *
     * @param $httpMethod
     * @param $handler
     * @return string
     */
    private static function getInternalRuleKey($httpMethod, $handler): string
    {
        $namespace = self::$current_namespace;

        if ($handler instanceof \Closure) {
            $internal_name = self::getClosureInternalName($handler);
        } elseif (is_array($handler)) {
            $class = $handler[0];
            $method = $handler[1] ?: '__invoke';

            $internal_name = "{$namespace}\\{$class}@{$method}";
        } elseif (is_string($handler)) {
            $internal_name = "{$namespace}\\{$handler}";
        } else {
            // function by name
            $internal_name = $handler;
        }

        return "{$httpMethod} {$internal_name}";
    }

    /**
     * Проверяет существование класса или кидает исключение AppRouterHandlerError
     *
     * @param $class
     * @return void
     * @throws AppRouterHandlerError
     */
    private static function checkClassExists($class)
    {
        if (!class_exists($class)){
            self::$logger->error("Class {$class} not defined.", [ self::$uri, self::$httpMethod, $class ]);
            throw new AppRouterHandlerError("Class {$class} not defined", 500, null, [
                'uri'       =>  self::$uri,
                'method'    =>  self::$httpMethod,
                'info'      =>  self::$routeInfo
            ]);
        }
    }

    /**
     * Проверяет существование метода в классе или кидает исключение AppRouterHandlerError
     *
     * @param $class
     * @param $method
     * @return void
     * @throws AppRouterHandlerError
     */
    private static function checkMethodExists($class, $method)
    {
        if (!method_exists($class, $method)){
            self::$logger->error("Method {$method} not declared at {$class} class", [ self::$uri, self::$httpMethod, $class ]);
            throw new AppRouterHandlerError("Method {$method} not declared at static class {$class}", 500, null, [
                'uri'       =>  self::$uri,
                'method'    =>  self::$httpMethod,
                'info'      =>  self::$routeInfo
            ]);
        }
    }

    /**
     * Проверяет существование функции с указанным именем или кидает исключение AppRouterHandlerError
     *
     * @param $handler
     * @return void
     * @throws AppRouterHandlerError
     */
    private static function checkFunctionExists($handler)
    {
        if (!function_exists($handler)){
            self::$logger->error("Handler function {$handler} not found", [ self::$uri, self::$httpMethod, $handler ]);
            throw new AppRouterHandlerError("Handler function {$handler} not found", 500, null, [
                'uri'       =>  self::$uri,
                'method'    =>  self::$httpMethod,
                'info'      =>  self::$routeInfo
            ]);
        }

    }

    /**
     * Возвращает "внутреннее" имя замыкание, сгенерированное на основе таймштампа (до мс) и аргументов функции
     * [таймштамп] Closure(LineStart-LineEnd)=аргумент1:аргумент2:аргумент3
     *
     * Или, если возникло исключение ReflectionException
     * [таймштамп] Closure(<md5(1, 4096)>)=.
     *
     * @param $closure
     * @return string
     */
    private static function getClosureInternalName($closure): string
    {
        $name = "[" . microtime() . "] Closure(";

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