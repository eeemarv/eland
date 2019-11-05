<?php declare(strict_types=1);

$server_name = $_SERVER['SERVER_NAME'];

$parts = explode('.', $server_name);

if (count($parts) === 3)
{
    $base_url = 'https://' . $parts[1] . '.' . $parts[2] . '/' . $parts[0];

    header('Location: ' . $base_url . '/login');
    exit;
}

header('Location: https://' . $parts[0] . '.' . $parts[1]);
exit;