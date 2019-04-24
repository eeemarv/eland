<?php

if (php_sapi_name() !== 'cli')
{
	echo '-- cli only --';
	exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::create(__DIR__ . '/..', '.env_m');
$dotenv->load();

$rootpath = '../';

require_once __DIR__ . '/../include/default.php';

error_log('TEST notify expired messages, schema x');

$app['systems']->get_schemas();
$app['schema_task.user_exp_msgs']->set_schema('x');
$app['schema_task.user_exp_msgs']->process(false);
echo 'Ok.';
