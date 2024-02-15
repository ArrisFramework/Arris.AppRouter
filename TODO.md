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

# Handler `::class` -- сделано, но требуются тесты!

Сделать обработку: если в хэндлер роута передана строчка `MyClass::class` - то должен быть вызван 

`MyClass->handler(Request $request)`

поправка: будет вызван `__invoke()`

- задавать это в конфигурации?



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
Подстановка регулярки делается на стадии маппинга роута. Если алиас не найден - кидаем исключение (какое?)

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

# Embed FastRouter code to class... 

# Есть проблема с method_exists()

```
Undocumented change since 7.4.0 is released:

<?php
class Foo 
{ 
    private function privateMethodTest() 
    {
        
    } 
} 

class Bar extends Foo 
{
    
} 

var_dump(method_exists(Bar::class, 'privateMethodTest'));
// PHP 7.4: bool(false)
// PHP 7.3: bool(true)

var_dump(is_callable(Bar::class, 'privateMethodTest'));
// PHP 7.3: bool(true)
// PHP 7.4: bool(true)
```

Вероятно, нужно проверять через рефлексии, isMethodPublic() ? 



