# Arris.AppRouter (`karelwintersky/arris.router`)

Статический роутер для Arris µFramework на базе форка [nikic/FastRoute](https://github.com/nikic/FastRoute) v2.
Поддерживает группы роутов, middleware (`before`/`after`), обратный роутинг и алиасы плейсхолдеров (BETA).

---

## Оглавление

- [Установка](#установка)
- [Быстрый старт](#быстрый-старт)
- [Инициализация](#инициализация)
- [Определение маршрутов](#определение-маршрутов)
- [Группировка маршрутов](#группировка-маршрутов)
- [Middleware](#middleware)
- [Обратный роутинг](#обратный-роутинг)
- [Алиасы параметров (BETA)](#алиасы-параметров-beta)
- [Обработка исключений](#обработка-исключений)
- [Настройки](#настройки)
- [Отладка](#отладка)
- [Примеры](#примеры)
- [API Reference](#api-reference)
- [Совместимость](#совместимость)

---

## Установка

```bash
composer require karelwintersky/arris.router
```

Зависимости: PHP `8.*`, `psr/log`. Ядро FastRoute входит в пакет и внешне не требуется.

## Быстрый старт

```php
use Arris\AppRouter;
use Arris\Exceptions\{
    AppRouterHandlerError,
    AppRouterMethodNotAllowedException,
    AppRouterNotFoundException
};

AppRouter::init();

AppRouter::get('/', function() { echo 'Главная'; }, 'home');
AppRouter::get('/about', 'AboutController@index', 'about');

try {
    AppRouter::dispatch();
} catch (AppRouterNotFoundException $e) {
    http_response_code(404);
    echo 'Страница не найдена';
} catch (AppRouterMethodNotAllowedException $e) {
    http_response_code(405);
    echo 'Метод не разрешен';
} catch (AppRouterHandlerError $e) {
    http_response_code(500);
    echo 'Ошибка сервера';
}
```

`AppRouter` — статический класс: `init()` считывает `$_SERVER['REQUEST_URI']` и `$_SERVER['REQUEST_METHOD']`
напрямую, роуты декларируются вызовами, `dispatch()` выполняет матчинг и вызывает обработчик.

## Инициализация

```php
AppRouter::init(
    logger?: Psr\Log\LoggerInterface,  // логгер (по умолчанию NullLogger)
    namespace?: string,                // неймспейс по умолчанию для строковых хэндлеров
    prefix?: string,                   // глобальный префикс URL
    allowEmptyGroups?: bool,           // разрешить пустые группы (false)
    allowEmptyHandlers?: bool,         // разрешить пустые обработчики (false)
    useAliases?: bool,                 // использовать алиасы параметров (false)
    customDataSource?: array            // источник данных запроса, эмулятор $_SERVER (null = $_SERVER)
);
```

`customDataSource` — массив, эмулирующий `$_SERVER` как источник данных запроса: ключи
соответствуют `$_SERVER` (`REQUEST_URI`, `REQUEST_METHOD`). По умолчанию `null` → используется
`$_SERVER`. Позволяет запускать роутер из CLI и писать тесты без HTTP-суперглобалов:

```php
AppRouter::init(
    allowEmptyHandlers: true,
    customDataSource: ['REQUEST_URI' => '/admin/users/', 'REQUEST_METHOD' => 'GET']
);
```

Отсутствующие ключи не роняют `init()`: `REQUEST_URI` → `/`, `REQUEST_METHOD` → `GET`.

```php
// через конструктор (те же опции, кроме useAliases)
$router = new AppRouter(logger: $logger, namespace: 'App\Controllers', prefix: '/api/v1');

// через setOption
AppRouter::init();
AppRouter::setOption(AppRouter::OPTION_ALLOW_EMPTY_HANDLERS, true);
AppRouter::setOption(AppRouter::OPTION_DEFAULT_ROUTE, '/404');
```

## Определение маршрутов

```php
AppRouter::get($route, $handler, $name = null);
AppRouter::post($route, $handler, $name = null);
AppRouter::put($route, $handler, $name = null);
AppRouter::patch($route, $handler, $name = null);
AppRouter::delete($route, $handler, $name = null);
AppRouter::head($route, $handler, $name = null);
AppRouter::options($route, $handler, $name = null);

// все методы сразу
AppRouter::any($route, $handler, $name = null);

// произвольный набор методов
AppRouter::addRoute(['GET', 'POST'], $route, $handler, $name = null);
```

Синтаксис маршрута — FastRoute v2:

- статические части: `/user/list/`
- плейсхолдеры: `/user/{id}` (по умолчанию `[^/]+`), с регуляркой: `/user/{id:\d+}`
- опциональные группы: `/user/{id}[/{action}]`, `/add[/]`
- опциональными могут быть только оконечные группы.

### Форматы обработчика

| Формат | Поведение |
|---|---|
| `function () { ... }` | Closure |
| `[ Class::class, 'method' ]` | класс инстанциируется (если не зарегистрирован через `addHandler()`), вызывается метод |
| `'Class@method'` | тип метода определяется рефлексией; динамический метод → инстанс класса |
| `'Class@'` | вызывается `__invoke()` класса |
| `'function_name'` | обычная функция |
| `[]` | пустой хэндлер: прогоняются только middleware; требует `allowEmptyHandlers: true` |
| `null` | роут **не регистрируется** — запрос упадёт в `AppRouterNotFoundException` (404) |

### Именование маршрутов

```php
AppRouter::get('/user/{id}/edit', 'UserController@edit', 'user.edit');
$url = AppRouter::getRouter('user.edit', ['id' => 42]); // => /user/42/edit
```

## Группировка маршрутов

```php
AppRouter::group(
    prefix: '/admin',
    namespace: 'App\Controllers\Admin',   // префикс неймспейса для строковых хэндлеров группы
    before: 'AuthMiddleware@check',
    after: 'LogMiddleware@log',
    callback: function () {
        AppRouter::get('/dashboard', 'DashboardController@index'); // => /admin/dashboard
        AppRouter::get('/settings', 'SettingsController@index');
    }
);
```

Сигнатура: `group(prefix='', namespace='', before=null, after=null, callback=null, alias=[])`.

> **Важно:** `callback` — пятый (именованный) параметр. `AppRouter::group('/admin', function () {...})`
> позиционно передаст замыкание в `$namespace` и вызовет `TypeError`. Всегда используйте именованные
> аргументы (`callback:`) либо полный позиционный набор.

Группы вкладываются (стек префиксов/неймспейсов/миддлваров). При исключении внутри `callback` стеки
восстанавливаются, а исключение уходит дальше по иерархии (обёртка `try/finally`).

```php
AppRouter::group('/api', '', null, null, function () {
    AppRouter::group('/v1', '', null, null, function () {
        AppRouter::get('/users', 'Api\V1\UserController@index'); // => /api/v1/users
    });
});
```

## Middleware

Регистрируются для группы в `before`/`after` и выполняются вокруг обработчика маршрута:

```php
AppRouter::group(
    prefix: '/admin',
    before: 'AuthMiddleware@check',
    after: 'LogMiddleware@log',
    callback: function () { /* роуты */ }
);
```

Форматы: строка `'Class@method'`, массив `[Class::class, 'method']`, Closure, имя функции.
Метод middleware вызывается с аргументами `($uri, $routeInfo)`.

Порядок выполнения для вложенных групп `GET /admin/users/list`:

```
1. Middleware1::before   (внешняя группа)
2. Middleware2::before   (внутренняя группа)
3. UserController::list  (обработчик)
4. Middleware2::after
5. Middleware1::after
```

Инстансы middleware можно зарегистрировать заранее (имитация контейнера):

```php
AppRouter::addHandlerMiddleware(AuthMiddleware::class, new AuthMiddleware());
AppRouter::addHandler(UserController::class, new UserController($db)); // то же для обработчиков
```

Неймспейс для middleware отдельно задавать не нужно — классы передаются с полным именем (FQN).

## Обратный роутинг

```php
AppRouter::get('/blog/{category}/{slug}', 'BlogController@show', 'blog.post');

$url = AppRouter::getRouter('blog.post', ['category' => 'news', 'slug' => 'hello-world']);
// => /blog/news/hello-world
```

- `getRouter('*')` — возвращает массив всех именованных маршрутов.
- `getRouter('')` — значение по умолчанию (по умолчанию `/`, см. `OPTION_DEFAULT_ROUTE`).
- Неизвестное имя — значение по умолчанию.
- Именованные плейсхолдеры (в т.ч. `{name:regex}`) заменяются переданными значениями;
  не переданные — остаются как есть.
- Оконечный необязательный слэш `[/]` заменяется на обязательный, необязательные группы удаляются.

Значения подставляются без интерпретации (не трактуются как regex-backreference).

Использование в Smarty-шаблонах (требует регистрации класса):

```php
$smarty->registerClass('Arris\AppRouter', 'Arris\AppRouter');
```

```smarty
<a href="{Arris\AppRouter::getRouter('blog.post', $post)}">{$post.title}</a>
```

## Алиасы параметров (BETA)

Включаются опцией `OPTION_USE_ALIASES`. Алиасы глобальные (задать алиасы только для группы нельзя).

```php
AppRouter::setOption(AppRouter::OPTION_USE_ALIASES, true);

AppRouter::addAlias('user_id', '\d+');
AppRouter::addAlias('username', '[a-zA-Z]+');
// или массивом
AppRouter::addAlias([['user_id' => '\d+'], ['username' => '[a-zA-Z]+']]);

AppRouter::get('/user/{user_id}', function ($user_id) {...}, 'user.by_id');
AppRouter::get('/user/{username}', function ($username) {...}, 'user.by_name');
```

При запросе `/user/123/` сработает `user.by_id`, при `/user/wombat/` — `user.by_name`.
Без алиасов оба роута раскрываются в `([^/]+)` и конфликтуют (`BadRouteException`).
Обратный роутинг для алиасов работает штатно.

## Обработка исключений

| Исключение | Код | Случай |
|---|---|---|
| `AppRouterNotFoundException` | 404 | маршрут не найден |
| `AppRouterMethodNotAllowedException` | 405 | метод не разрешён для маршрута |
| `AppRouterHandlerError` | 500 | ошибка обработчика (пустой, несуществующий класс/метод/функция) |

Все наследуются от `Arris\Exceptions\AppRouterException` (и `RuntimeException`).
Кроме `getMessage()` доступны:

```php
catch (AppRouterNotFoundException $e) {
    $e->getError();         // строка с описанием и указанием места декларации роута
    $e->getInfo();          // ['request', 'uri', 'method', 'info', 'rule']
    $e->getInfo('uri');     // конкретный ключ
}
```

## Настройки

```php
AppRouter::OPTION_ALLOW_EMPTY_GROUPS      // разрешить пустые группы (default false)
AppRouter::OPTION_ALLOW_EMPTY_HANDLERS    // разрешить пустые хэндлеры [] (default false)
AppRouter::OPTION_DEFAULT_ROUTE           // значение getRouter() для пустых/ненайденных имён (default '/')
AppRouter::OPTION_USE_ALIASES             // включить алиасы параметров (default false)

AppRouter::setOption($name, $value);
```

Замечания:

- `setDefaultNamespace()` и опция `namespace` в `init()`/`group()` — префикс неймспейса только для
  **строковых** хэндлеров (`'Class@method'`); на массивные `[Class::class, 'method']` не влияют.
- Роуты компилируются (собираются в FastRoute-диспетчер) **при каждом вызове `dispatch()`** —
  кеш скомпилированных правил не используется.

## Отладка

```php
// все правила маршрутизации (включая backtrace декларации)
$rules = AppRouter::getRoutingRules();

// WEB-таблица
echo \Arris\AppRouter\Helper::dumpRoutingRulesWeb($rules, /*withMiddlewares*/ true);

// CLI-таблица
echo \Arris\AppRouter\Helper::dumpRoutingRulesCLI($rules);

// имя текущего роута, список имён, алиасы
$info     = AppRouter::getRoutingInfo();   // результат последнего dispatch()
$names    = AppRouter::getRoutersNames();
$aliases  = AppRouter::getAliases();
```

## Примеры

Полный пример приложения:

```php
use Arris\AppRouter;
use Arris\Exceptions\{
    AppRouterNotFoundException,
    AppRouterMethodNotAllowedException,
    AppRouterHandlerError
};

class AuthMiddleware {
    public function check($uri, $routeInfo) {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
    }
}

AppRouter::init(namespace: 'App\Controllers');

AppRouter::get('/', 'HomeController@index', 'home');
AppRouter::get('/about', 'HomeController@about', 'about');

AppRouter::group(
    prefix: '/auth',
    callback: function () {
        AppRouter::get('/login', 'AuthController@loginForm', 'auth.login');
        AppRouter::post('/login', 'AuthController@login');
    }
);

AppRouter::group(
    prefix: '/user',
    before: 'AuthMiddleware@check',
    callback: function () {
        AppRouter::get('/profile', 'UserController@profile', 'user.profile');
        AppRouter::group(
            prefix: '/posts',
            callback: function () {
                AppRouter::get('/', 'PostController@index', 'user.posts');
                AppRouter::get('/{id}/edit', 'PostController@editForm', 'user.posts.edit');
                AppRouter::post('/{id}/edit', 'PostController@update');
            }
        );
    }
);

try {
    AppRouter::dispatch();
} catch (AppRouterNotFoundException $e) {
    http_response_code(404);
} catch (AppRouterMethodNotAllowedException $e) {
    http_response_code(405);
} catch (AppRouterHandlerError $e) {
    http_response_code(500);
    if (defined('DEBUG') && DEBUG) {
        echo $e->getMessage();
    }
}
```

## API Reference

Основные методы:

| Метод | Описание |
|---|---|
| `init(...$options)` | Инициализация роутера |
| `get/post/put/patch/delete/head/options($route, $handler, $name = null)` | Маршруты по методам |
| `any($route, $handler, $name = null)` | Маршрут для всех методов |
| `addRoute($httpMethod, $route, $handler, $name = null)` | Произвольный(ые) метод(ы) |
| `group($prefix, $namespace, $before, $after, $callback, $alias)` | Группа роутов |
| `dispatch()` | Матчинг и выполнение текущего запроса |
| `getRouter($name, $parts = [])` | Обратный роутинг |

Вспомогательные:

| Метод | Описание |
|---|---|
| `setOption($name, $value)` | Установка опции |
| `setDefaultNamespace($namespace)` | Неймспейс по умолчанию для строковых хэндлеров |
| `addHandler($name, $instance)` | Предрегистрация инстанса обработчика |
| `addHandlerMiddleware($name, $instance)` | Предрегистрация инстанса middleware |
| `addAlias($name, $regexp = null)` | Добавление алиаса параметра |
| `getRoutingRules()` | Все правила маршрутизации |
| `getRoutingInfo()` | Результат последнего `dispatch()` |
| `getRoutersNames()` | Список имён маршрутов |
| `getAliases()` | Список алиасов |

## Совместимость

- PHP `8.*`
- PSR-3 Logger Interface
- FastRoute v2 встроен в пакет; PSR-7 не используется

## Лицензия

MIT
