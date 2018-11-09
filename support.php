<?php

$page_access = 'user';

require_once __DIR__ . '/include/web.php';

if (isset($_POST['zend']))
{
	$message = $_POST['message'] ?? '';
	$message = trim($message);

	if(empty($message) || strip_tags($message) == '' || $message === false)
	{
		$errors[] = 'Het bericht is leeg.';
	}

	if (!trim($app['config']->get('support')))
	{
		$errors[] = 'Het support email adres is niet ingesteld op deze installatie';
	}

	if ($s_master)
	{
		$errors[] = 'Het master account kan geen email berichten versturen.';
	}

	if ($token_error = $app['form_token']->get_error())
	{
		$errors[] = $token_error;
	}

	if(!count($errors))
	{
		$contacts = $app['db']->fetchAll('select c.value, tc.abbrev
			from contact c, type_contact tc
			where c.id_user = ?
				and c.id_type_contact = tc.id', [$s_id]);

		$email = $app['mailaddr']->get($s_id);

		$vars = [
			'group'	=> [
				'name'		=> $app['config']->get('systemname'),
				'tag'		=> $app['config']->get('systemtag'),
			],
			'user'	=> [
				'text'			=> link_user($s_id, false, false),
				'url'			=> $app['base_url'] . '/users.php?id=' . $s_id,
				'email'			=> $email,
			],
			'contacts'		=> $contacts,
			'message'		=> $message,
			'config_url'	=> $app['base_url'] . '/config.php?active_tab=mailaddresses',
		];

		$email_ary = [
			'to'		=> 'support',
			'template'	=> 'support',
			'vars'		=> $vars,
		];

		if ($email)
		{
			$app['queue.mail']->queue([
				'template'	=> 'support_copy',
				'vars'		=> $vars,
				'to'		=> $s_id,
			]);

			$email_ary['reply_to'] = $s_id;
		}

		$return_message =  $app['queue.mail']->queue($email_ary);

		if (!$return_message)
		{
			$app['alert']->success('De support email is verzonden.');
			redirect_default_page();
		}

		$app['alert']->error('Email niet verstuurd. ' . $return_message);
	}
	else
	{
		$app['alert']->error($errors);
	}
}
else
{
	$message = '';

	if ($s_master)
	{
		$app['alert']->warning('Het master account kan geen email berichten versturen.');
	}
	else
	{
		$email = $app['mailaddr']->get($s_id);

		if (!count($email))
		{
			$app['alert']->warning('Je hebt geen email adres ingesteld voor je account. ');
		}
	}
}

if (!$app['config']->get('mailenabled'))
{
	$app['alert']->warning('De email functies zijn uitgeschakeld door de beheerder. Je kan dit formulier niet gebruiken');
}
else if (!$app['config']->get('support'))
{
	$app['alert']->warning('Er is geen support email adres ingesteld door de beheerder. Je kan dit formulier niet gebruiken.');
}

$h1 = 'Help / Probleem melden';
$fa = 'ambulance';

require_once __DIR__ . '/include/header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post">';

echo '<div class="form-group">';
echo '<label for="message">Je Bericht</label>';
echo '<textarea name="message" class="form-control" id="message" rows="4">';
echo $message;
echo '</textarea>';
echo '</div>';

echo '<input type="submit" name="zend" value="Verzenden" class="btn btn-default">';
echo $app['form_token']->get_input();

echo '</form>';

echo '</div>';
echo '</div>';

include __DIR__ . '/include/footer.php';
