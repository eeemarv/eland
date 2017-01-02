<?php

require_once __DIR__ . '/default.php';

$app['eland.protocol'] = 'http://';

// tasks

$app['eland.task.cleanup_cache'] = function ($app){
	return new eland\task\cleanup_cache($app['eland.cache']);
};

$app['eland.task.geocode'] = function ($app){
	return new eland\task\geocode($app['db'], $app['eland.cache'],
		$app['monolog'], $app['eland.queue.geocode']);
};

$app['eland.task.cleanup_image_files'] = function ($app){
	return new eland\task\cleanup_image_files($app['eland.cache'], $app['db'], $app['monolog'],
		$app['eland.s3'], $app['eland.groups']);
};

$app['eland.task.cleanup_messages'] = function ($app){
	return new eland\task\cleanup_messages($app['db'], $app['monolog']);
};

$app['eland.task.cleanup_news'] = function ($app){
	return new eland\task\cleanup_news($app['db'], $app['eland.xdb'], $app['monolog']);
};

$app['eland.task.cleanup_logs'] = function ($app){
	return new eland\task\cleanup_logs($app['db'], $app['eland.xdb']);
};

$app['eland.task.saldo_update'] = function ($app){
	return new eland\task\saldo_update($app['db'], $app['monolog']);
};

$app['eland.task.user_exp_msgs'] = function ($app){
	return new eland\task\user_exp_msgs($app['db'], $app['eland.queue.mail'],
		$app['eland.groups'], $app['eland.protocol']);
};

$app['eland.task.saldo'] = function ($app){
	return new eland\task\saldo($app['db'], $app['eland.xdb'], $app['monolog'], $app['eland.queue.mail'],
		$app['eland.groups'], $app['eland.s3_img_url'], $app['eland.s3_doc_url'], $app['eland.protocol'],
		$app['eland.date_format']);
};

$app['eland.task.interlets_fetch'] = function ($app){
	return new eland\task\interlets_fetch($app['redis'], $app['db'], $app['eland.xdb'], $app['eland.cache'],
		$app['eland.typeahead'], $app['monolog'], $app['eland.groups']);
};

$app['eland.task_schedule'] = function ($app){
	return new eland\task_schedule($app['db'], $app['monolog'], $app['eland.cache'],
		$app['eland.groups'], $app['eland.this_group']);
};

// queue

$app['eland.queue.geocode'] = function ($app){
	return new eland\queue\geocode($app['db'], $app['eland.cache'], $app['eland.queue'], $app['monolog']);
};

// init

$app['eland.elas_db_upgrade'] = function ($app){
	return new eland\elas_db_upgrade($app['db']);
};


