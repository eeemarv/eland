<?php

$rootpath = './';
$page_access = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$mdb->connect();

if (isset($_POST['zend']))
{
	if ($error_token = get_error_form_token())
	{
		$alert->error($error_token);
		cancel();
	}

	$a = array(
		'name'							=> 'autominlimit',
		'enabled'						=> (isset($_POST['enabled'])) ? true : false,
		'active_no_new_or_leaving'		=> (isset($_POST['active_no_new_or_leaving'])) ? true : false,
		'new'							=> (isset($_POST['new'])) ? true : false,
		'leaving'						=> (isset($_POST['leaving'])) ? true : false,
		'inclusive'						=> $_POST['inclusive'],
		'exclusive'						=> $_POST['exclusive'],
		'min'							=> $_POST['min'],
		'trans_percentage'				=> $_POST['trans_percentage'],
		'account_base'					=> $_POST['account_base'],
		'trans_exclusive'				=> $_POST['trans_exclusive'],
	);

	$mdb->settings->update(array('name' => 'autominlimit'), $a, array('upsert' => true));

	unset($a['_id'], $a['name']);

	$exdb->set('setting', 'autominlimit', $a);

	$alert->success('De automatische minimum limiet instellingen zijn aangepast.');
	cancel();
}
else 
{
	$row = $exdb->get('setting', 'autominlimit');

	if ($row)
	{
		$a = $row['data'];
	}
	else
	{
		$a = $mdb->settings->findOne(array('name'=> 'autominlimit'));

		unset($a['name'], $a['_id']);
		$exdb->set('setting', 'autominlimit', $a);
	}
}

$h1 = 'Automatische minimum limiet';
$fa = 'arrows-v';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';

echo '<div class="panel-body"><p>';
echo 'Met dit formulier kan een automatische minimum limiet ingesteld worden. ';
echo 'De minimum limiet van gebruikers zal zo automatisch lager worden door ontvangen transacties</p></div>';
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
echo '<p>Enkel actieve accounts kunnen een automatische minimum limiet hebben.</p>';

echo '<div class="form-group">';
echo '<label for="active_no_new_or_leaving" class="col-sm-3 control-label">';
echo 'Alle actieve zonder in- en uitstappers</label>';
echo '<div class="col-sm-9">';
echo '<input type="checkbox" id="active_no_new_or_leaving" name="active_no_new_or_leaving" value="1" ';
echo ($a['active_no_new_or_leaving']) ? ' checked="checked"' : '';
echo '>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="new" class="col-sm-3 control-label">';
echo 'Instappers</label>';
echo '<div class="col-sm-9">';
echo '<input type="checkbox" id="new" name="new" value="1" ';
echo ($a['new']) ? ' checked="checked"' : '';
echo '>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="leaving" class="col-sm-3 control-label">';
echo 'Uitstappers</label>';
echo '<div class="col-sm-9">';
echo '<input type="checkbox" id="leaving" name="leaving" value="1" ';
echo ($a['leaving']) ? ' checked="checked"' : '';
echo '>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="inclusive" class="col-sm-3 control-label">';
echo 'Inclusief (letscodes gescheiden door comma\'s)</label>';
echo '<div class="col-sm-9">';
echo '<input type="text" id="inclusive" name="inclusive" ';
echo 'value="' . $a['inclusive'] . '" ';
echo 'class="form-control">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="exclusive" class="col-sm-3 control-label">';
echo 'Exclusief (letscodes gescheiden door comma\'s)</label>';
echo '<div class="col-sm-9">';
echo '<input type="text" id="exclusive" name="exclusive" ';
echo 'value="' . $a['exclusive'] . '" ';
echo 'class="form-control">';
echo '</div>';
echo '</div>';

echo '<hr>';

echo '<h3>Begrenzing</h3>';
echo '<p>Grens van de automatische minimum limiet (in ' . $currency . ').</p>';

echo '<div class="form-group">';
echo '<label for="max" class="col-sm-3 control-label">';
echo 'Ondergrens</label>';
echo '<div class="col-sm-9">';
echo '<input type="number" id="min" name="min" ';
echo 'value="' . $a['min'] . '" ';
echo 'class="form-control">';
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
echo '<label for="account_base" class="col-sm-3 control-label">';
echo 'Enkel wanneer account is boven bedrag (' . $currency . ')</label>';
echo '<div class="col-sm-9">';
echo '<input type="number" id="account_base" name="account_base" ';
echo 'value="' . $a['account_base'] . '" ';
echo 'class="form-control">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="trans_exclusive" class="col-sm-3 control-label">';
echo 'Exclusief tegenpartijen (letscodes gescheiden door comma\'s)</label>';
echo '<div class="col-sm-9">';
echo '<input type="text" id="trans_exclusive" name="trans_exclusive" ';
echo 'value="' . $a['trans_exclusive'] . '" ';
echo 'class="form-control">';
echo '</div>';
echo '</div>';

echo '<input type="submit" value="Aanpassen" name="zend" class="btn btn-primary">';
generate_form_token();

echo '</form>';

echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';

function cancel()
{
	header('Location: ' . generate_url('autominlimit'));
	exit;
}
