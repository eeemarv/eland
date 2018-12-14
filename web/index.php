<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../include/default.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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

$c_system_user->assert('access', 'u');
$c_system_admin->assert('access', '[ua]');

$c_system->match('/login', function (string $system) use ($app) {
    echo $system;
    $page_access = 'anonymous';
    require_once __DIR__ . '/../include/web.php';
    require_once __DIR__ . '/login.php';
    return new Response(ob_get_clean());
});

$c_system_auth->get('/logout', function () {
    return 'Blog home page';
});

$c_system_admin->get('/status', function (){
    return ;
});

$c_system->mount('/{access}', $c_system_auth);
$c_system->mount('/{access}', $c_system_user);
$c_system->mount('/{access}', $c_system_admin);
$c_locale->mount('/{system}', $c_system);
$app->mount('/{_locale}', $c_locale);

$app->run();
