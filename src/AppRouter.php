<?php

namespace Arris;

use Arris\AppRouter\FastRoute\Dispatcher;
use Arris\AppRouter\FastRoute\RouteCollector;
use Arris\AppRouter\Stack;
use Arris\Exceptions\AppRouterHandlerError;
use Arris\Exceptions\AppRouterMethodNotAllowedException;
use Arris\Exceptions\AppRouterNotFoundException;
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
    private static array $rules;

    /**
     * @var string
     */
    private static string $current_namespace = '';

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
    private static string $uri;

    /**
     * @var array
     */
    public static array $route_names;

    /**
     * @var string
     */
    private static string $current_prefix;

    /**
     * Default replace pattern
     *
     * @var string
     */
    private static string $routeReplacePattern = '%%$1%%';

    /**
     * Current Routing Info
     *
     * @var array
     */
    private static array $routeInfo;

    /**
     * @var Stack
     */
    private static Stack $stack_prefix;

    /**
     * @var Stack
     */
    private static Stack $stack_namespace;

    /**
     * @var Stack
     */
    private static Stack $stack_middlewares_before;

    /**
     * @var Stack
     */
    private static Stack $stack_middlewares_after;

    /**
     * Инстансы middlewares ( class_name => class_instance )
     * @var array
     */
    private static array $instances_middlewares = [];

    /**
     * @var Stack
     */
    private static Stack $stack_aliases;

    /**
     * @var null
     */
    private static $current_middleware_before = null;

    /**
     * @var null
     */
    private static $current_middleware_after = null;

    /**
     * @var string
     */
    private static $middlewares_namespace = '';

    /**
     * @var string[]
     */
    public static array $route_parts;

    /**
     * @var array Current rule of dispatched route
     */
    private static array $routeRule = [];

    /* ===== Options ===== */

    /**
     * Разрешать ли пустые группы (без роутов, но с опциями или миддлварами)
     * @var bool $option_allow_empty_groups
     */
    private static bool $option_allow_empty_groups = true;

    /**
     * Отладочная опция: присоединять namespace к именам ключей при вызове метода dispatch()
     *
     * @var bool
     */
    private static bool $option_appendNamespaceOnDispatch = true;


    /* ======================================================================================= */


    public function __construct()
    {
    }

    public static function init(LoggerInterface $logger = null, array $options = [])
    {
        self::$route_parts = \preg_split("/\/+/", \preg_replace("/(\?.*)/", "", trim($_SERVER['REQUEST_URI'], '/')));

        self::$logger
            = ($logger instanceof LoggerInterface)
            ? $logger
            : new NullLogger();

        self::$httpMethod = $_SERVER['REQUEST_METHOD'];

        $uri = $_SERVER['REQUEST_URI'];
        if (false !== $pos = \strpos($uri, '?')) {
            $uri = \substr($uri, 0, $pos);
        }
        self::$uri = \rawurldecode($uri);

        if (\array_key_exists('defaultNamespace', $options)) {
            self::setDefaultNamespace($options['defaultNamespace']);
        } elseif (\array_key_exists('namespace', $options)) {
            self::setDefaultNamespace($options['namespace']);
        }

        if (\array_key_exists('middlewareNamespace', $options)) {
            self::setMiddlewaresNamespace($options['middlewareNamespace']);
        }

        if (\array_key_exists('prefix', $options)) {
            self::$current_prefix = $options['prefix'];
        }

        //@todo: документация!
        if (\array_key_exists('routeReplacePattern', $options)) {
            self::$routeReplacePattern = $options['routeReplacePattern'];
        }

        if (\array_key_exists('appendNamespaceOnDispatch', $options)) {
            self::$option_appendNamespaceOnDispatch = (bool)$options['appendNamespaceOnDispatch'];
        }

        if (\array_key_exists('allowEmptyGroups', $options)) {
            self::$option_allow_empty_groups = (bool)$options['allowEmptyGroups'];
        }

        self::$stack_prefix = new Stack();

        self::$stack_namespace = new Stack();

        self::$stack_middlewares_before = new Stack();

        self::$stack_middlewares_after = new Stack();

        self::$stack_aliases = new Stack();
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
        if (\is_null($route) || \is_null($handler)) {
            return;
        }

        $key = self::getInternalRuleKey('GET', $handler, $route);

        if (!\is_null($name)) {
            self::$route_names[$name] = self::$current_prefix . $route;
        }

        self::$rules[ $key ] = [
            'httpMethod'    =>  'GET',
            'route'         =>  self::$current_prefix . $route,
            'handler'       =>  $handler,
            'namespace'     =>  self::$current_namespace,
            'name'          =>  $name,
            'middlewares'   =>  [
                'before'    =>  clone self::$stack_middlewares_before,
                'after'     =>  clone self::$stack_middlewares_after
            ],
            'backtrace'     =>  \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]
        ];
    }

    public static function post($route, $handler, $name = null)
    {
        if (\is_null($route) || \is_null($handler)) {
            return;
        }

        $key = self::getInternalRuleKey('POST', $handler, $route);

        if (!\is_null($name)) {
            self::$route_names[$name] = self::$current_prefix . $route;
        }

        self::$rules[ $key ] = [
            'httpMethod'    =>  'POST',
            'route'         =>  self::$current_prefix . $route,
            'handler'       =>  $handler,
            'namespace'     =>  self::$current_namespace,
            'name'          =>  $name,
            'middlewares'   =>  [
                'before'    =>  clone self::$stack_middlewares_before,
                'after'     =>  clone self::$stack_middlewares_after
            ],
            'backtrace'     =>  \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]
        ];
    }

    public static function put($route, $handler, $name = null)
    {
        if (\is_null($route) || \is_null($handler)) {
            return;
        }

        $key = self::getInternalRuleKey('PUT', $handler, $route);

        if (!\is_null($name)) {
            self::$route_names[$name] = self::$current_prefix . $route;
        }

        self::$rules[ $key ] = [
            'httpMethod'    =>  'PUT',
            'route'         =>  self::$current_prefix . $route,
            'handler'       =>  $handler,
            'namespace'     =>  self::$current_namespace,
            'name'          =>  $name,
            'middlewares'   =>  [
                'before'    =>  clone self::$stack_middlewares_before,
                'after'     =>  clone self::$stack_middlewares_after
            ],
            'backtrace'     =>  \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]
        ];
    }

    public static function patch($route, $handler, $name = null)
    {
        if (\is_null($route) || \is_null($handler)) {
            return;
        }

        $key = self::getInternalRuleKey('PATCH', $handler, $route);

        if (!\is_null($name)) {
            self::$route_names[$name] = self::$current_prefix . $route;
        }

        self::$rules[ $key ] = [
            'httpMethod'    =>  'PATCH',
            'route'         =>  self::$current_prefix . $route,
            'handler'       =>  $handler,
            'namespace'     =>  self::$current_namespace,
            'name'          =>  $name,
            'middlewares'   =>  [
                'before'    =>  clone self::$stack_middlewares_before,
                'after'     =>  clone self::$stack_middlewares_after
            ],
            'backtrace'     =>  \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]
        ];
    }

    public static function delete($route, $handler, $name = null)
    {
        if (\is_null($route) || \is_null($handler)) {
            return;
        }

        $key = self::getInternalRuleKey('DELETE', $handler, $route);

        if (!\is_null($name)) {
            self::$route_names[$name] = self::$current_prefix . $route;
        }

        self::$rules[ $key ] = [
            'httpMethod'    =>  'DELETE',
            'route'         =>  self::$current_prefix . $route,
            'handler'       =>  $handler,
            'namespace'     =>  self::$current_namespace,
            'name'          =>  $name,
            'middlewares'   =>  [
                'before'    =>  clone self::$stack_middlewares_before,
                'after'     =>  clone self::$stack_middlewares_after
            ],
            'backtrace'     =>  \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]
        ];
    }

    public static function head($route, $handler, $name = null)
    {
        if (\is_null($route) || \is_null($handler)) {
            return;
        }

        $key = self::getInternalRuleKey('HEAD', $handler, $route);

        if (!\is_null($name)) {
            self::$route_names[$name] = self::$current_prefix . $route;
        }

        self::$rules[ $key ] = [
            'httpMethod'    =>  'HEAD',
            'route'         =>  self::$current_prefix . $route,
            'handler'       =>  $handler,
            'namespace'     =>  self::$current_namespace,
            'name'          =>  $name,
            'middlewares'   =>  [
                'before'    =>  clone self::$stack_middlewares_before,
                'after'     =>  clone self::$stack_middlewares_after
            ],
            'backtrace'     =>  \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]
        ];
    }

    public static function any($route, $handler, $name = null)
    {
        if (\is_null($route) || \is_null($handler)) {
            return;
        }

        self::$route_names[ $name ] = self::$current_prefix . $route;
        foreach (self::ALL_HTTP_METHODS as $method) {
            if (!\is_null($name)) {
                self::$route_names["{$method}.{$name}"] = self::$current_prefix . $route;
            }

            $key = self::getInternalRuleKey($method, $handler, $route);

            self::$rules[ $key ] = [
                'httpMethod'    =>  $method,
                'route'         =>  self::$current_prefix . $route,
                'handler'       =>  $handler,
                'namespace'     =>  self::$current_namespace,
                'name'          =>  $name,
                'middlewares'   =>  [
                    'before'    =>  clone self::$stack_middlewares_before,
                    'after'     =>  clone self::$stack_middlewares_after
                ],
                'backtrace'     =>  \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]
            ];
        }
    }

    public static function addRoute($httpMethod, $route, $handler, $name = null)
    {
        if (\is_null($route) || \is_null($handler)) {
            return;
        }

        $backtrace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];

        foreach ((array)$httpMethod as $method) {
            $httpMethod = $method;
            $key = self::getInternalRuleKey($httpMethod, $handler, $route);

            if (!\is_null($name)) {
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
                ],
                'backtrace'     =>  $backtrace
            ];
        }


        if (!\is_null($name)) {
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
     * @return void
     */
    public static function group(array $options = [], callable $callback = null)
    {
        if (empty($callback) && !self::$option_allow_empty_groups) {
            return;
        }

        $_setPrefix = \array_key_exists('prefix', $options);
        $_setNamespace = \array_key_exists('namespace', $options);

        if ($_setPrefix) {
            self::$stack_prefix->push($options['prefix']);
            self::$current_prefix = self::$stack_prefix->implode();
        }

        if ($_setNamespace) {
            self::$stack_namespace->push($options['namespace']);
            self::$current_namespace = self::$stack_namespace->implode('\\');
        }

        // Проверка is_hander лишняя, поскольку позже, в диспетчере, всё равно выполняется компиляция хэндлера.
        // Там и кидаются все исключения.

        $group_have_before_middleware = false;
        if (\array_key_exists('before', $options)/* && self::is_handler($options['before'])*/) {
            self::$stack_middlewares_before->push($options['before']);
            self::$current_middleware_before = $options['before'];
            $group_have_before_middleware = true;
        }

        $group_have_after_middleware = false;
        if (\array_key_exists('after', $options)/* && self::is_handler($options['after'])*/) {
            self::$stack_middlewares_after->push($options['after']);
            self::$current_middleware_after = $options['after'];
            $group_have_after_middleware = true;
        }

        if (\is_callable($callback)) {
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

        // return something? What?
    }

    /**
     * Возвращает информацию о роуте по имени
     *
     * @todo: добавить аргумент "кастомная маска", перекрывающая дефолтное значение?
     * @todo: и нужен ли default route path или перенести его в опции?
     *
     * @param string $name
     * @param string $default
     * @param bool $replace_parts
     * @return string|array
     */
    public static function getRouter($name = '', string $default = '/', bool $replace_parts = false)
    {
        if ($name === '*') {
            return self::$route_names;
        }

        if ($name === '') {
            return $default;
        }

        if (\array_key_exists($name, self::$route_names)) {
            $route = self::$route_names[ $name ];

            if ($replace_parts) {
                $route = \preg_replace(
                    '/{([[:word:]]+)}/',
                    self::$routeReplacePattern,
                    $route
                );
            }

            // убрать необязательные группы из роута `[...]`
            return \preg_replace('/\[.+\]$/', '', $route);
        }

        return $default;
    }

    /**
     * @return array
     */
    public static function getRoutersNames(): array
    {
        return self::$route_names;
    }

    public static function dispatch()
    {
        self::$dispatcher = \Arris\AppRouter\FastRoute\simpleDispatcher(function (RouteCollector $r) {
            foreach (self::$rules as $rule) {
                $handler
                    = (\is_string($rule['handler']) && !empty($rule['namespace']))
                    ? "{$rule['namespace']}\\{$rule['handler']}"
                    : $rule['handler'];

                $r->addRoute($rule['httpMethod'], $rule['route'], $handler);
            }
        });

        // Fetch method and URI from somewhere
        self::$routeInfo = $routeInfo = (self::$dispatcher)->dispatch(self::$httpMethod, self::$uri);

        // list($state, $handler, $method_parameters) = $routeInfo;
        // PHP8+ good practice:
        $state = $routeInfo[0]; // тут ВСЕГДА что-то есть
        $handler = $routeInfo[1] ?? [];
        $method_parameters = $routeInfo[2] ?? [];

        // Вычисляем правило, определяющее текущий роут.
        $rules = self::getRoutingRules();
        $rules_key = self::getInternalRuleKey(self::$httpMethod, $handler, self::$option_appendNamespaceOnDispatch);
        $rule = \array_key_exists($rules_key, $rules) ? $rules[$rules_key] : [];
        self::$routeRule = $rule;

        // Handler пустой или некорректный
        if (empty($handler)) {
            throw new AppRouterHandlerError("Handler not found or empty", 500, [
                'uri'       =>  self::$uri,
                'method'    =>  self::$httpMethod,
                'info'      =>  self::$routeInfo,
                'rule'      =>  self::$routeRule
            ]);
        }

        // dispatch errors
        if ($state === Dispatcher::NOT_FOUND) {
            throw new AppRouterNotFoundException("URL not found", 404, [
                'uri'       =>  self::$uri,
                'method'    =>  self::$httpMethod,
                'info'      =>  self::$routeInfo,
                'rule'      =>  self::$routeRule
            ]);
        }

        if ($state === Dispatcher::METHOD_NOT_ALLOWED) {
            throw new AppRouterMethodNotAllowedException("Method " . self::$httpMethod . " not allowed for URI " . self::$uri, 405, [
                'uri'       =>  self::$uri,
                'method'    =>  self::$httpMethod,
                'info'      =>  self::$routeInfo,
                'rule'      =>  self::$routeRule
            ]);
        }

        /**
         * Посредники ПЕРЕД
         *
         * @var Stack $middlewares_before
         */
        $middlewares_before = $rule['middlewares']['before'] ?? null;

        if (!\is_null($middlewares_before) && !$middlewares_before->isEmpty()) {
            $middlewares_before = $middlewares_before->reverse(); // инвертируем массив before-middlewares (иначе порядок будет неверный)

            do {
                $middleware_handler = $middlewares_before->pop();

                if (!\is_null($middleware_handler)) {
                    $before = self::compileHandler($middleware_handler, true, 'before');

                    \call_user_func_array($before, [ self::$uri, self::$routeInfo ] ); // если вот сейчас передан пустой хэндлер - никакой ошибки не будет - миддлвар просто не вызовется

                    unset($before);
                }
            } while (!$middlewares_before->isEmpty());
        }

        $actor = self::compileHandler($handler, false, 'default');
        \call_user_func_array($actor, $method_parameters);
        // c PHP8 поведение нужно поменять? Передавать
        // self::$uri, self::$routeInfo как именованные параметры и первым параметром собственно $method_parameters

        /**
         * Посредники ПОСЛЕ
         *
         * @var Stack $middlewares_after
         */
        $middlewares_after = $rule['middlewares']['after'] ?? null;

        if (!\is_null($middlewares_after) && !$middlewares_after->isEmpty()) {
            do {
                $middleware_handler = $middlewares_after->pop();

                if (!\is_null($middleware_handler)) {
                    $after = self::compileHandler($middleware_handler, true, 'after');

                    \call_user_func_array($after, [ self::$uri, self::$routeInfo ] );

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
     * @deprecated
     * Выясняет, является ли передаваемый аргумент допустимым хэндлером
     *
     * Неоптимальное поведение, скорее всего нужен единый метод, принимающий аргументы
     * - throw_on_error BOOL
     * - log_on_error BOOL
     *
     * @param $handler
     * @param bool $validate_handlers
     *
     * @return bool
     */
    public static function is_handler($handler = null, bool $validate_handlers = false): bool
    {
        if (\is_null($handler)) {
            return false;
        } elseif ($handler instanceof \Closure) {
            return true;
        } elseif (\is_array($handler)) {
            if (empty($handler)) {
                return false;
            }

            // [ \Path\To\Class:class, "method" ]

            $class = $handler[0];
            $method = $handler[1] ?: '__invoke';

            if ($validate_handlers && !\class_exists($class)) {
                return false;
            }

            if ($validate_handlers && !\method_exists($class, $method)) {
                return false;
            }

            return true;

        } elseif (strpos($handler, '@') > 0) {
            // dynamic method
            list($class, $method) = \explode('@', $handler, 2);

            if ($validate_handlers && !\class_exists($class)) {
                return false;
            }

            if ($validate_handlers && !\method_exists($class, $method)) {
                return false;
            }

            return true;
        } elseif (\strpos($handler, '::')) {
            // static method
            list($class, $method) = \explode('::', $handler, 2);

            if ($validate_handlers && !\class_exists($class)){
                return false;
            }

            if ($validate_handlers && !\method_exists($class, $method)){
                return false;
            }

            return true;
        }
        else {
            // function
            if ($validate_handlers && !\function_exists($handler)){
                return false;
            }

            return true;
        }

    } // is_handler()

    /**
     * Проверяет хэндлер на корректность. Сходен по функционалу с compile
     *
     * @param $handler
     * @return bool
     */
    public static function validateHandler($handler): bool
    {
        if ($handler instanceof \Closure) {
            return true;
        } elseif (\is_array($handler)) {
            // [ \Path\To\Class:class, "method" ]
            if (empty($handler)) {
                return false;
            }

            $class = $handler[0];
            $method = $handler[1] ?: '__invoke';

            self::checkClassExists($class);
            self::checkMethodExists($class, $method);

            return true;

        } elseif (\strpos($handler, '@') > 0) {
            // dynamic method
            $exploded = \explode('@', $handler, 2);
            $class = $exploded[0];
            $method = $exploded[1] ?: '__invoke';

            self::checkClassExists($class);
            self::checkMethodExists($class, $method);

            return true;

        } elseif (\strpos($handler, '::')) {
            // static method
            $exploded = \explode('::', $handler, 2);
            $class = $exploded[0];
            $method = $exploded[1] ?: '';

            self::checkClassExists($class, true);
            self::checkMethodExists($class, $method, true);

            return true;

        }  else {
            return self::checkFunctionExists($handler);
        }

        return false;
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
     * @param $route
     * @param bool $append_namespace
     * @return string
     */
    private static function getInternalRuleKey($httpMethod, $handler, $route, bool $append_namespace = true): string
    {
        $namespace = '';
        if ($append_namespace) {
            $namespace = self::$current_namespace;
            $namespace = "{$namespace}\\";
        }

        if ($handler instanceof \Closure) {
            $internal_name = self::getClosureInternalName($handler);
        } elseif (\is_array($handler)) {

            // Хэндлер может быть массивом, но пустым. Вообще, это ошибка, поэтому генерим имя на основе md5 роута и метода.
            $class = $handler[0] ?? \md5(self::$httpMethod . ':' . self::$current_prefix . $route);
            $method = $handler[1] ?? '__invoke';

            $internal_name = "{$namespace}{$class}@{$method}";
        } elseif (\is_string($handler)) {
            $internal_name = "{$namespace}{$handler}";
        } elseif (\is_null($handler)) {
            return \md5(self::$httpMethod . ':' . self::$current_prefix . $route);
        } else {
            // function by name
            $internal_name = $handler;
        }

        return "{$httpMethod} {$internal_name}";
    }

    public static function check($class, $method)
    {
        $reflection = new \ReflectionClass($class);

        return $reflection->hasMethod($method) && $reflection->getMethod($method)->isPublic();
    }

    /**
     * Проверяет существование класса или кидает исключение AppRouterHandlerError
     *
     * @param $class
     * @param bool $is_static
     * @return void
     */
    public static function checkClassExists($class, bool $is_static = false)
    {
        if (!\class_exists($class)){
            $prompt = $is_static ? "Static class" : "Class";
            self::$logger->error("{$prompt} '{$class}' not defined.", [ self::$uri, self::$httpMethod, $class ]);
            throw new AppRouterHandlerError("{$prompt} '{$class}' not defined", 500, [
                'uri'       =>  self::$uri,
                'method'    =>  self::$httpMethod,
                'info'      =>  self::$routeInfo,
                'rule'      =>  self::$routeRule
            ]);
        }
    }

    /**
     * Проверяет существование метода в классе или кидает исключение AppRouterHandlerError
     *
     * @param $class
     * @param $method
     * @param bool $is_static
     * @return void
     */
    public static function checkMethodExists($class, $method, bool $is_static = false)
    {
        $prompt = $is_static ? 'static class' : 'class';

        if (empty($method)) {
            self::$logger->error("Method can't be empty at {$prompt} '{$class}'", [ self::$uri, self::$httpMethod, $class ]);
            throw new AppRouterHandlerError("Method can't be empty at {$prompt} '{$class}'", 500, [
                'uri'       =>  self::$uri,
                'method'    =>  self::$httpMethod,
                'info'      =>  self::$routeInfo,
                'rule'      =>  self::$routeRule
            ]);
        }

        if (!\method_exists($class, $method)){
            self::$logger->error("Method '{$method}' not defined at {$prompt} '{$class}'", [ self::$uri, self::$httpMethod, $class ]);
            throw new AppRouterHandlerError("Method '{$method}' not defined at {$prompt} '{$class}'", 500, [
                'uri'       =>  self::$uri,
                'method'    =>  self::$httpMethod,
                'info'      =>  self::$routeInfo,
                'rule'      =>  self::$routeRule
            ]);
        }
    }

    /**
     * Проверяет существование функции с указанным именем или кидает исключение AppRouterHandlerError
     *
     * @param $handler
     * @return bool
     * @throws AppRouterHandlerError
     */
    public static function checkFunctionExists($handler): bool
    {
        if (!\function_exists($handler)){
            self::$logger->error("Handler function '{$handler}' not found", [ self::$uri, self::$httpMethod, $handler ]);
            throw new AppRouterHandlerError("Handler function '{$handler}' not found", 500, [
                'uri'       =>  self::$uri,
                'method'    =>  self::$httpMethod,
                'info'      =>  self::$routeInfo,
                'rule'      =>  self::$routeRule
            ]);
        }
        return true;
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
    public static function getClosureInternalName($closure): string
    {
        $name = "[] Closure(";

        try {
            $reflected = new \ReflectionFunction($closure);
            $args = \implode(':',
                // создаем статичную функцию и сразу вызываем
                (static function ($r) {
                    return
                        \array_map(
                        // обработчик
                            static function($v)
                            {
                                return \is_object($v) ? $v->name : $v;
                            },
                            // входной массив
                            \array_merge(
                                $r->getParameters(),
                                \array_keys(
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
            $name .= \md5(\mt_rand(1, PHP_MAXPATHLEN)) . ")=.";
        }

        return $name;
    }

    /**
     * Компилирует хэндлер из строчки, замыкания или массива [класс, метод] в действующий хэндлер
     * с отловом ошибок несуществования роута
     *
     * @param $handler
     * @param bool $is_middleware
     * @param string $target
     * @return array|\Closure
     */
    public static function compileHandler($handler, bool $is_middleware = false, string $target = 'default')
    {
        if (empty($handler)) {
            return [];
        }

        if ($handler instanceof \Closure) {
            $actor = $handler;
        } elseif (\is_array($handler) || (\is_string($handler) && \strpos($handler, '@') > 0)) {
            // [ \Path\To\Class:class, "method" ] or 'Class@method'

            if (\is_string($handler)) {
                list($class, $method) = self::explode($handler, [null, '__invoke'], '@');
            } else {
                list($class, $method) = $handler;
            }

            self::checkClassExists($class);
            self::checkMethodExists($class, $method);

            if ($is_middleware) {
                if (\array_key_exists($class, self::$instances_middlewares)) {
                    $i_class = self::$instances_middlewares[$class];
                } else {
                    $i_class = self::$instances_middlewares[$class] = new $class();
                }
            } else {
                // не миддлвар, а целевой вызов.
                $i_class = new $class(); //@todo: тут надо бы передавать параметры в конструктор, всякие HTTP_REQUEST, HTTP_RESPONSE
            }

            $actor = [ $i_class, $method ];

        } elseif (\strpos($handler, '::')) {
            // static method
            list($class, $method) = self::explode($handler, [null, ''], '::');

            self::checkClassExists($class, true);
            self::checkMethodExists($class, $method, true);

            $actor = [ $class, $method ];

        }  else {
            // function
            self::checkFunctionExists($handler);

            $actor = $handler;
        }

        return $actor;
    }


    /**
     * Выполняет explode строки роута с учетом дефолтной маски
     * Заменяет list($a, $b) = explode(separator, string) с дефолтными значениями элементов
     * Хотел назвать это replace_array_callback(), но передумал
     *
     * @param $income
     * @param array $default
     * @param string $separator
     * @return array
     */
    private static function explode($income, array $default = [ null, '__invoke' ], string $separator = '@'): array
    {
        return \array_map(static function($first, $second) {
            return empty($second) ? $first : $second;
        }, $default, \explode($separator, $income));
    }

}



# -eof-