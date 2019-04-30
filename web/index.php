<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use util\app;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../include/default.php';

$app['controllers']
    ->assert('id', '\d+')
    ->assert('locale', 'nl')
    ->assert('access', '[gua]')
    ->assert('system', '[a-z][a-z0-9]*');

$c_locale = $app['controllers_factory'];
$c_system = $app['controllers_factory'];
$c_system_auth = $app['controllers_factory'];
$c_system_user = $app['controllers_factory'];
$c_system_admin = $app['controllers_factory'];

$c_locale->assert('_locale', 'nl');
$c_system->assert('_locale', 'nl')
    ->assert('system', '[a-z][a-z0-9]*');
$c_system_auth->assert('_locale', 'nl')
    ->assert('system', '[a-z][a-z0-9]*')
    ->assert('access', '[gua]')
    ->assert('id', '\d+')
    ->assert('view', 'extended|list|map|tiles');
$c_system_user->assert('_locale', 'nl')
    ->assert('system', '[a-z][a-z0-9]*')
    ->assert('access', '[ua]')
    ->assert('id', '\d+')
    ->assert('view', 'extended|list|map|tiles');
$c_system_admin->assert('_locale', 'nl')
    ->assert('system', '[a-z][a-z0-9]*')
    ->assert('access', 'a')
    ->assert('id', '\d+')
    ->assert('view', 'extended|list|map|tiles');

$c_system->match('/login', function (string $system) use ($app) {
    return render_legacy($app, 'login', $system, 'p');
})->bind('login');

$c_system->match('/contact', function (string $system) use ($app) {
    return render_legacy($app, 'contact', $system, 'p');
})->bind('contact');

$c_system->match('/register', function (string $system) use ($app) {
    return render_legacy($app, 'register', $system, 'p');
})->bind('register');

$c_system->match('/password-reset', function (string $system) use ($app) {
    return render_legacy($app, 'pwreset', $system, 'p');
})->bind('password_reset');

$c_system_auth->get('/logout', function (string $system, string $access) use ($app) {
    return render_legacy($app, 'logout', $system, $access);
})->bind('logout');

$c_system_admin->get('/status', function (string $system, string $access) use ($app){
    return render_legacy($app, 'status', $system, $access);
})->bind('status');

$c_system->mount('/{access}', $c_system_auth);
$c_system->mount('/{access}', $c_system_user);
$c_system->mount('/{access}', $c_system_admin);
$c_locale->mount('/{system}', $c_system);
$app->mount('/{_locale}', $c_locale);

$app->run();

function render_legacy(
    app &$app,
    string $name,
    string $system,
    string $access
):Response
{
    $app['p_system'] = $system;
    $app['p_access'] = $access;
    ob_start();
    require_once __DIR__ . '/../' . $name . '.php';
    return new Response(ob_get_clean());
}
