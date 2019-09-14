<?php declare(strict_types=1);

if (!($app['pp_admin'] || $app['pp_user']))
{
	exit;
}

if ($app['s_master'])
{
	$user_email_ary = [];
}
else
{
	$user_email_ary = $app['mail_addr_user']->get_active($app['s_id'], $app['pp_schema']);
}

$can_reply = count($user_email_ary) ? true : false;

if (isset($_POST['zend']))
{
	$cc = isset($_POST['cc']);
	$message = $_POST['message'] ?? '';
	$message = trim($message);

	if(empty($message) || strip_tags($message) == '' || $message === false)
	{
		$errors[] = 'Het bericht is leeg.';
	}

	if (!trim($app['config']->get('support', $app['pp_schema'])))
	{
		$errors[] = 'Het Support E-mail adres is niet ingesteld op dit Systeem';
	}

	if ($app['s_master'])
	{
		$errors[] = 'Het master account kan geen E-mail berichten versturen.';
	}

	if ($token_error = $app['form_token']->get_error())
	{
		$errors[] = $token_error;
	}

	if(!count($errors))
	{
		$vars = [
			'user_id'	=> $app['s_id'],
			'can_reply'	=> $can_reply,
			'message'	=> $message,
		];

		if ($cc && $can_reply)
		{
			$app['queue.mail']->queue([
				'schema'	=> $app['pp_schema'],
				'template'	=> 'support/copy',
				'vars'		=> $vars,
				'to'		=> $user_email_ary,
			], 8500);
		}

		$app['queue.mail']->queue([
			'schema'	=> $app['pp_schema'],
			'template'	=> 'support/support',
			'vars'		=> $vars,
			'to'		=> $app['mail_addr_system']->get_support($app['pp_schema']),
			'reply_to'	=> $user_email_ary,
		], 8000);

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

	if ($app['s_master'])
	{
		$app['alert']->warning('Het master account kan geen E-mail berichten versturen.');
	}
	else
	{
		if (!$can_reply)
		{
			$app['alert']->warning('Je hebt geen E-mail adres ingesteld voor je account. ');
		}
	}

	$cc = true;
}

if (!$can_reply)
{
	$cc = false;
}

if (!$app['config']->get('mailenabled', $app['pp_schema']))
{
	$app['alert']->warning('De E-mail functies zijn uitgeschakeld door de beheerder. Je kan dit formulier niet gebruiken');
}
else if (!$app['config']->get('support', $app['pp_schema']))
{
	$app['alert']->warning('Er is geen Support E-mail adres ingesteld door de beheerder. Je kan dit formulier niet gebruiken.');
}

$app['heading']->add('Help / Probleem melden');
$app['heading']->fa('ambulance');

require_once __DIR__ . '/../include/header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post">';

echo '<div class="form-group">';
echo '<label for="message">Je Bericht</label>';
echo '<textarea name="message" ';
echo 'class="form-control" id="message" rows="4"';
echo $app['s_master'] ? ' disabled' : '';
echo '>';
echo $message;
echo '</textarea>';
echo '</div>';

echo '<div class="form-group';
echo $can_reply ? '' : ' checkbox disabled has-warning';
echo '">';
echo '<label for="cc" class="control-label">';
echo '<input type="checkbox" name="cc" ';
echo $can_reply ? '' : 'disabled ';
echo 'id="cc" value="1"';
echo $cc ? ' checked="checked"' : '';
echo '> ';

if ($can_reply)
{
	echo 'Stuur een kopie naar mijzelf.';
}
else
{
	echo 'Een kopie van je bericht naar ';
	echo 'jezelf sturen is ';
	echo 'niet mogelijk want er is ';
	echo 'geen E-mail adres ingesteld voor ';
	echo 'je account.';
}

echo '</label>';
echo '</div>';

echo '<input type="submit" name="zend" value="Verzenden" class="btn btn-default">';
echo $app['form_token']->get_hidden_input();

echo '</form>';

echo '</div>';
echo '</div>';

include __DIR__ . '/../include/footer.php';
