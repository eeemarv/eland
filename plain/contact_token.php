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

$key = $app['tschema'] . '_contact_' . $token;
$data = $app['predis']->get($key);

if ($data)
{
	$app['predis']->del($key);

	$data = json_decode($data, true);

	$vars = [
		'message'		=> $data['message'],
		'ip'			=> $data['ip'],
		'agent'			=> $data['agent'],
		'email'			=> $data['email'],
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

	$app['alert']->success('Je bericht werd succesvol verzonden.');
	$app['link']->redirect('contact', $app['pp_ary'], []);
}

$app['alert']->error('Ongeldig of verlopen token.');
$app['link']->redirect('contact', $app['pp_ary'], []);
