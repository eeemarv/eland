<?php declare(strict_types=1);

require_once __DIR__ . '/../include/web_legacy.php';

header('Location: ' . $app->url('register', [
    'system'		=> $system,
]));
exit;
