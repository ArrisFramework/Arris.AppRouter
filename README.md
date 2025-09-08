# About

Попытка написать свой роутер на базе https://github.com/nikic/FastRoute

Реализует возможность статического класса AppRouter.

- `init()`
- `get()`
- `post()`
- etc

# Пример использования:

```php
use Arris\AppRouter;
use Arris\Exceptions\{
    AppRouterHandlerError,
    AppRouterMethodNotAllowedException,
    AppRouterNotFoundException
};

try {
    AppRouter::init(
        logger: null,
        allowEmptyHandlers: true,
    );

    AppRouter::get('/', [ DynamicClass::class, 'present_dynamic_method'], 'root');

    AppRouter::get('/function/', 'example_function', 'root.function_call');

    AppRouter::group(
        prefix: '/admin',
        before: 'MiddleAdmin@before',
        after: [ MiddleAdmin::class, 'after' ],
        callback: function () {
            AppRouter::get('/', function () { d('this is simple closure'); }, 'admin.root');

            AppRouter::get('/foo[/]', 'StaticClass@present_static_method', 'admin.foo');

            AppRouter::get('/list/', [StaticClass::class, 'present_static_method'], 'admin.list');

            AppRouter::group(
                prefix: '/users',
                before: [MiddleAdminUsers::class, 'before'],
                after: [MiddleAdminUsers::class, 'after'],
                callback: static function() {
                    AppRouter::get('/', [ DynamicClass::class, 'users'], 'admin.users.root');
                    AppRouter::get('/all/', 'DynamicClass@all', 'admin.users.all');
                    AppRouter::get('/invoke/', 'DynamicClass@' , 'admin.users.invoke');
                    AppRouter::get('/list/', [StaticClass::class, 'method_not_exist'], 'admin.users.list');
                    AppRouter::get('/empty/[{id:\d+}[/]]', /*[ DynamicClass::class, 'create']*/ [] , 'admin.users.empty');
                }
            );
        }
    );

    AppRouter::dispatch();

} catch (AppRouterHandlerError|AppRouterNotFoundException|AppRouterMethodNotAllowedException $e) {
    var_dump($e->getMessage());
} catch (RuntimeException|Exception $e) {
    var_dump($e);
    echo "<br>" . PHP_EOL;
}
```

# Детали

## init - Инициализация роутера

```php
AppRouter::init(
    logger: AppLogger::scope('routing'),
    /* other options */
); 
```

Опции:

- `namespace` - неймспейс по-умолчанию, может быть задан вызовом `AppRouter::setDefaultNamespace()`
- `prefix` - префикс URL (аналогично поведению для групп)
- `allowEmptyHandlers` (false) - разрешить ли пустые хэндлеры?
- `allowEmptyGroups` (false) - разрешить ли пустые группы?

## setOption - переопределение опций

Некоторые опции могут быть переопределены только вызовом:

```php
AppRouter::setOption(name, value);
```

Допустимые имена опций:
- `AppRouter::OPTION_ALLOW_EMPTY_HANDLERS` - разрешить пустые (заданные как `[]`) хэндлеры? Если false - кидается исключение `AppRouterHandlerError: Handler not found or empty`.
- `AppRouter::OPTION_ALLOW_EMPTY_GROUPS` - разрешить ли пустые группы? Пустой считается группа без роутов. Если разрешено - для такой группы будут парситься миддлвары и опции.
- `AppRouter::OPTION_DEFAULT_ROUTE` - дефолтное значение для реверс-роутинга
- `AppRouter::OPTION_USE_ALIASES` - разрешить ли алиасы?


## Декларация роутов

Методы: `get`, `post`, `put`, `patch`, `delete`, `head`, `options`

```php
AppRouter::method(
    route: '/my/awesome/uri/',
    handler: хэндлер,
    name: 'имя'
);
```

- `route` - строка (с регулярками/алиасами регулярок)
- `handler` - хэндлер
- `name` - имя роута для обратного роутинга (reverse routing)

### Как можно задать handler?

- `function() { }`, то есть Closure;
- `[Class::class, 'method']` - массив из двух элементов, подразумевается, что метод динамический, то есть класс будет инстанциирован перед вызовом метода.
- `Class@method` - строка, содержащая `@`. Будет применена рефлексия для вычисления типа метода. Если метод динамический - класс будет инстанциирован.
- `Class@` - будет вызван метод `__invoke()` у класса. 
- `function` - функция
- `null` - строго пустой роут, вызов **всегда** выбросит исключение `AppRouterNotFoundException -> URL not found`
- `[]`. По умолчанию будет выброшено исключение `AppRouterHandlerError`, но... есть нюанс: 

### Пустой хэндлер? 

Если задать опцию `allowEmptyHandlers: true` или вызвать `AppRouter::setOption('allowEmptyHandlers', true)`, то можно
будет использовать пустые хэндлеры, например:

```php
AppRouter::get('/admin/users/', [], 'admin.users.root');
```

В этом случае пройдет стандартная цепочка роутинга - будут инстанциированы и вызваны миддлвары, сначала before, потом в обратном порядке after, например:

```
string(30) "Class MiddleAdmin instantiated"
string(19) "MiddleAdmin::before"
string(35) "Class MiddleAdminUsers instantiated"
string(24) "MiddleAdminUsers::before"

<тут должен был обрабатываться хэндлер, но он пуст>

string(23) "MiddleAdminUsers::after"
string(18) "MiddleAdmin::after"
```

## Группировка роутов

```php
\Arris\AppRouter::group(
    prefix: '/admin',
    before: 'MiddleAdmin@before',
    after: [ MiddleAdmin::class, 'after' ],
    callback: function () {
        /* роуты группы */
    }
);


```

## Реверс-роутинг

`AppRouter::getRouter(name)` возвращает URL, соответствующий имени роута.

При этом, имя `*` вернет все маршруты. Если имя не найдено - будет возвращен роут по-умолчанию. 

При этом:

- именованные группы-плейсхолдеры будут заменены на переданные переменные
- необязательные оконечные слэши будут заменены на обязательные
- будут удалены необязательные группы

Таким образом, если роут определен:
```php
AppRouter::get('/entry/delete/{id}/', 'handler', 'callback_entry_delete');
```

То вызов 
```php
Arris\AppRouter::getRouter('callback_entry_delete', [ 'id' => 15 ])
```

Сгенерирует строчку: `/entry/delete/15/`

### Роут по-умолчанию

Если роут **не найден** или передан пустой роут - будет возвращен URL `/`. Это поведение может быть переопределено вызовом:

```php
AppRouter::setOption('getRouterDefaultValue', '/foo/bar');
```

### Вызов реверс-роутинга в шаблонах

Что полезно, реверс-роутинг может вызываться в Smarty-шаблонах:
```
<button data-url="{Arris\AppRouter::getRouter('callback_entry_delete', [ 'id' => $item.user_id ])}">Delete Entry</button>
```
Что требует определения в Smarty или Arris.Presenter:
```php
->registerClass("Arris\AppRouter", "Arris\AppRouter")
```


## Исключения (Exceptions)

Класс может выкинуть три исключения:

- `AppRouterHandlerError` - ошибка в хэндлере (пустой, неправильный, итп)
- `AppRouterNotFoundException` - роут не определен (URL ... not found)
- `AppRouterMethodNotAllowedException` - используемый метод недопустим для этого роута

При этом передается расширенная информация по роуту, получить которую можно через метод `$e->getError()`, потому что 
переопределить финальный метод `getMessage()` НЕВОЗМОЖНО. 

## Алиасы (BETA)

Включается с помощью 
```php
AppRouter::setOption('useAliases', true);
```

После этого можно задать алиасы:

```php
AppRouter::addAlias([
    [ 'userid'    =>  '\d+' ],
    [ 'username'  =>  '[a-zA-Z]+' ]
]);
```

И определить роуты:
```php
AppRouter::get(
    route: '/user/{userid}[/]', 
    handler: function ($userid = 0) { var_dump('Closure => userid: ' . $userid ); }, 
    name: 'root.userid'
);

AppRouter::get(
    route: '/user/{username}[/]', 
    handler: function ($username = 'anon') { var_dump('Closure => username: ' . $username ); },
    name: 'root.username'
);
```

Теперь при вызове `/user/<value>/` в зависимости от совпадения с регуляркой будет вызван один из хэндлеров: 

- `\d+`, то есть число - хэндлер userid
- `[a-zA-Z]+`, то есть латинская строка - хэндлер username

### Реверс-роутинг и алиасы

Прекрасно работает:
```php
echo AppRouter::getRouter('root.userid',    [ 'userid'   => 42 ]);          // => /user/42/
echo AppRouter::getRouter('root.username',  [ 'username' => 'wombat' ]);    // => /user/wombat/
```


### Опциональные группы и алиасы

В данном случае объявить опциональной можно только одну группу, хотя так делать не стоит:

```php
AppRouter::get('/user/{userid}[/]', function ($userid = 0) { var_dump('Closure => userid: ' . $userid  ); });
AppRouter::get('/user/[{username}[/]]', function ($username = 'anon') { var_dump('Closure => username: ' . $username  ); });
```

При переходе на `/user/` произойдет вызов хэндлера **username**. 

Объявление двух групп опциональными вызовет исключение: 
```
BadRouteException: Cannot register two routes matching "/user/" for method "GET"
```

### Как на самом деле сделать "опциональную" группу: 

```php
AppRouter::get('/user/', function () { var_dump('Closure => user root '  ); });
AppRouter::get('/user/{userid}[/]', function ($userid = 0) { var_dump('Closure => userid: ' . $userid  ); });
AppRouter::get('/user/{username}[/]', function ($username = 'anon') { var_dump('Closure => username: ' . $username  ); });
```

- `/user`                = 'Closure => user root'
- `/user/123/`           = 'Closure => userid: 123'
- `/username/wombat/`    = 'Closure => username: wombat'


### Использование алиасов с выключенной опцией useAliases

Вызовет исключение:
```
BadRouteException: Cannot register two routes matching "/user/([^/]+)" for method "GET""
```
Происходит это, очевидно, потому что без алиасов подгруппы `{userid}` и `{username}` раскрываются в `([^/]+)`, а роуты с
одинаковыми URL определить нельзя.


**NB:** 

В версии 2.0.* реализованы **только** глобальные алиасы. Возможности задать алиасы для роутов группы (и только для них) нет.

# Отладочные функции

```php
echo AppRouter\Helper::dumpRoutingRulesWeb( AppRouter::getRoutingRules(), false );die;
```
Даст примерно такую картинку:

<img width="1443" height="696" alt="image" src="https://github.com/user-attachments/assets/2d6f4083-9203-4c4e-8408-610cf3277713" />


------
# ToDo

- Опция `middlewareNamespace` для `init()` - неймспейс посредников по умолчанию.
