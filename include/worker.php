<?php

require_once __DIR__ . '/default.php';

// tasks

$app['task.cleanup_cache'] = function ($app){
	return new task\cleanup_cache($app['cache'], $app['schedule']);
};

$app['task.cleanup_image_files'] = function ($app){
	return new task\cleanup_image_files($app['cache'], $app['db'], $app['monolog'],
		$app['s3'], $app['groups'], $app['schedule']);
};

$app['task.cleanup_logs'] = function ($app){
	return new task\cleanup_logs($app['db'], $app['schedule']);
};

$app['task.get_elas_interlets_domains'] = function ($app){
	return new task\get_elas_interlets_domains($app['db'], $app['cache'],
		$app['schedule'], $app['groups']);
};

$app['task.fetch_elas_interlets'] = function ($app){
	return new task\fetch_elas_interlets($app['cache'], $app['predis'], $app['typeahead'],
		$app['monolog'], $app['schedule']);
};

// schema tasks (tasks applied to every group seperate)

$app['schema_task.cleanup_messages'] = function ($app){
	return new schema_task\cleanup_messages($app['db'], $app['monolog'],
		$app['schedule'], $app['groups'], $app['this_group'], $app['config']);
};

$app['schema_task.cleanup_news'] = function ($app){
	return new schema_task\cleanup_news($app['db'], $app['xdb'], $app['monolog'],
		$app['schedule'], $app['groups'], $app['this_group']);
};

$app['schema_task.geocode'] = function ($app){
	return new schema_task\geocode($app['db'], $app['cache'],
		$app['monolog'], $app['queue.geocode'],
		$app['schedule'], $app['groups'], $app['this_group']);
};

$app['schema_task.saldo_update'] = function ($app){
	return new schema_task\saldo_update($app['db'], $app['monolog'],
		$app['schedule'], $app['groups'], $app['this_group']);
};

$app['schema_task.user_exp_msgs'] = function ($app){
	return new schema_task\user_exp_msgs($app['db'], $app['queue.mail'],
		$app['protocol'],
		$app['schedule'], $app['groups'], $app['this_group'],
		$app['config'], $app['template_vars']);
};

$app['schema_task.saldo'] = function ($app){
	return new schema_task\saldo($app['db'], $app['xdb'], $app['predis'], $app['cache'],
		$app['monolog'], $app['queue.mail'],
		$app['s3_img_url'], $app['s3_doc_url'], $app['protocol'],
		$app['date_format'], $app['distance'],
		$app['schedule'], $app['groups'], $app['this_group'],
		$app['interlets_groups'], $app['config']);
};

$app['schema_task.interlets_fetch'] = function ($app){
	return new schema_task\interlets_fetch($app['predis'], $app['db'], $app['xdb'], $app['cache'],
		$app['typeahead'], $app['monolog'],
		$app['schedule'], $app['groups'], $app['this_group']);
};

//

$app['schedule'] = function ($app){
	return new service\schedule($app['cache'], $app['predis']);
};

// queue

$app['queue.geocode'] = function ($app){
	return new queue\geocode($app['db'], $app['cache'], $app['queue'], $app['monolog']);
};

