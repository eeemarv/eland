#!/usr/bin/php

<?php

set_time_limit(300);

$app = require_once __DIR__ . '/app.php';

$console = $app['console'];
$console->add(new \command\user_create());
$console->add(new \command\user_change_password());
$console->run();
