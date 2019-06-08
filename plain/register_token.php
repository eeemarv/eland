<?php

if (!$app['s_anonymous'])
{
	exit;
}

if (!$app['config']->get('registration_en', $app['tschema']))
{
	$app['alert']->warning('De inschrijvingspagina is niet ingeschakeld.');
	$app['link']->redirect('login', $app['pp_ary'], []);
}

$token = $app['request']->attributes->get('token');
$data = $app['data_token']->retrieve($token, 'register', $app['tschema']);

if (!$data)
{
	$app['alert']->error('Geen geldig token.');

	require_once __DIR__ . '/../include/header.php';

	echo '<div class="panel panel-danger">';
	echo '<div class="panel-heading">';

	echo '<h2>Registratie niet gelukt</h2>';

	echo '</div>';
	echo '<div class="panel-body">';

	echo $app['link']->link('register', $app['pp_ary'],
		[], 'Opnieuw proberen', ['class' => 'btn btn-default']);

	echo '</div>';
	echo '</div>';

	require_once __DIR__ . '/../include/footer.php';
	exit;
}

$app['data_token']->del($token, 'register', $app['tschema']);

for ($i = 0; $i < 20; $i++)
{
	$name = $data['first_name'];

	if ($i)
	{
		$name .= ' ';

		if ($i < strlen($data['last_name']))
		{
			$name .= substr($data['last_name'], 0, $i);
		}
		else
		{
			$name .= substr(hash('sha512', $app['tschema'] . time() . mt_rand(0, 100000)), 0, 4);
		}
	}

	if (!$app['db']->fetchColumn('select name
		from ' . $app['tschema'] . '.users
		where name = ?', [$name]))
	{
		break;
	}
}

$minlimit = $app['config']->get('preset_minlimit', $app['tschema']);
$minlimit = $minlimit === '' ? -999999999 : $minlimit;

$maxlimit = $app['config']->get('preset_maxlimit', $app['tschema']);
$maxlimit = $maxlimit === '' ? 999999999 : $maxlimit;

$user = [
	'name'			=> $name,
	'fullname'		=> $data['first_name'] . ' ' . $data['last_name'],
	'postcode'		=> $data['postcode'],
//			'letscode'		=> '',
	'login'			=> sha1(microtime()),
	'minlimit'		=> $minlimit,
	'maxlimit'		=> $maxlimit,
	'status'		=> 5,
	'accountrole'	=> 'user',
	'cron_saldo'	=> 't',
	'lang'			=> 'nl',
	'hobbies'		=> '',
	'cdate'			=> gmdate('Y-m-d H:i:s'),
];

$app['db']->beginTransaction();

try
{
	$app['db']->insert($app['tschema'] . '.users', $user);

	$user_id = $app['db']->lastInsertId($app['tschema'] . '.users_id_seq');

	$tc = [];

	$rs = $app['db']->prepare('select abbrev, id
		from ' . $app['tschema'] . '.type_contact');

	$rs->execute();

	while($row = $rs->fetch())
	{
		$tc[$row['abbrev']] = $row['id'];
	}

	$data['email'] = strtolower($data['email']);

	$mail = [
		'id_user'			=> $user_id,
		'flag_public'		=> 0,
		'value'				=> $data['email'],
		'id_type_contact'	=> $tc['mail'],
	];

	$app['db']->insert($app['tschema'] . '.contact', $mail);

	if ($data['gsm'] || $data['tel'])
	{
		if ($data['gsm'])
		{
			$gsm = [
				'id_user'			=> $user_id,
				'flag_public'		=> 0,
				'value'				=> $data['gsm'],
				'id_type_contact'	=> $tc['gsm'],
			];

			$app['db']->insert($app['tschema'] . '.contact', $gsm);
		}

		if ($data['tel'])
		{
			$tel = [
				'id_user'			=> $user_id,
				'flag_public'		=> 0,
				'value'				=> $data['tel'],
				'id_type_contact'	=> $tc['tel'],
			];
			$app['db']->insert($app['tschema'] . '.contact', $tel);
		}
	}
	$app['db']->commit();
}
catch (Exception $e)
{
	$app['db']->rollback();
	throw $e;
}

$vars = [
	'user_id'		=> $user_id,
	'postcode'		=> $user['postcode'],
	'email'			=> $data['email'],
];

$app['queue.mail']->queue([
	'schema'		=> $app['tschema'],
	'to' 			=> $app['mail_addr_system']->get_admin($app['tschema']),
	'vars'			=> $vars,
	'template'		=> 'register/admin',
], 8000);

$map_template_vars = [
	'voornaam' 			=> 'first_name',
	'achternaam'		=> 'last_name',
	'postcode'			=> 'postcode',
];

foreach ($map_template_vars as $k => $v)
{
	$vars[$k] = $data[$v];
}

$vars['subject'] = $app['translator']->trans('register_success.subject', [
	'%system_name%'	=> $app['config']->get('systemname', $app['tschema']),
], 'mail');

$app['queue.mail']->queue([
	'schema'				=> $app['tschema'],
	'to' 					=> [$data['email'] => $user['fullname']],
	'reply_to'				=> $app['mail_addr_system']->get_admin($app['tschema']),
	'pre_html_template'		=> $app['config']->get('registration_success_mail', $app['tschema']),
	'template'				=> 'skeleton',
	'vars'					=> $vars,
], 8500);

$app['alert']->success('Inschrijving voltooid.');

require_once __DIR__ . '/../include/header.php';

$registration_success_text = $app['config']->get('registration_success_text', $app['tschema']);

if ($registration_success_text)
{
	echo $registration_success_text;
}

require_once __DIR__ . '/../include/footer.php';
