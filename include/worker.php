<?php

require_once __DIR__ . '/default.php';

$app['eland.protocol'] = 'http://';

// tasks

$app['eland.task.cleanup_cache'] = function ($app){
	return new eland\task\cleanup_cache($app['eland.cache'], $app['eland.schedule']);
};

$app['eland.task.cleanup_image_files'] = function ($app){
	return new eland\task\cleanup_image_files($app['eland.cache'], $app['db'], $app['monolog'],
		$app['eland.s3'], $app['eland.groups'], $app['eland.schedule']);
};

$app['eland.task.cleanup_logs'] = function ($app){
	return new eland\task\cleanup_logs($app['db'], $app['eland.schedule']);
};

$app['eland.task.get_elas_interlets_domains'] = function ($app){
	return new eland\task\get_elas_interlets_domains($app['db'], $app['eland.cache'],
		$app['eland.schedule'], $app['eland.groups']);
};

$app['eland.task.fetch_elas_interlets'] = function ($app){
	return new eland\task\fetch_elas_interlets($app['eland.cache'], $app['redis'], $app['eland.typeahead'],
		$app['monolog'], $app['eland.schedule']);
};

// schema tasks (tasks applied to every group seperate)

$app['eland.schema_task.cleanup_messages'] = function ($app){
	return new eland\schema_task\cleanup_messages($app['db'], $app['monolog'],
		$app['eland.schedule'], $app['eland.groups'], $app['eland.this_group']);
};

$app['eland.schema_task.cleanup_news'] = function ($app){
	return new eland\schema_task\cleanup_news($app['db'], $app['eland.xdb'], $app['monolog'],
		$app['eland.schedule'], $app['eland.groups'], $app['eland.this_group']);
};

$app['eland.schema_task.geocode'] = function ($app){
	return new eland\schema_task\geocode($app['db'], $app['eland.cache'],
		$app['monolog'], $app['eland.queue.geocode'],
		$app['eland.schedule'], $app['eland.groups'], $app['eland.this_group']);
};

$app['eland.schema_task.saldo_update'] = function ($app){
	return new eland\schema_task\saldo_update($app['db'], $app['monolog'],
		$app['eland.schedule'], $app['eland.groups'], $app['eland.this_group']);
};

$app['eland.schema_task.user_exp_msgs'] = function ($app){
	return new eland\schema_task\user_exp_msgs($app['db'], $app['eland.queue.mail'],
		$app['eland.protocol'],
		$app['eland.schedule'], $app['eland.groups'], $app['eland.this_group']);
};

$app['eland.schema_task.saldo'] = function ($app){
	return new eland\schema_task\saldo($app['db'], $app['eland.xdb'], $app['eland.cache'],
		$app['monolog'], $app['eland.queue.mail'],
		$app['eland.s3_img_url'], $app['eland.s3_doc_url'], $app['eland.protocol'],
		$app['eland.date_format'],
		$app['eland.schedule'], $app['eland.groups'], $app['eland.this_group']);
};

$app['eland.schema_task.interlets_fetch'] = function ($app){
	return new eland\schema_task\interlets_fetch($app['redis'], $app['db'], $app['eland.xdb'], $app['eland.cache'],
		$app['eland.typeahead'], $app['monolog'],
		$app['eland.schedule'], $app['eland.groups'], $app['eland.this_group']);
};

//

$app['eland.task'] = function ($app){
	return new eland\task($app['db'], $app['monolog'], $app['eland.schedule'],
		$app['eland.groups'], $app['eland.this_group']);
};

$app['eland.schema_task'] = function ($app){
	return new eland\task($app['eland.schedule'], $app['new_schema_task'],
		$app['eland.groups'], $app['eland.this_group']);
};

$app['eland.new_schema_task'] = function ($app){
	return new eland\task($app['db'], $app['monolog'], $app['eland.schedule']);
};

$app['eland.schedule'] = function ($app){
	return new eland\schedule($app['eland.cache']);
};

// queue

$app['eland.queue.geocode'] = function ($app){
	return new \eland\queue\geocode($app['db'], $app['eland.cache'], $app['eland.queue'], $app['monolog']);
};

