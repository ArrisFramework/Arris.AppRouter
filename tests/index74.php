<?php

use Arris\AppRouter;
use Arris\Exceptions\AppRouterHandlerError;
use Arris\Exceptions\AppRouterMethodNotAllowedException;
use Arris\Exceptions\AppRouterNotFoundException;

require_once __DIR__ . '/../vendor/autoload.php';

class StaticClass
{
    static public function present_static_method()
    {
        d(__METHOD__);
    }
}

class DynamicClass
{
    public function __construct()
    {
        d(sprintf("Class %s instantiated", __CLASS__));
    }

    public function present_dynamic_method()
    {
        d(__METHOD__);
    }

    public function __invoke()
    {
        d(__METHOD__);
    }

    public function users()
    {
        d(__METHOD__);
    }

    public function create($id = 0)
    {
        d(__METHOD__ . ' created user with id = ' . $id);
    }

    public static function stasis() {
        d(__METHOD__);
    }

    public static function all() {
        $method = explode('::', __METHOD__)[1] ?? null;

        $r = new ReflectionMethod(__CLASS__, $method);
        $s = $r->isStatic() ? 'static' : 'dynamic';
        d("===========> {$s} " . __METHOD__);
    }
}

class MiddleAdmin {
    public function __construct()
    {
        d(sprintf("Class %s instantiated", __CLASS__));
    }
    public function before()
    {
        d(__METHOD__);
    }

    public function after()
    {
        d(__METHOD__);
    }
}

class MiddleAdminUsers {
    public function __construct()
    {
        d(sprintf("Class %s instantiated", __CLASS__));
    }
    public function before()
    {
        d(__METHOD__);
    }

    public function after()
    {
        d(__METHOD__);
    }
}

function example_function() {
    var_dump(__FUNCTION__);
    echo "<br>" . PHP_EOL;
}

try {
    AppRouter::init(null, [ 'allowEmptyHandlers' => true]);

    AppRouter::get('/', [ DynamicClass::class, 'present_dynamic_method'], 'root');

    AppRouter::get('/function/', 'example_function', 'root.function_call');

    AppRouter::group(
        [
            'prefix'    =>  '/admin',
            'before'    =>  'MiddleAdmin@before',
            'after'     =>  [ MiddleAdmin::class, 'after' ]
        ], function () {
        AppRouter::get('/', function () { d('this is simple closure'); }, 'admin.root');

        AppRouter::get('/foo[/]', 'StaticClass@present_static_method', 'admin.foo');

        AppRouter::get('/list/', [StaticClass::class, 'present_static_method'], 'admin.list');

        AppRouter::group([
            'prefix'    =>  '/users',
            'before'    =>  [MiddleAdminUsers::class, 'before'],
            'after'     =>  [MiddleAdminUsers::class, 'after']
        ], static function() {
            AppRouter::get('/', [ DynamicClass::class, 'users'], 'admin.users.root');
            AppRouter::get('/all/', 'DynamicClass@all', 'admin.users.all');
            AppRouter::get('/invoke/', 'DynamicClass@' , );
            AppRouter::get('/list/', [StaticClass::class, 'method_not_exist'], 'admin.users.list');
            AppRouter::get('/empty/[{id:\d+}[/]]', /*[ DynamicClass::class, 'create']*/ [] , 'admin.users.empty');
        }
        );
    }
    );

    // dd(AppRouter::getRoutingRules());

    AppRouter::dispatch();

    $debug = [
        [
            'present dynamic method</a><br>',
            'root'
        ],
        [
            'callback function',
            'root.function_call'
        ],
        [   'Group admin'      ],
        [
            'simple closure',
            'admin.root'
        ],
        [
            'present static method, declared as string, detect with reflection',
            'admin.foo'
        ],
        [
            'static class, present method, declared as array handler, ',
            'admin.list'
        ],
        [
            'Group /admin/users/'
        ],
        [
            'declared as array, class -> method',
            'admin.users.root'
        ],
        [
            'declared as string with @, determine method type by ReflectionMethod',
            'admin.users.all'
        ],
        [
            'call __invoke() of Dynamic class',
            'admin.users.invoke'
        ],
        [
            'throws Exception, \'cause method_not_exist in class',
            'admin.users.list'
        ],
        [
            'is empty handler',
            'admin.users.empty'
        ]
    ];

    echo '<hr>';

    foreach ($debug as $row) {
        if (count($row) == 1) {
            echo sprintf("<li>{$row[0]}</li>");
        } else {
            echo sprintf('<li><a href="%1$s">%1$s</a> -- ' . $row[0] . '</li>', AppRouter::getRouter($row[1]));
        }
    }

} catch (AppRouterHandlerError|AppRouterNotFoundException|AppRouterMethodNotAllowedException $e) {
    d($e->getMessage());
    echo PHP_EOL . '<hr>' . PHP_EOL;
    d($e);
    echo "<br>" . PHP_EOL;
} catch (RuntimeException|Exception $e) {
    d($e);
    echo "<br>" . PHP_EOL;
}


