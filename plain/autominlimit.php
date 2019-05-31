<?php

if (!$app['s_admin'])
{
	exit;
}

if (isset($_POST['zend']))
{
	if ($error_token = $app['form_token']->get_error())
	{
		$app['alert']->error($error_token);
		$app['link']->redirect('autominlimit', $app['pp_ary'], []);
	}

	$data = [
		'enabled'						=> isset($_POST['enabled']),
		'exclusive'						=> $_POST['exclusive'],
		'trans_percentage'				=> $_POST['trans_percentage'],
		'trans_exclusive'				=> $_POST['trans_exclusive'],
	];

	$app['xdb']->set('setting', 'autominlimit', $data, $app['tschema']);

	$app['alert']->success('De automatische minimum limiet instellingen zijn aangepast.');
	$app['link']->redirect('autominlimit', $app['pp_ary'], []);
}
else
{
	$row = $app['xdb']->get('setting', 'autominlimit', $app['tschema']);

	if ($row)
	{
		$data = $row['data'];
	}
	else
	{
		$data = [
			'enabled'					=> false,
			'exclusive'					=> '',
			'trans_percentage'			=> 100,
			'trans_exclusive'			=> '',
		];
	}
}

$app['heading']->add('Automatische minimum limiet');
$app['heading']->fa('arrows-v');

include __DIR__ . '/../include/header.php';

echo '<div class="panel panel-info">';

echo '<div class="panel-heading"><p>';
echo 'Met dit formulier kan een Automatische Minimum Limiet ingesteld worden. ';
echo 'De individuele Minimum Limiet van Accounts zal zo automatisch lager ';
echo 'worden door ontvangen transacties ';
echo 'tot de ';
echo $app['link']->link_no_attr('config',
	$app['pp_ary'], ['tab' => 'balance'], 'Minimum Systeemslimiet');
echo ' bereikt wordt. ';
echo 'De individuele Account Minimum Limiet wordt gewist wanneer de ';
echo $app['link']->link_no_attr('config',
	$app['pp_ary'], ['tab' => 'balance'], 'Minimum Systeemslimiet');
echo ' bereikt of onderschreden wordt.</p>';
echo '<p>Wanneer geen Minimum Systeemslimiet is ingesteld, ';
echo 'dan blijft de individuele Account Minimum Limiet bij elke ';
echo 'transactie naar het Account telkens dalen.</p>';

echo '<p>Individuele Account Minimum Limieten die ';
echo 'lager zijn dan de algemene Minimum Systeemslimiet ';
echo 'blijven altijd ongewijzigd.</p>';
echo '<p>Wanneer de Automatische Minimum Limiet systematisch ';
echo 'voor instappende leden gebruikt wordt, is het ';
echo 'nuttig de ';
echo $app['link']->link_no_attr('config',
	$app['pp_ary'], ['tab' => 'balance'],
	'Preset Individuele Account Minimum Limiet');
echo ' ';
echo 'in te vullen in de instellingen.</p>';

echo '<form method="post">';

echo '<div class="form-group">';
echo '<label for="enabled" class="control-label">';
echo '<input type="checkbox" id="enabled" name="enabled" value="1" ';
echo $data['enabled'] ? ' checked="checked"' : '';
echo '>';
echo ' Zet de automatische minimum limiet aan</label>';
echo '</div>';

echo '<hr>';

echo '<h3>Voor accounts</h3>';
echo '<p>De automatische minimum limiet is enkel van toepassing op actieve accounts die ';
echo 'rol van gewone gebruiker hebben (user) en die ';
echo 'niet de status uitstapper hebben. Hieronder kunnnen nog verder individuele accounts uitgesloten ';
echo 'worden.</p>';

echo '<div class="form-group">';
echo '<label for="exclusive" class="control-label">';
echo 'Exclusief</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<span class="fa fa-user"></span></span>';
echo '<input type="text" id="exclusive" name="exclusive" ';
echo 'value="';
echo $data['exclusive'];
echo '" ';
echo 'class="form-control">';
echo '</div>';
echo '<p>';
echo 'Account Codes gescheiden door comma\'s</p>';
echo '</div>';

echo '<hr>';

echo '<h3>Trigger voor daling van de minimum limiet.</h3>';
echo '<h4>Ontvangen transacties laten de minimum limiet dalen.</h4>';

echo '<div class="form-group">';
echo '<label for="trans_percentage" class="control-label">';
echo 'Percentage van ontvangen bedrag</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<span class="fa fa-percent"></span></span>';
echo '<input type="number" id="trans_percentage" name="trans_percentage" ';
echo 'value="';
echo $data['trans_percentage'];
echo '" ';
echo 'class="form-control">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="trans_exclusive" class="control-label">';
echo 'Exclusief tegenpartijen</label>';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<span class="fa fa-user"></span></span>';
echo '<input type="text" id="trans_exclusive" name="trans_exclusive" ';
echo 'value="';
echo $data['trans_exclusive'];
echo '" ';
echo 'class="form-control">';
echo '</div>';
echo '<p>';
echo 'Account Codes gescheiden door comma\'s</p>';
echo '</div>';

echo '<input type="submit" value="Aanpassen" name="zend" class="btn btn-primary">';
echo $app['form_token']->get_hidden_input();

echo '</form>';

echo '</div>';
echo '</div>';

include __DIR__ . '/../include/footer.php';
