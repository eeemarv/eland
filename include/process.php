<?php

require_once __DIR__ . '/app.php';

$app->boot();

$app['request_context']->setHost(getenv('APP_HOST'));
$app['request_context']->setScheme(getenv('APP_SCHEME'));

$app['monitor_process']->boot();
