<?php

require __DIR__ . '/../../../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Mockery as m;

date_default_timezone_set('UTC');

$container = new Container;
$container->instance('Illuminate\Contracts\Http\Kernel', m::mock('Illuminate\Contracts\Http\Kernel')->shouldReceive('hasMiddleware')->andReturn(false)->getMock());
Container::setInstance($container);

$capsule = new Capsule;
$capsule->addConnection([
  'driver'   => 'sqlite',
  'database' => ':memory:',
]);
$capsule->setEventDispatcher(new Dispatcher);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$capsule->schema()->dropIfExists('models');

$capsule->schema()->create('models', function (Blueprint $table) {
    $table->increments('id');
    $table->string('string');
    $table->string('email');
    $table->timestamps();
});

if (!function_exists('app')) {
    function app($abstract = null) {
        return Container::getInstance()->make($abstract);
    }
}
