<?php declare(strict_types=1);

if (substr_count($_SERVER['SERVER_NAME'], '.') === 2)
{
    require_once __DIR__ . '/../include/web_legacy.php';

    header('Location: ' . $app->url('home_system', [
        'system'		=> $system,
    ]));
    exit;
}

require_once __DIR__ . '/../include/app.php';

$app->run();
