<?php

namespace Arris;

use Arris\AppRouter\FastRoute\ConfigureRoutes;
use Arris\AppRouter\FastRoute\Dispatcher;
use Arris\AppRouter\Stack;
use Arris\Exceptions\AppRouterHandlerError;
use Arris\Exceptions\AppRouterMethodNotAllowedException;
use Arris\Exceptions\AppRouterNotFoundException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function call_user_func_array;
use function class_exists;
use function debug_backtrace;
use function function_exists;
use function implode;
use function is_array;
use function is_null;
use function is_object;
use function is_string;
use function md5;
use function method_exists;
use function mt_rand;
use function preg_replace;
use function rawurldecode;
use function strpos;
use function substr;

class AppRouter implements AppRouterInterface
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
    private static $logger;

    /**
     * @var
     */
    private static $httpMethod;

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
     * Default replace pattern
     *
     * @var string
     */
    // private static string $routeReplacePattern = '%%$1%%';

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
     * @var Stack
     */
    private static Stack $stack_aliases;

    /**
     * @var null
     */
    // private static $current_middleware_before = null;

    /**
     * @var null
     */
    // private static $current_middleware_after = null;

    /**
     * В планах: дать возможность указывать нэйспейс для миддлваров, но не реализован. Зачем?
     * @var string
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
     * Разрешать ли пустые группы (без роутов, но с опциями или миддлварами)
     * @var bool $option_allow_empty_groups
     */
    private static bool $option_allow_empty_groups = false;

    /**
     * @var bool Разрешать пустые хэндлеры ([])
     */
    private static bool $option_allow_empty_handlers = false;

    /**
     * getRouter: Заменять конечный необязательный слэш на обязательный
     * @var bool
     */
    private static bool $option_getroute_replace_optional_slash_to_mandatory = true;

    /**
     * getRouter: убирать ли опциональные группы?
     * @var bool
     */
    private static bool $option_getroute_remove_optional_groups = true;

    /**
     * getRouter: значение роута по-умолчанию
     * @var string
     */
    private static string $option_getroute_default_value = '/';


    /**
     * @param LoggerInterface|null $logger
     * @param array $options
     */
    public function __construct(LoggerInterface $logger = null, array $options = []) {
        self::init($logger, $options);
    }

    public static function init(LoggerInterface $logger = null, array $options = [])
    {
        self::$route_parts = \preg_split("/\/+/", preg_replace("/(\?.*)/", "", trim($_SERVER['REQUEST_URI'], '/')));

        self::$logger
            = ($logger instanceof LoggerInterface)
            ? $logger
            : new NullLogger();

        self::$httpMethod = $_SERVER['REQUEST_METHOD'];

        $uri = $_SERVER['REQUEST_URI'];
        $uri = strstr($uri, '?', true) ?: $uri;

        self::$uri = rawurldecode($uri);

        if (array_key_exists('defaultNamespace', $options)) {
            self::setDefaultNamespace($options['defaultNamespace']);
        } elseif (array_key_exists('namespace', $options)) {
            self::setDefaultNamespace($options['namespace']);
        }

        if (array_key_exists('middlewareNamespace', $options)) {
            self::setMiddlewaresNamespace($options['middlewareNamespace']);
        }

        if (array_key_exists('prefix', $options)) {
            self::$current_prefix = $options['prefix'];
        }

        //@todo: документация!
        /*if (array_key_exists('routeReplacePattern', $options)) {
            self::$routeReplacePattern = $options['routeReplacePattern'];
        }*/

        if (array_key_exists('allowEmptyGroups', $options)) {
            self::$option_allow_empty_groups = (bool)$options['allowEmptyGroups'];
        }

        if (array_key_exists('allowEmptyHandlers', $options)) {
            self::$option_allow_empty_handlers = (bool)$options['allowEmptyHandlers'];
        }

        self::$stack_prefix = new Stack();

        self::$stack_namespace = new Stack();

        self::$stack_middlewares_before = new Stack();

        self::$stack_middlewares_after = new Stack();

        self::$stack_aliases = new Stack();
    }

    public static function setOption($name, $value = null)
    {
        switch ($name) {
            /*case 'routeReplacePattern': {
                self::$routeReplacePattern = $value;
                break;
            }*/
            case 'allowEmptyGroups': {
                self::$option_allow_empty_groups = (bool)$value;
                break;
            }
            case 'allowEmptyHandlers': {
                self::$option_allow_empty_handlers = (bool)$value;
                break;
            }
            case 'getRouterDefaultValue': {
                self::$option_getroute_default_value = $value;
                break;
            }
        }
    }

    public static function setDefaultNamespace(string $namespace = '')
    {
        self::$current_namespace = $namespace;
    }

    /**
     * Указывает нэймспейс для миддлваров-посредников
     *
     * @param string $namespace
     * @return bool
     */
    public static function setMiddlewaresNamespace(string $namespace = ''):bool
    {
        return true;
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

    /**
     * @param string $prefix
     * @param string $namespace
     * @param null $before
     * @param null $after
     * @param callable|null $callback
     *
     * @return bool
     */
    public static function group(array $options = [], callable $callback = null):bool
    {
        if (empty($callback) && !self::$option_allow_empty_groups) {
            return false;
        }

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

        // Проверка is_hander лишняя, поскольку позже, в диспетчере, всё равно выполняется компиляция хэндлера.
        // Там и кидаются все исключения.

        $group_have_before_middleware = false;
        if (array_key_exists('before', $options)) {
            self::$stack_middlewares_before->push($options['before']);
            $group_have_before_middleware = true;
        }

        $group_have_after_middleware = false;
        if (array_key_exists('after', $options)) {
            self::$stack_middlewares_after->push($options['after']);
            $group_have_after_middleware = true;
        }

        if (\is_callable($callback)) {
            $callback();
        }

        if ($group_have_before_middleware) {
            /*self::$current_middleware_before =*/ self::$stack_middlewares_before->pop();
        }

        if ($group_have_after_middleware) {
            /*self::$current_middleware_after =*/ self::$stack_middlewares_after->pop();
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

    /**
     * Возвращает информацию о роуте по имени
     *
     * @param string $name - имя роута
     * @param array $parts - массив замен именованных групп на параметры
     * @return string|array
     */
    public static function getRouter($name = '', array $parts = [])
    {
        if ($name === '*') {
            return self::$route_names;
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

            /*if ($replace_parts) {
                $route = preg_replace(
                    '/{([[:word:]]+)}/',
                    self::$routeReplacePattern,
                    $route
                );
            }*/

            // заменяем необязательный слэш в конце на обязательный
            if (self::$option_getroute_replace_optional_slash_to_mandatory) {
                $route = preg_replace('/\[\/]$/', '/', $route);
            }

            if (self::$option_getroute_remove_optional_groups) {
                // убираем из роута необязательные группы
                $route = preg_replace('/\[.+\]$/', '', $route);
            }

            return $route;
        }

        return self::$option_getroute_default_value;
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
        self::$dispatcher = \Arris\AppRouter\FastRoute\FastRoute::recommendedSettings(function (ConfigureRoutes $r){
            foreach (self::$rules as $rule) {
                $handler
                    = (is_string($rule['handler']) && !empty($rule['namespace']))
                    ? "{$rule['namespace']}\\{$rule['handler']}"
                    : $rule['handler'];

                $r->addRoute($rule['httpMethod'], $rule['route'], $handler, $rule);
            }
        });

        // Fetch method and URI from somewhere
        self::$routeInfo = $routeInfo = (self::$dispatcher->dispatcher())->dispatch(self::$httpMethod, self::$uri);

        $state = $routeInfo[0]; // тут ВСЕГДА что-то есть
        $handler = $routeInfo[1] ?? [];
        $method_parameters = $routeInfo[2] ?? [];

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
        self::$routeRule = $rule = $routeInfo[3];

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
     * @return array|Dispatcher\Result\ResultInterface
     */
    public static function getRoutingInfo()
    {
        return self::$routeInfo;
    }

    /**
     * Возвращает список объявленных роутов: [ 'method: handler' => [ handler, namespace, extra params ]
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
     * - Замыкание ([таймштамп] Closure(LineStart-LineEnd)=аргумент1:аргумент2:аргумент3 или [таймштамп] Closure(<md5(1, 4096)>)=.
     * - Метод класса, переданный строкой
     * - Метод класса, переданный массивом [ класс, метод ]
     * - функция
     *
     * @param $httpMethod
     * @param $handler
     * @param $route
     * @param bool $append_namespace
     * @param string $force_use_namespace -- если не пусто - будет использован этот неймспейс вместо `self::$current_namespace`. Зачем?
     * Дело в том, что на этапе диспетчера current_namespace уже может быть пустым, а не тем, что соответствует роуту. Поэтому внутреннее имя будет вычислено неверно.
     * Поэтому будем передавать в `force_use_namespace` нэймспейс, который идет в $rule['namespace']
     * @return string
     */
    private static function getInternalRuleKey($httpMethod, $handler, $route, bool $append_namespace = true, string $force_use_namespace = ''): string
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
            $internal_name = self::getClosureInternalName($handler);
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
    public static function checkClassExists($class, bool $is_static = false)
    {
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
    public static function checkFunctionExists($handler): bool
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

    /**
     * Компилирует хэндлер из строчки, замыкания или массива [класс, метод] в действующий хэндлер
     * с отловом ошибок несуществования роута
     *
     * @param $handler
     * @param bool $is_middleware
     * @param string $target
     * @return array|\Closure
     */
    public static function compileHandler($handler, bool $is_middleware = false)
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
            if (count($handler) == 2) {
                list($class, $method) = $handler;
            } else {
                $class = $handler[0];
                $method = '__invoke';
            }

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

            return [ $i_class, $method ];
        }

        // isString
        if (is_string($handler) && strpos($handler, '@') !== false) {
            // 'Class@method'

            list($class, $method) = self::explode($handler, [null, '__invoke'], '@');
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
        return array_map(static function($first, $second) {
            return empty($second) ? $first : $second;
        }, $default, \explode($separator, $income));
    }

}



# -eof-