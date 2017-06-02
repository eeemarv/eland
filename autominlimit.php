<?php

$page_access = 'admin';
require_once __DIR__ . '/include/web.php';

if (isset($_POST['zend']))
{
	if ($error_token = $app['form_token']->get_error())
	{
		$app['alert']->error($error_token);
		cancel();
	}

	$a = array(
		'enabled'						=> (isset($_POST['enabled'])) ? true : false,
		'exclusive'						=> $_POST['exclusive'],
		'trans_percentage'				=> $_POST['trans_percentage'],
		'trans_exclusive'				=> $_POST['trans_exclusive'],
	);

	$app['xdb']->set('setting', 'autominlimit', $a);

	$app['alert']->success('De automatische minimum limiet instellingen zijn aangepast.');
	cancel();
}
else
{
	$row = $app['xdb']->get('setting', 'autominlimit');

	if ($row)
	{
		$a = $row['data'];
	}
	else
	{
		$a = [
			'enabled'					=> false,
			'exclusive'					=> '',
			'trans_percentage'			=> 100,
			'trans_exclusive'			=> '',
		];
	}
}

$h1 = 'Automatische minimum limiet';
$fa = 'arrows-v';

include __DIR__ . '/include/header.php';

echo '<div class="panel panel-info">';

echo '<div class="panel-body"><p>';
echo 'Met dit formulier kan een automatische minimum limiet ingesteld worden. ';
echo 'De individuele minimum limiet van gebruikers zal zo automatisch lager worden door ontvangen transacties ';
echo 'tot de ' . aphp('config', [], 'minimum groepslimiet') .  ' bereikt wordt. ';
echo 'De individuele minimum limiet wordt gewist wanneer de ';
echo aphp('config', ['active_tab' => 'balance'], 'minimum groepslimiet') . ' ';
echo 'bereikt of onderschreden wordt.</p>';
echo '<p>Wanneer geen minimum groepslimiet is ingesteld, dan blijft de individuele minimum limiet bij elke ';
echo 'transactie naar het account telkens dalen.</p>';

echo '<p>Individuele minimum limieten die lager zijn dan de algemene groepslimiet blijven altijd ongewijzigd.</p>';
echo '<p>Wanneer de automatische minimum limiet systematisch voor instappende leden gebruikt wordt, is het ';
echo 'nuttig de ' . aphp('config', ['active_tab' => 'balance'], 'preset individuele minimum limiet') . ' ';
echo 'in te vullen in de instellingen.</p>';

echo '</div>';
echo '<div class="panel-heading">';

echo '<form class="form-horizontal" method="post">';

echo '<div class="form-group">';
echo '<label for="enabled" class="col-sm-3 control-label">';
echo 'Zet de automatische minimum limiet aan</label>';
echo '<div class="col-sm-9">';
echo '<input type="checkbox" id="enabled" name="enabled" value="1" ';
echo ($a['enabled']) ? ' checked="checked"' : '';
echo '>';
echo '</div>';
echo '</div>';

echo '<hr>';

echo '<h3>Voor accounts</h3>';
echo '<p>De automatische minimum limiet is enkel van toepassing op actieve accounts die ';
echo 'rol van gewone gebruiker hebben (user) en die ';
echo 'niet de status uitstapper hebben. Hieronder kunnnen nog verder individuele accounts uitgesloten ';
echo 'worden.</p>';

echo '<div class="form-group">';
echo '<label for="exclusive" class="col-sm-3 control-label">';
echo 'Exclusief</label>';
echo '<div class="col-sm-9">';
echo '<input type="text" id="exclusive" name="exclusive" ';
echo 'value="' . $a['exclusive'] . '" ';
echo 'class="form-control">';
echo '<p>';
echo $app['type_template']->get_cap('codes');
echo ' gescheiden door comma\'s</p>';
echo '</div>';
echo '</div>';

echo '<hr>';

echo '<h3>Trigger voor daling van de minimum limiet.</h3>';
echo '<h4>Ontvangen transacties laten de minimum limiet dalen.</h4>';

echo '<div class="form-group">';
echo '<label for="trans_percentage" class="col-sm-3 control-label">';
echo 'Percentage van ontvangen bedrag</label>';
echo '<div class="col-sm-9">';
echo '<input type="number" id="trans_percentage" name="trans_percentage" ';
echo 'value="' . $a['trans_percentage'] . '" ';
echo 'class="form-control">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="trans_exclusive" class="col-sm-3 control-label">';
echo 'Exclusief tegenpartijen</label>';
echo '<div class="col-sm-9">';
echo '<input type="text" id="trans_exclusive" name="trans_exclusive" ';
echo 'value="' . $a['trans_exclusive'] . '" ';
echo 'class="form-control">';
echo '<p>';
echo $app['type_template']->get_cap('codes');
echo ' gescheiden door comma\'s</p>';
echo '</div>';
echo '</div>';

echo '<input type="submit" value="Aanpassen" name="zend" class="btn btn-primary">';
$app['form_token']->generate();

echo '</form>';

echo '</div>';
echo '</div>';

include __DIR__ . '/include/footer.php';

function cancel()
{
	header('Location: ' . generate_url('autominlimit'));
	exit;
}
