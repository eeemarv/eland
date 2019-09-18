<?php declare(strict_types=1);

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

require_once __DIR__ . '/app.php';

$app->flush();

$server_name = $_SERVER['SERVER_NAME'];

$dot_count = substr_count($server_name, '.');

if ($dot_count !== 2)
{
	NotFoundHttpException('Deze pagina bestaat niet.');
}

[$system] = explode('.', $server_name);

$schema = $app['systems']->get_schema($system);

if (!$schema)
{
	throw new NotFoundHttpException('Dit systeem bestaat niet (' . $system . ')');
}

if (getenv('APP_PORT'))
{
    $app['request_context']->setHttpPort(getenv('APP_PORT'));
}

if (getenv('APP_HOST'))
{
    $app['request_context']->setHost(getenv('APP_HOST'));
}

if (getenv('APP_SCHEME'))
{
    $app['request_context']->setScheme(getenv('APP_SCHEME'));
}

$role_short = isset($_GET['r']) && $_GET['r'] === 'admin' ? 'a' : 'u';

$id = $_GET['id'] ?? false;
$del = $_GET['del'] ?? false;
$edit = $_GET['edit'] ?? false;
$add = isset($_GET['add']);
