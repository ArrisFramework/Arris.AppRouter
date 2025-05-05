<?php

namespace Arris\AppRouter;

interface AppRouterHelperInterface
{
    /**
     * Превращает дамп внутренней таблицы роутов в WEB-таблицу.
     *
     * echo getRoutingTable(AppRouter::getRoutingRules());
     *
     * @param array $routingRules
     * @param bool $withMiddlewares
     * @param bool $withIcons
     * @param bool $withFooter
     * @return string
     */
    public static function dumpRoutingRulesWeb(array $routingRules, bool $withMiddlewares = true, bool $withIcons = false, bool $withFooter = false): string;

    /**
     * Превращает дамп внутренней таблицы роутов в CLI-таблицу
     *
     * echo dumpRoutingRulesCLI(AppRouter::getRoutingRules());
     *
     * @param array $routingRules
     * @param bool $withMiddlewares
     * @return string
     */
    public static function dumpRoutingRulesCLI(array $routingRules, bool $withMiddlewares = false):string;

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
    public static function explode($income, array $default = [ null, '__invoke' ], string $separator = '@'): array;

    /**
     * Возвращает "внутреннее" имя замыкание, сгенерированное на основе таймштампа (до мс) и аргументов функции
     * Closure(LineStart-LineEnd)=аргумент1:аргумент2:аргумент3
     *
     * Или, если возникло исключение ReflectionException
     * Closure(<md5(1, 4096)>)=.
     *
     * @param $closure
     * @return string
     */
    public static function getClosureInternalName($closure): string;

}