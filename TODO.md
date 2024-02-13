# Helper `router()`

```php
// роутер
// 4 аргумента: строка, строка, хэндлер, имя
router('GET', '/', function (){}, 'root');

// 3 аргумента: массив, closure, массив от нуля до двух ключей
router(['prefix'=>'/test'], function (){

    // 3-4 аргумента: строка, строка, коллбэк/хэндлер, [строка]
    router('GET', '/ajax', 'Test::ajax');

    // 2-3 аргумента: строка, коллбэк/хэндлер, [строка]
    router('GET /ajaxKeys', 'Test::ajaxKeys');

}, [
'before'    =>  handler,
'after'     =>  handler
]);
```

# Handler `::class`

Сделать обработку: если в хэндлер роута передана строчка `MyClass::class` - то должен быть вызван 

`MyClass->handler(Request $request)`

# AppRouter::group -- aliases

```php

AppRouter::group([
    'alias' =>  [
        'map_alias' => '[\w\d\.]+'
    ]
], function (){
    AppRouter::get('/map/{map_alias}', 'MapsController@view_map_fullscreen');
});
```
Подстановка регулярки делается на стадии маппинга роута. Если алиас не найден - кидаем исключение.

# Chaining methods for appRouter:

```
SimpleRouter::get('/', 'PagesController@view_page_frontpage')->name('page.frontpage');
```

Сделано как:

```php

public static function get(string $url, $callback, array $settings = null): IRoute
{
    return static::match([Request::REQUEST_TYPE_GET], $url, $callback, $settings);
}

public function name($name): ILoadableRoute
{
    return $this->setName($name);
}
```


