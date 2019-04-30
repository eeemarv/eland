<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use util\app;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../include/default.php';

$app['controllers']
    ->assert('id', '\d+')
    ->assert('locale', 'nl')
    ->assert('role', '[gua]')
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
    ->assert('role', '[gua]')
    ->assert('id', '\d+')
    ->assert('view', 'extended|list|map|tiles');
$c_system_user->assert('_locale', 'nl')
    ->assert('system', '[a-z][a-z0-9]*')
    ->assert('role', '[ua]')
    ->assert('id', '\d+')
    ->assert('view', 'extended|list|map|tiles');
$c_system_admin->assert('_locale', 'nl')
    ->assert('system', '[a-z][a-z0-9]*')
    ->assert('role', 'a')
    ->assert('id', '\d+')
    ->assert('view', 'extended|list|map|tiles');

$c_system->match('/login', function (Request $request, string $system) use ($app) {
    return render_legacy($app, $request, 'login', $system, 'p');
})->bind('login');

$c_system->match('/contact', function (Request $request, string $system) use ($app) {
    return render_legacy($app, $request, 'contact', $system, 'p');
})->bind('contact');

$c_system->match('/register', function (Request $request, string $system) use ($app) {
    return render_legacy($app, $request, 'register', $system, 'p');
})->bind('register');

$c_system->match('/password-reset', function (Request $request, string $system) use ($app) {
    return render_legacy($app, $request, 'pwreset', $system, 'p');
})->bind('password_reset');

$c_system_auth->get('/logout', function (Request $request, string $system, string $role) use ($app) {
    return render_legacy($app, $request, 'logout', $system, $role);
})->bind('logout');

$c_system_admin->get('/status', function (Request $request, string $system, string $role) use ($app){
    return render_legacy($app, $request, 'status', $system, $role);
})->bind('status');

$c_system_admin->get('/categories', function (Request $request, string $system, string $role) use ($app){
    return render_legacy($app, $request, 'categories', $system, $role);
})->bind('categories');

$c_system_admin->get('/contact-types', function (Request $request, string $system, string $role) use ($app){
    return render_legacy($app, $request, 'type_contact', $system, $role);
})->bind('contact_types');

$c_system_auth->get('/contacts', function (Request $request, string $system, string $role) use ($app){
    return render_legacy($app, $request, 'contacts', $system, $role);
})->bind('contacts');

$c_system_admin->get('/config', function (Request $request, string $system, string $role) use ($app){
    return render_legacy($app, $request, 'config', $system, $role);
})->bind('config');

$c_system_admin->get('/intersystem', function (Request $request, string $system, string $role) use ($app){
    return render_legacy($app, $request, 'intersystem', $system, $role);
})->bind('intersystem');

$c_system_admin->get('/apikeys', function (Request $request, string $system, string $role) use ($app){
    return render_legacy($app, $request, 'apikeys', $system, $role);
})->bind('apikeys');

$c_system_admin->get('/export', function (Request $request, string $system, string $role) use ($app){
    return render_legacy($app, $request, 'export', $system, $role);
})->bind('export');

$c_system_admin->get('/autominlimit', function (Request $request, string $system, string $role) use ($app){
    return render_legacy($app, $request, 'autominlimit', $system, $role);
})->bind('autominlimit');

$c_system_admin->get('/mass-transaction', function (Request $request, string $system, string $role) use ($app){
    return render_legacy($app, $request, 'mass_transaction', $system, $role);
})->bind('mass_transaction');

$c_system_admin->get('/logs', function (Request $request, string $system, string $role) use ($app){
    return render_legacy($app, $request, 'logs', $system, $role);
})->bind('logs');

$c_system_user->get('/support', function (Request $request, string $system, string $role) use ($app){
    return render_legacy($app, $request, 'support', $system, $role);
})->bind('support');

$c_system_auth->get('/', function (Request $request, string $system, string $role) use ($app){
    return render_legacy($app, $request, 'index', $system, $role);
})->bind('home');

$c_system_auth->get('/messages', function (Request $request, string $system, string $role) use ($app){
    return render_legacy($app, $request, 'messages', $system, $role);
})->bind('messages');

$c_system_auth->get('/users', function (Request $request, string $system, string $role) use ($app){
    return render_legacy($app, $request, 'users', $system, $role);
})->bind('users');

$c_system_auth->get('/transactions', function (Request $request, string $system, string $role) use ($app){
    return render_legacy($app, $request, 'transactions', $system, $role);
})->bind('transactions');

$c_system_auth->get('/docs', function (Request $request, string $system, string $role) use ($app){
    return render_legacy($app, $request, 'docs', $system, $role);
})->bind('docs');

$c_system_auth->get('/forum', function (Request $request, string $system, string $role) use ($app){
    return render_legacy($app, $request, 'forum', $system, $role);
})->bind('forum');

$c_system->mount('/{role}', $c_system_auth);
$c_system->mount('/{role}', $c_system_user);
$c_system->mount('/{role}', $c_system_admin);
$c_locale->mount('/{system}', $c_system);
$app->mount('/{_locale}', $c_locale);

$app->run();

function render_legacy(
    app &$app,
    Request $request,
    string $name,
    string $system,
    string $role
):Response
{
    $app['request'] = $request;
    $app['pp_system'] = $system;
    $app['pp_role'] = $role;
    ob_start();
    require_once __DIR__ . '/../' . $name . '.php';
    return new Response(ob_get_clean());
}
