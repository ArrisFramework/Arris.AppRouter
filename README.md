# About

Попытка написать свой роутер на базе https://github.com/nikic/FastRoute

Реализует возможность статического класса AppRouter с методами:

- `init()`
- `get()`
- `post()`
- etc

Выделено в отдельный пакет для возможности обновления отдельно от основного класса фреймворка и отдельного подключения. 


# Инициализация

```php
AppRouter::init(AppLogger::scope('routing'), [
// опции
]);
```

Опции:

- `defaultNamespace` - неймспейс по-умолчанию
- `namespace` - алиас `defaultNamespace`
- `prefix` - текущий префикс URL (аналогично поведению для групп)
- `routeReplacePattern` - ? 
- `allowEmptyHandlers` (true) - разрешить пустые (заданные как `[]`) хэндлеры? Если false - кидается исключение `AppRouterHandlerError` "Handler not found or empty".
- `allowEmptyGroups` (false) - разрешить ли пустые группы? Пустой считается группа без роутов. Если разрешено - для такой группы будут парситься миддлвары и опции.
- 

```php

AppRouter::get('/', function () {
    CLIConsole::say('Call from /');
}, 'root');

AppRouter::group([
    'prefix' => '/auth', 
    'namespace' => 'Auth', 
    'before' => static function () { CLIConsole::say('Called BEFORE middleware for /auth/*'); }, 
    'after' => null
    ], static function() {

    AppRouter::get('/login', function () {
        CLIConsole::say('Call /auth/login');
    });

    AppRouter::group(['prefix' => '/ajax'], static function() {

        AppRouter::get('/getKey', function (){
            CLIConsole::say('Call from /test/ajax/getKey');
        }, 'auth:ajax:getKey');

    });

    AppRouter::get('/get', function (){
        CLIConsole::say('Call from /test/get (declared after /ajax prefix group');
    });

    AppRouter::group(['prefix' => '/2'], static function() {
        AppRouter::get('/3', function () {
            CLIConsole::say('Call from /test/2/3');
        });
    });

});

AppRouter::get('/root', function (){
    CLIConsole::say('Call from /root (declared after /ajax prefix group ; after /test prefix group)');
});

AppRouter::group([], function (){
    AppRouter::get('/not_group', function () {

    });
});
```


# ToDo

- Опция `middlewareNamespace` для `init()` - неймспейс посредников по умолчанию.
