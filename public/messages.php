<?php declare(strict_types=1);

require_once __DIR__ . '/../include/web_legacy.php';

$extend = $_GET['extend'] ?? false;

if ($extend && $id)
{
    header('Location: ' . $app->url('messages_extend', [
        'system'		=> $system,
        'role_short'    => $role_short,
        'days'          => $extend,
        'id'            => $id,
    ]));
    exit;
}

if ($edit)
{
    header('Location: ' . $app->url('messages_edit', [
        'system'		=> $system,
        'role_short'    => $role_short,
        'id'            => $edit,
    ]));
    exit;
}

if ($add)
{
    header('Location: ' . $app->url('messages_add', [
        'system'		=> $system,
        'role_short'    => $role_short,
        'id'            => $add,
    ]));
    exit;
}

if ($id)
{
    header('Location: ' . $app->url('messages_show', [
        'system'		=> $system,
        'role_short'    => $role_short,
        'id'            => $id,
    ]));
    exit;
}

header('Location: ' . $app->url('messages_extended', [
    'system'		=> $system,
    'role_short'    => $role_short,
]));
exit;
