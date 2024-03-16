# reflection

Переделать код так, чтобы хэндлер `\Path\To\Class::method` вызывал не сразу статический класс, а сначала анализировал метод через рефлексию.

Зачем? Это полезно в рамках
https://youtrack.jetbrains.com/issue/WI-76233/Mozhno-li-sdelat-introspekciyu-koda-dlya-kastomnogo-frejmvorka-i-kak

Советуют, конечно, писать плагин https://plugins.jetbrains.com/docs/intellij/welcome.html , но это такое...

```php
class foobar {
    public function a() {
        var_dump('S-');
    }

    static public function b() {
        var_dump('S+');
    }
}

$class = 'foobar';
$method = 'b';

$reflection = new ReflectionClass( $class );
$reflected_method = $reflection->getMethod( 'a' );

if ($reflected_method->isStatic()) {
    $handler =  [ $class, $method ];
} else {
    $handler = [ new $class, $method ];
}

call_user_func_array($handler, []);
```

# getRouter()

Ситуация: 

Роутер определен как:

```php
AppRouter::get  ('/ajax/poi:get/{id}', [ AjaxController::class, 'view_poi_page'], 'ajax.view.poi.info');
```

В шаблоне пишем:
```
let url = '{Arris\AppRouter::getRouter('ajax.view.poi.info')}',
```

Тогда в url будет строчка

```
/ajax/poi:get/{id}
```

чтобы этого избежать, приходится делать роут
```php
AppRouter::get  ('/ajax/poi:get/[{id}]', [ AjaxController::class, 'view_poi_page'], 'ajax.view.poi.info');
```
То есть объявлять секцию опциональной. 

Это потенциальная проблема. 

Видимо, нужно переписать getRouter() чтобы он заменял блоки параметров `{id}` на плейсхолдеры типа `%%id%%`

которые мы в обработчике будем заменять как 
```js
url.replace('%%id%%', id);
```

Вероятно, нужен доп. параметр к `getRouter`, который будет включать (или выключать) подобное поведение.




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



