<?php declare(strict_types=1);

use App\Kernel;

/** Redirect old subdomain routes (nov 2019) */

$server_name = $_SERVER['SERVER_NAME'];

$parts = explode('.', $server_name);

if (count($parts) === 3)
{
    header('Location: https://' . $parts[1] . '.' . $parts[2] . '/' . $parts[0] . '/login');
    exit;
}

/** End redirect */

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
