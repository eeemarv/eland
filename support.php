<?php

$page_access = 'user';

require_once __DIR__ . '/include/web.php';

$tschema = $app['this_group']->get_schema();

if (isset($_POST['zend']))
{
	$message = $_POST['message'] ?? '';
	$message = trim($message);

	if(empty($message) || strip_tags($message) == '' || $message === false)
	{
		$errors[] = 'Het bericht is leeg.';
	}

	if (!trim($app['config']->get('support', $tschema)))
	{
		$errors[] = 'Het Support E-mail adres is niet ingesteld op dit Systeem';
	}

	if ($s_master)
	{
		$errors[] = 'Het master account kan geen E-mail berichten versturen.';
	}

	if ($token_error = $app['form_token']->get_error())
	{
		$errors[] = $token_error;
	}

	if(!count($errors))
	{
		$contacts = $app['db']->fetchAll('select c.value, tc.abbrev
			from ' . $tschema . '.contact c, ' . $tschema . '.type_contact tc
			where c.id_user = ?
				and c.id_type_contact = tc.id', [$s_id]);

		$email = $app['mail_addr_user']->get($s_id, $tschema);

		$vars = [
			'group'	=> [
				'name'		=> $app['config']->get('systemname', $tschema),
				'tag'		=> $app['config']->get('systemtag', $tschema),
			],
			'user'	=> [
				'text'			=> link_user($s_id, $tschema, false),
				'url'			=> $app['base_url'] . '/users.php?id=' . $s_id,
				'email'			=> $email,
			],
			'contacts'		=> $contacts,
			'message'		=> $message,
			'config_url'	=> $app['base_url'] . '/config.php?active_tab=mailaddresses',
		];

		$email_ary = [
			'schema'	=> $tschema,
			'to'		=> $app['mail_addr_system']->get_support($tschema),
			'template'	=> 'support',
			'vars'		=> $vars,
		];

		if ($email)
		{
			$app['queue.mail']->queue([
				'schema'	=> $tschema,
				'template'	=> 'support_copy',
				'vars'		=> $vars,
				'to'		=> $app['mail_addr_user']->get($s_id, $tschema),
			]);

			$email_ary['reply_to'] = $app['mail_addr_user']->get($s_id, $tschema);
		}

		$app['queue.mail']->queue($email_ary);

		$app['alert']->success('De Support E-mail is verzonden.');
		redirect_default_page();
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
		$app['alert']->warning('Het master account kan geen E-mail berichten versturen.');
	}
	else
	{
		$email = $app['mail_addr_user']->get($s_id, $tschema);

		if (!count($email))
		{
			$app['alert']->warning('Je hebt geen E-mail adres ingesteld voor je account. ');
		}
	}
}

if (!$app['config']->get('mailenabled', $tschema))
{
	$app['alert']->warning('De E-mail functies zijn uitgeschakeld door de beheerder. Je kan dit formulier niet gebruiken');
}
else if (!$app['config']->get('support', $tschema))
{
	$app['alert']->warning('Er is geen Support E-mail adres ingesteld door de beheerder. Je kan dit formulier niet gebruiken.');
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
echo $app['form_token']->get_hidden_input();

echo '</form>';

echo '</div>';
echo '</div>';

include __DIR__ . '/include/footer.php';
