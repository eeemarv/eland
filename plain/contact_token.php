<?php

if (!$app['s_anonymous'])
{
	exit;
}

if (!$app['config']->get('contact_form_en', $app['tschema']))
{
	$app['alert']->warning('De contactpagina is niet ingeschakeld.');
	$app['link']->redirect('login', $app['pp_ary'], []);
}

$token = $app['request']->attributes->get('token');
$data = $app['data_token']->retrieve($token, 'contact', $app['tschema']);

if (!$data)
{
	$app['alert']->error('Ongeldig of verlopen token.');
	$app['link']->redirect('contact', $app['pp_ary'], []);
}

$vars = [
	'message'		=> $data['message'],
	'ip'			=> $data['ip'],
	'agent'			=> $data['agent'],
	'email'			=> $data['email'],
	'pp_ary'		=> $app['pp_ary'],
];

$app['queue.mail']->queue([
	'schema'	=> $app['tschema'],
	'template'	=> 'contact/copy',
	'vars'		=> $vars,
	'to'		=> [$data['email'] => $data['email']],
], 9000);

$app['queue.mail']->queue([
	'schema'	=> $app['tschema'],
	'template'	=> 'contact/support',
	'vars'		=> $vars,
	'to'		=> $app['mail_addr_system']->get_support($app['tschema']),
	'reply_to'	=> [$data['email']],
], 8000);

$app['data_token']->del($token, 'contact', $app['tschema']);

$app['alert']->success('Je bericht werd succesvol verzonden.');
$app['link']->redirect('contact', $app['pp_ary'], []);
