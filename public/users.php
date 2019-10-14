<?php declare(strict_types=1);

require_once __DIR__ . '/../include/web_legacy.php';

if ($edit)
{
    header('Location: ' . $app->url('users_edit', [
        'system'		=> $system,
        'role_short'    => $role_short,
        'id'            => $edit,
    ]));
    exit;
}

if ($add)
{
    header('Location: ' . $app->url('users_add', [
        'system'		=> $system,
        'role_short'    => $role_short,
        'id'            => $add,
    ]));
    exit;
}

if ($id)
{
    header('Location: ' . $app->url('users_show', [
        'system'		=> $system,
        'role_short'    => $role_short,
        'id'            => $id,
    ]));
    exit;
}

header('Location: ' . $app->url('users_list', [
    'system'		=> $system,
    'role_short'    => $role_short,
]));
exit;
