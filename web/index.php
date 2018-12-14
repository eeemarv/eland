<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../include/default.php';

$c_locale = $app['controllers_factory'];
$c_system = $app['controllers_factory'];

$c_system->get('/', function () {
    return 'Blog home page';
});

$c_locale->mount('/index', $c_system);
$app->mount('/{_locale}', $c_locale);

$app->run();
