<?php

namespace Arris;

use Arris\AppRouter\FastRoute\ConfigureRoutes;
use Arris\AppRouter\FastRoute\Dispatcher;
use Arris\AppRouter\Helper;
use Arris\AppRouter\Stack;
use Arris\Exceptions\AppRouterHandlerError;
use Arris\Exceptions\AppRouterMethodNotAllowedException;
use Arris\Exceptions\AppRouterNotFoundException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function array_key_exists;
use function call_user_func_array;
use function class_exists;
use function debug_backtrace;
use function function_exists;
use function is_array;
use function is_null;
use function is_string;
use function md5;
use function method_exists;
use function preg_replace;
use function preg_split;
use function trim;
use function rawurldecode;
use function str_contains;

class AppRouter implements AppRouterInterface
{
    const OPTION_ALLOW_EMPTY_GROUPS = 'allowEmptyGroups';
    const OPTION_ALLOW_EMPTY_HANDLERS = 'allowEmptyHandlers';
    const OPTION_DEFAULT_ROUTE = 'getRouterDefaultValue';
    const OPTION_USE_ALIASES = 'useAliases';

    public const ALL_HTTP_METHODS = [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'HEAD',
        'OPTIONS'
    ];

    /**
     * @var Dispatcher
     */
    private static $dispatcher;

    /**
     * @var array
     */
    private static array $rules = [];

    /**
     * @var string
     */
    private static string $current_namespace = '';

    /**
     * @var LoggerInterface
     */
    private static LoggerInterface $logger;

    /**
     * @var string
     */
    private static string $httpMethod;

    /**
     * @var string
     */
    private static string $uri = '';

    /**
     * @var array
     */
    public static array $route_names = [];

    /**
     * @var string
     */
    private static string $current_prefix = '';

    /**
     * Current Routing Info
     *
     * @var Dispatcher\Result\ResultInterface
     */
    private static $routeInfo;

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
     * @var array
     */
    public static array $stack_aliases = [];

    /**
     * В планах: дать возможность указывать нэйспейс для миддлваров, но не реализован. Зачем?
     */
    // private static $middlewares_namespace = '';

    /**
     * @var string[]
     */
    public static array $route_parts;

    /**
     * @var array Current rule of dispatched route
     */
    private static array $routeRule = [];

    /**
     * STUB для внутренних опций
     *
     * @var array
     */
    private static array $options = [];

    /**
     * Разрешать ли пустые группы (без роутов, но с опциями или миддлварами)
     *
     * @var bool $option_allow_empty_groups
     */
    private static bool $option_allow_empty_groups = false;

    /**
     * Разрешать пустые хэндлеры ([])
     *
     * @var bool
     */
    private static bool $option_allow_empty_handlers = false;

    /**
     * getRouter: Заменять конечный необязательный слэш на обязательный
     *
     * @var bool
     */
    private static bool $option_getroute_replace_optional_slash_to_mandatory = true;

    /**
     * getRouter: убирать ли опциональные группы?
     *
     * @var bool
     */
    private static bool $option_getroute_remove_optional_groups = true;

    /**
     * getRouter: значение роута по-умолчанию (для ненайденных или пустых имён)
     *
     * @var string
     */
    private static string $option_getroute_default_value = '/';

    /**
     * Использовать ли механизм "алиасов"? (FALSE)
     *
     * @var bool
     */
    private static bool $option_use_aliases = false;

    /**
     * @inheritDoc
     */
    public function __construct(
        LoggerInterface $logger = null,
        string $namespace = '',
        string $prefix = '',
        bool $allowEmptyGroups = false,
        bool $allowEmptyHandlers = false,
    ) {
        self::init($logger, namespace: $namespace, prefix: $prefix, allowEmptyGroups: $allowEmptyGroups, allowEmptyHandlers: $allowEmptyHandlers);
    }

    /**
     * @inheritDoc
     */
    public static function init(
        LoggerInterface $logger = null,
        string $namespace = '',
        string $prefix = '',
        bool $allowEmptyGroups = false,
        bool $allowEmptyHandlers = false,
    )
    {
        // unimplemented options in constructor
        // string $middleware_namespace = '',
        self::$route_parts = preg_split("/\/+/", \preg_replace("/(\?.*)/", "", trim($_SERVER['REQUEST_URI'], '/')));

        self::$logger
            = ($logger instanceof LoggerInterface)
            ? $logger
            : new NullLogger();

        self::$httpMethod = $_SERVER['REQUEST_METHOD'];

        $uri = $_SERVER['REQUEST_URI'];
        $uri = strstr($uri, '?', true) ?: $uri;

        self::$uri = rawurldecode($uri);

        if (!empty($namespace)) {
            self::setDefaultNamespace($namespace);
        }

        if (!empty($prefix)) {
            self::$current_prefix = $prefix;
        }

        self::$option_allow_empty_groups = $allowEmptyGroups;
        self::$option_allow_empty_handlers = $allowEmptyHandlers;

        self::$stack_prefix = new Stack();

        self::$stack_namespace = new Stack();

        self::$stack_middlewares_before = new Stack();

        self::$stack_middlewares_after = new Stack();

        self::$stack_aliases = [];

        // self::$routeReplacePattern = $routeReplacePattern;
    }

    public static function setOption(string $name, $value = null):void
    {
        match ($name) {
            self::OPTION_ALLOW_EMPTY_GROUPS     => self::$option_allow_empty_groups     = (bool)$value,
            self::OPTION_ALLOW_EMPTY_HANDLERS   => self::$option_allow_empty_handlers   = (bool)$value,
            self::OPTION_DEFAULT_ROUTE          => self::$option_getroute_default_value = $value,
            self::OPTION_USE_ALIASES            => self::$option_use_aliases            = (bool)$value,
            default => null,
        };
    }

    public static function setDefaultNamespace(string $namespace = ''):void
    {
        self::$current_namespace = $namespace;
    }


    public static function setMiddlewaresNamespace(string $namespace = ''):void
    {
        // return true;
        // self::$middlewares_namespace = $namespace;
    }

    public static function get($route, $handler, $name = null)
    {
        if (is_null($route) || is_null($handler)) {
            return;
        }

        $key = self::getInternalRuleKey('GET', $handler, $route);

        if (!is_null($name)) {
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
            'backtrace'     =>  debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]
        ];
    }

    public static function post($route, $handler, $name = null)
    {
        if (is_null($route) || is_null($handler)) {
            return;
        }

        $key = self::getInternalRuleKey('POST', $handler, $route);

        if (!is_null($name)) {
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
            'backtrace'     =>  debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]
        ];
    }


    public static function put($route, $handler, $name = null)
    {
        if (is_null($route) || is_null($handler)) {
            return;
        }

        $key = self::getInternalRuleKey('PUT', $handler, $route);

        if (!is_null($name)) {
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
            'backtrace'     =>  debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]
        ];
    }

    public static function patch($route, $handler, $name = null)
    {
        if (is_null($route) || is_null($handler)) {
            return;
        }

        $key = self::getInternalRuleKey('PATCH', $handler, $route);

        if (!is_null($name)) {
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
            'backtrace'     =>  debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]
        ];
    }

    public static function delete($route, $handler, $name = null)
    {
        if (is_null($route) || is_null($handler)) {
            return;
        }

        $key = self::getInternalRuleKey('DELETE', $handler, $route);

        if (!is_null($name)) {
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
            'backtrace'     =>  debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]
        ];
    }

    public static function head($route, $handler, $name = null)
    {
        if (is_null($route) || is_null($handler)) {
            return;
        }

        $key = self::getInternalRuleKey('HEAD', $handler, $route);

        if (!is_null($name)) {
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
            'backtrace'     =>  debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]
        ];
    }

    public static function any($route, $handler, $name = null)
    {
        if (is_null($route) || is_null($handler)) {
            return;
        }

        self::$route_names[ $name ] = self::$current_prefix . $route;
        foreach (self::ALL_HTTP_METHODS as $method) {
            if (!is_null($name)) {
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
                'backtrace'     =>  debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]
            ];
        }
    }

    public static function addRoute($httpMethod, $route, $handler, $name = null)
    {
        if (is_null($route) || is_null($handler)) {
            return;
        }

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];

        foreach ((array)$httpMethod as $method) {
            $httpMethod = $method;
            $key = self::getInternalRuleKey($httpMethod, $handler, $route);

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
                ],
                'backtrace'     =>  $backtrace
            ];
        }


        if (!is_null($name)) {
            self::$route_names[$name] = self::$current_prefix . $route;
        }
    }

    public static function group(string $prefix = '', string $namespace = '', $before = null, $after = null, callable $callback = null, array $alias = []):bool
    {
        if (empty($callback) && !self::$option_allow_empty_groups) {
            return false;
        }

        $_setPrefix = !empty($prefix);
        $_setNamespace = !empty($namespace);

        if ($_setPrefix) {
            self::$stack_prefix->push($prefix);
            self::$current_prefix = self::$stack_prefix->implode();
        }

        if ($_setNamespace) {
            self::$stack_namespace->push($namespace);
            self::$current_namespace = self::$stack_namespace->implode('\\');
        }

        $group_have_before_middleware = false;
        if (!is_null($before)) {
            self::$stack_middlewares_before->push($before);
            $group_have_before_middleware = true;
        }

        $group_have_after_middleware = false;
        if (!is_null($after)) {
            self::$stack_middlewares_after->push($after);
            $group_have_after_middleware = true;
        }

        if (\is_callable($callback)) {
            $callback();
        }

        if ($group_have_before_middleware) {
            self::$stack_middlewares_before->pop();
        }

        if ($group_have_after_middleware) {
            self::$stack_middlewares_after->pop();
        }

        if ($_setNamespace) {
            self::$stack_namespace->pop();
            self::$current_namespace = self::$stack_namespace->implode('\\');
        }

        if ($_setPrefix) {
            self::$stack_prefix->pop();
            self::$current_prefix = self::$stack_prefix->implode();
        }

        return true;
    }


    public static function getRouter(string $name = '', array $parts = []): array|string
    {
        if ($name === '*') {
            $set = [];
            foreach (self::$route_names as $name => $route) {
                $set[ $name ] = self::getRouter($name/*, $parts*/); //@todo: эксперименты!
            }
            return $set;
        }

        if ($name === '') {
            return self::$option_getroute_default_value;
        }

        if (array_key_exists($name, self::$route_names)) {
            $route = self::$route_names[ $name ];

            // заменяем именованные группы-плейсхолдеры на переданные переменные?
            if (!empty($parts)) {
                foreach ($parts as $key => $value) {
                    $pattern = "/\[?\{({$key})(\:\\\\\w+\+)?\}\]?/";
                    $route = preg_replace(
                        $pattern,
                        $value,
                        $route
                    );
                }
            }

            // заменяем необязательный слэш в конце на обязательный
            if (self::$option_getroute_replace_optional_slash_to_mandatory) {
                $route = preg_replace('/\[\/]$/', '/', $route);
            }

            // убираем из роута необязательные группы
            if (self::$option_getroute_remove_optional_groups) {
                $route = preg_replace('/\[.+\]$/', '', $route);
            }

            return $route;
        }

        return self::$option_getroute_default_value;
    }


    public static function getRoutersNames(): array
    {
        return self::$route_names;
    }

    public static function dispatch()
    {
        self::$dispatcher = \Arris\AppRouter\FastRoute\FastRoute::recommendedSettings(function (ConfigureRoutes $r){
            foreach (self::$rules as $rule) {
                $handler
                    = (is_string($rule['handler']) && !empty($rule['namespace']))
                    ? "{$rule['namespace']}\\{$rule['handler']}"
                    : $rule['handler'];

                if (self::$option_use_aliases) {
                    $route = str_contains($rule['route'], '{') ? self::applyAliases($rule['route']) : $rule['route'];
                } else {
                    $route = $rule['route'];
                }

                $r->addRoute($rule['httpMethod'], $route, $handler, $rule);
            }
        });

        // Fetch method and URI from somewhere
        self::$routeInfo = $routeInfo = (self::$dispatcher->dispatcher())->dispatch(self::$httpMethod, self::$uri);

        // $state = $routeInfo[0]; // тут ВСЕГДА что-то есть
        // $handler = $routeInfo[1] ?? [];
        // $method_parameters = $routeInfo[2] ?? [];

        [$state, $handler, $method_parameters] = $routeInfo + [null, null, []];

        // dispatch errors
        if ($state === Dispatcher::NOT_FOUND) {
            throw new AppRouterNotFoundException("URL not found", 404, [
                'uri'       =>  self::$uri,
                'method'    =>  self::$httpMethod,
                'info'      =>  self::$routeInfo,
                'rule'      =>  [
                    'backtrace'     =>  debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]
                ]
            ]);
        }

        if ($state === Dispatcher::METHOD_NOT_ALLOWED) {
            throw new AppRouterMethodNotAllowedException(sprintf("Method %s not allowed for URI %s", self::$httpMethod, self::$uri), 405, [
                'uri'       =>  self::$uri,
                'method'    =>  self::$httpMethod,
                'info'      =>  self::$routeInfo,
                'rule'      =>  self::$routeRule
            ]);
        }

        // Route Rule доступен только для Matched-роутов.
        self::$routeRule = $rule = $routeInfo[3] ?? [];

        $actor = self::compileHandler($handler, false, 'default');

        // Handler пустой или некорректный
        if (empty($handler) && !self::$option_allow_empty_handlers) {
            throw new AppRouterHandlerError("Handler not found or empty", 500, [
                'uri'       =>  self::$uri,
                'method'    =>  self::$httpMethod,
                'info'      =>  self::$routeInfo,
                'rule'      =>  self::$routeRule,
                // 'rule'      =>  [                   'backtrace'     =>  \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[0] ]
            ]);
        }

        /**
         * Посредники ПЕРЕД
         *
         * @var Stack $middlewares_before
         */
        $middlewares_before = $rule['middlewares']['before'] ?? null;

        if (!is_null($middlewares_before) && !$middlewares_before->isEmpty()) {
            $middlewares_before = $middlewares_before->reverse(); // инвертируем массив before-middlewares (иначе порядок будет неверный)

            do {
                $middleware_handler = $middlewares_before->pop();

                if (!is_null($middleware_handler)) {
                    $before = self::compileHandler($middleware_handler, true, 'before');

                    call_user_func_array($before, [ self::$uri, self::$routeInfo ] ); // если вот сейчас передан пустой хэндлер - никакой ошибки не будет - миддлвар просто не вызовется

                    unset($before);
                }
            } while (!$middlewares_before->isEmpty());
        }

        // c PHP8 поведение можно поменять?
        // self::$uri, self::$routeInfo как именованные параметры и первым параметром собственно $method_parameters
        // или вообще передавать в конструктор параметрами три экземпляра:
        // - Route_Params (implements ArrayAccess)
        // - HTTP_Request (implements ArrayAccess)
        // - HTTP_Response (implements ArrayAccess)
        // это нам даст ОО-подход к $_REQUEST итд.
        if (!empty($actor)) {
            call_user_func_array($actor, $method_parameters);
        }

        /**
         * Посредники ПОСЛЕ
         *
         * @var Stack $middlewares_after
         */
        $middlewares_after = $rule['middlewares']['after'] ?? null;

        if (!is_null($middlewares_after) && !$middlewares_after->isEmpty()) {
            do {
                $middleware_handler = $middlewares_after->pop();

                if (!is_null($middleware_handler)) {
                    $after = self::compileHandler($middleware_handler, true, 'after');

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
     * @return Dispatcher\Result\ResultInterface
     */
    public static function getRoutingInfo()
    {
        return self::$routeInfo;
    }

    /**
     * DEBUG: Возвращает список объявленных роутов: [ 'method: handler' => [ handler, namespace, extra params ]
     *
     * @return array
     */
    public static function getRoutingRules(): array
    {
        return self::$rules;
    }

    /**
     * Генерирует имя внутреннего ключа для массива именованных роутов
     * на основе метода и хэндлера:
     *
     * - Замыкание (Closure(LineStart-LineEnd)=аргумент1:аргумент2:аргумент3 или [таймштамп] Closure(<md5(1, 4096)>)=.
     * - Метод класса, переданный строкой
     * - Метод класса, переданный массивом [ класс, метод ]
     * - функция
     *
     * @param string $httpMethod
     * @param $handler
     * @param string $route
     * @param bool $append_namespace
     * @param string $force_use_namespace -- если не пусто - будет использован этот неймспейс вместо `self::$current_namespace`. Зачем?
     * Дело в том, что на этапе диспетчера current_namespace уже может быть пустым, а не тем, что соответствует роуту. Поэтому внутреннее имя будет вычислено неверно.
     * Поэтому будем передавать в `force_use_namespace` нэймспейс, который идет в $rule['namespace']
     * @return string
     */
    private static function getInternalRuleKey(string $httpMethod, $handler, string $route, bool $append_namespace = true, string $force_use_namespace = ''): string
    {
        $namespace = '';
        if ($append_namespace) {
            $namespace = self::$current_namespace;
            $namespace = "{$namespace}\\";
        }

        if (!empty($force_use_namespace)) {
            $namespace = $force_use_namespace;
        }

        if ($handler instanceof \Closure) {
            $internal_name = Helper::getClosureInternalName($handler);
        } elseif (is_array($handler)) {

            // Хэндлер может быть массивом, но пустым. Вообще, это ошибка, поэтому генерим имя на основе md5 роута и метода.
            $class = $handler[0] ?? md5(self::$httpMethod . ':' . self::$current_prefix . $route);
            $method = $handler[1] ?? '__invoke';

            $internal_name = "{$namespace}{$class}@{$method}";
        } elseif (is_string($handler)) {
            $internal_name = "{$namespace}{$handler}";
        } elseif (is_null($handler)) {
            return md5(self::$httpMethod . ':' . self::$current_prefix . $route); // никогда не вызывается, потому что is_null(handler) отсекается выше
        } else {
            // function by name
            $internal_name = $handler;
        }
        $r = self::$current_prefix . $route;

        return "{$httpMethod} {$r} {$internal_name}";
    }

    /**
     * Проверяет существование класса или кидает исключение AppRouterHandlerError
     *
     * @param $class
     * @param bool $is_static
     * @return void
     */
    private static function checkClassExists($class, bool $is_static = false): void
    {
        // быстрее, чем рефлексия
        if (!class_exists($class)){
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
    private static function checkMethodExists($class, $method, bool $is_static = false): void
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

        // быстрее, чем рефлексия
        if (!method_exists($class, $method)){
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
    private static function checkFunctionExists($handler): bool
    {
        if (!function_exists($handler)){
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
     * Компилирует хэндлер из строчки, замыкания или массива [класс, метод] в действующий хэндлер
     * с отловом ошибок несуществования роута
     *
     * @param $handler
     * @param bool $is_middleware
     * @return array|\Closure|string
     */
    private static function compileHandler($handler, bool $is_middleware = false): array|\Closure|string
    {
        if (empty($handler)) {
            return [];
        }

        // closure
        if ($handler instanceof \Closure) {
            return $handler;
        }

        // [ \Path\To\Class:class, "method" ]
        if (is_array($handler)) {
            [$class, $method] = $handler + [null, '__invoke'];

            self::checkClassExists($class);
            self::checkMethodExists($class, $method);

            if ($is_middleware) {
                if (array_key_exists($class, self::$instances_middlewares)) {
                    $i_class = self::$instances_middlewares[$class];
                } else {
                    $i_class = self::$instances_middlewares[$class] = new $class();
                }
            } else {
                // не миддлвар, а целевой вызов.
                $i_class = new $class(); //@todo: тут надо бы передавать параметры в конструктор, всякие HTTP_REQUEST, HTTP_RESPONSE
            }
            /*
            // Deepseek change:
            if ($is_middleware) {
                $i_class = self::$instances_middlewares[$class] ??= new $class();
                return [$i_class, $method];
            }
            return [new $class(), $method];
             */

            return [ $i_class, $method ];
        }

        // isString
        if (is_string($handler) && str_contains($handler, '@')) {
            // 'Class@method'

            list($class, $method) = Helper::explode($handler, [null, '__invoke'], '@');
            self::checkClassExists($class);
            self::checkMethodExists($class, $method);

            if ($is_middleware) {
                if (array_key_exists($class, self::$instances_middlewares)) {
                    $i_class = self::$instances_middlewares[$class];
                } else {
                    $i_class = self::$instances_middlewares[$class] = new $class();
                }

                $handler = [ $i_class, $method ];

            } else {
                // не миддлвар, а целевой вызов.
                try {
                    $reflection = new \ReflectionClass($class);
                    $reflected_method = $reflection->getMethod($method);

                    if ($reflected_method->isStatic()) {
                        $handler = [ $class, $method ];
                    } else {
                        //@todo: тут надо бы передавать параметры в конструктор, всякие HTTP_REQUEST, HTTP_RESPONSE
                        $handler = [ new $class(), $method ];
                    }

                } catch (\ReflectionException $e) {
                    // метод не существует!
                    // но это исключение никогда не будет кинуто, потому что наличие метода проверено выше

                    self::$logger->error("Method '{$method}' not defined at '{$class}'", [ self::$uri, self::$httpMethod, $class ]);
                    throw new AppRouterHandlerError("Method '{$method}' not defined at '{$class}'", 500, [
                        'uri'       =>  self::$uri,
                        'method'    =>  self::$httpMethod,
                        'info'      =>  self::$routeInfo,
                        'rule'      =>  self::$routeRule
                    ]);
                }
            }

            return $handler;
        }

        // остался вариант "функция"

        self::checkFunctionExists($handler);

        return $handler;
    }

    /**
     * Experimental - use only if str_contains('{')
     *
     * @param $route
     * @return array|string|string[]|null
     */
    private static function applyAliases($route): array|string|null
    {
        return preg_replace_callback('/\{(\w+)\}/', function($matches) {
            $paramName = $matches[1];
            if (isset(self::$stack_aliases[$paramName])) {
                return '{' . $paramName . ':' . self::$stack_aliases[$paramName] . '}';
            }
            return $matches[0]; // Если алиас не найден, оставляем как есть
        }, $route);
    }

    /**
     * @inheritDoc
     */
    public static function addAlias(array|string $name, ?string $regexp = null): void
    {
        if (is_array($name)) {
            foreach ($name as $_v) {
                self::addAlias(key($_v), current($_v));
            }
        } elseif (!is_null($regexp)) {
            self::$stack_aliases[$name] = $regexp;
        }
    }

    /**
     * @inheritDoc
     */
    public static function getAliases():array
    {
        return self::$stack_aliases;
    }

}

# -eof- #