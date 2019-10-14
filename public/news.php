<?php declare(strict_types=1);

require_once __DIR__ . '/../include/web_legacy.php';

if ($id)
{
    header('Location: ' . $app->url('news_show', [
        'system'		=> $system,
        'role_short'    => $role_short,
        'id'            => $id,
    ]));
    exit;
}

header('Location: ' . $app->url('news_extended', [
    'system'		=> $system,
    'role_short'    => $role_short,
]));
exit;
