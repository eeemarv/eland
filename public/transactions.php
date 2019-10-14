<?php declare(strict_types=1);

require_once __DIR__ . '/../include/web_legacy.php';

if ($add)
{
    header('Location: ' . $app->url('transactions_add', [
        'system'		=> $system,
        'role_short'    => $role_short,
    ]));
    exit;
}

if ($id)
{
    header('Location: ' . $app->url('transactions_show', [
        'system'		=> $system,
        'role_short'    => $role_short,
        'id'            => $id,
    ]));
    exit;
}

header('Location: ' . $app->url('transactions', [
    'system'		=> $system,
    'role_short'    => $role_short,
]));
exit;
