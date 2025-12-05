<?php

namespace Arris;

use Psr\Log\LoggerInterface;

interface AppRouterInterface
{
    /**
     * Конструктор, инициализирующий статик класс
     *
     * @param LoggerInterface|null $logger
     * @param string $namespace
     * @param string $prefix
     * @param bool $allowEmptyGroups
     * @param bool $allowEmptyHandlers
     */
    public function __construct(
        LoggerInterface $logger = null,
        string $namespace = '',
        string $prefix = '',
        bool $allowEmptyGroups = false,
        bool $allowEmptyHandlers = false,
    );

    /**
     * Инициализирует статик-класс
     *
     * @param LoggerInterface|null $logger
     *
     * @param string $namespace
     * @param string $prefix
     * @param bool $allowEmptyGroups
     * @param bool $allowEmptyHandlers
     * @param bool $useAliases
     */
    public static function init(
        LoggerInterface $logger = null,
        string $namespace = '',
        string $prefix = '',
        bool $allowEmptyGroups = false,
        bool $allowEmptyHandlers = false,
        bool $useAliases = false
    );

    /**
     * Устанавливает кастомные опции:
     * - allowEmptyGroups - разрешить ли пустые группы? (НЕТ)
     * - allowEmptyHandlers - разрешить ли пустые хэндлеры (НЕТ)
     * - getRouterDefaultValue - роут по-умолчанию, если имя не найдено (/)
     *
     * @param string $name
     * @param null $value
     * @return void
     */
    public static function setOption(string $name, $value = null): void;

    /**
     * Устанавливает namespace по умолчанию (дублируется в опциях init() )
     *
     * @param string $namespace
     * @return void
     */
    public static function setDefaultNamespace(string $namespace = ''):void;

    /**
     * Указывает нэймспейс для миддлваров-посредников
     * @todo: НЕ РЕАЛИЗОВАНО
     *
     * @param string $namespace
     * @return void
     */
    public static function setMiddlewaresNamespace(string $namespace = ''): void;

    /**
     * Добавляет хэндлер-интанс по имени класса в массив предопределенных до роутига инстансов, что
     * позволяет инстанциировать обработчики роутов заранее.
     *
     * Имена должны передаваться с FQDN
     *
     * @param string $name
     * @param object|callable $class
     * @return void
     */
    public static function addHandler(string $name, object|callable $class):void;

    /**
     * Добавляет хэндлер-инстанс по имени класса в массив посредников.
     * Это позволяет инстанциировать обработчик посредника заранее.
     *
     * @param string $name
     * @param object|callable $class
     * @return void
     */
    public static function addHandlerMiddleware(string $name, object|callable $class):void;

    /**
     * Helper method GET
     *
     * @param $route
     * @param $handler
     * @param $name - route internal name
     * @return void
     */
    public static function get($route, $handler, $name = null): void;

    /**
     * Helper method POST
     *
     * @param $route
     * @param $handler
     * @param null $name
     * @return void
     */
    public static function post($route, $handler, $name = null): void;

    /**
     * Helper method PUT
     *
     * @param $route
     * @param $handler
     * @param null $name
     * @return void
     */
    public static function put($route, $handler, $name = null): void;

    /**
     * Helper method PATCH
     *
     * @param $route
     * @param $handler
     * @param null $name
     * @return void
     */
    public static function patch($route, $handler, $name = null): void;

    /**
     * Helper method DELETE
     *
     * @param $route
     * @param $handler
     * @param null $name
     * @return void
     */
    public static function delete($route, $handler, $name = null): void;

    /**
     * Helper method HEAD
     *
     * @param $route
     * @param $handler
     * @param null $name
     * @return void
     */
    public static function head($route, $handler, $name = null): void;

    /**
     * Helper method OPTIONS
     *
     * @param $route
     * @param $handler
     * @param $name
     * @return void
     */
    public static function options($route, $handler, $name = null): void;

    /**
     * Устанавливает роут для ВСЕХ методов
     *
     * @param $route
     * @param $handler
     * @param null $name
     * @return void
     */
    public static function any($route, $handler, $name = null): void;

    /**
     * Add route method
     * Добавляет роут с прямым указанием метода
     *
     * @param $httpMethod
     * @param $route
     * @param $handler
     * @param null $name
     */
    public static function addRoute($httpMethod, $route, $handler, $name = null);

    /**
     * Create routing group with options
     * Создает группу роутов
     *
     * @param string $prefix - prefix (URL prefix)
     * @param string $namespace - namespace
     * @param null $before - before (middleware handler)
     * @param null $after - after (middleware handler)
     * @param callable|null $callback inline callback function with group definition
     * @param array $alias
     * @return bool
     */
    public static function group(string $prefix = '', string $namespace = '', $before = null, $after = null, callable $callback = null, array $alias = []): bool;

    /**
     * Dispatch routing
     *
     * @throws \Exception
     */
    public static function dispatch();

    /**
     * Возвращает информацию о роуте по имени
     *
     * @param string $name - имя роута
     * @param array $parts - массив замен именованных групп на параметры
     * @return string|array
     */
    public static function getRouter(string $name = '', array $parts = []): array|string;

    /**
     * Возвращает информацию о текущем роутинге
     *
     * @return array
     */
    public static function getRoutingInfo();

    /**
     * @return array
     */
    public static function getRoutersNames(): array;

    /**
     * Возвращает список объявленных роутов: [ 'method route' => [ handler, namespace, name ]
     *
     * @return array
     */
    public static function getRoutingRules(): array;

    /**
     * Experimental - addAlias
     *
     * @param array|string $name
     * @param string|null $regexp
     * @return void
     */
    public static function addAlias(array|string $name, ?string $regexp = null): void;

    /**
     * Experimental: возвращает список алиасов
     *
     * @return array
     */
    public static function getAliases():array;

}