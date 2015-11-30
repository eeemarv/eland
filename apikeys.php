<?php

$rootpath = './';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$del = ($_GET['del']) ?: 0;

if ($del)
{
	if(isset($_POST['zend']))
	{
		if ($db->delete('apikeys', array('id' => $del)))
		{
			$alert->success('Apikey verwijderd.');
			cancel();
		}
		$alert->error('Apikey niet verwijderd.');
	}
	$apikey = $db->fetchAssoc('SELECT * FROM apikeys WHERE id = ?', array($del));

	$h1 = 'Apikey verwijderen?';
	$fa = 'key';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';
	echo '<dl>';
	echo '<dt>Apikey</dt>';
	echo '<dd>' . $apikey['apikey'] . '</dd>';
	echo '<dt>Comment</dt>';
	echo '<dd>' . $apikey['comment'] .  '</dd>';
	echo '</dl>';
	echo aphp('apikeys', '', 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

$apikey = array(
	'comment'	=> '',
);

if ($_POST['zend'])
{
	$apikey = array(
		'apikey' 	=> $_POST['apikey'],
		'comment'	=> $_POST['comment'],
		'type'		=> 'interlets',
	);

	if($db->insert('apikeys', $apikey))
	{
		$alert->success('Apikey opgeslagen.');
		cancel();
	}
	$alert->error('Apikey niet opgeslagen.');
}

$apikeys = $db->fetchAll('select * from apikeys');

$top_buttons .= '<a href="#add" class="btn btn-success"';
$top_buttons .= ' title="Apikey toevoegen"><i class="fa fa-plus"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

$h1 = 'Apikeys';
$fa = 'key';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-default printview">';

echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-hover table-striped footable">';
echo '<thead>';
echo '<tr>';
echo '<th>Id</th>';
echo '<th>Comment</th>';
echo '<th data-hide="phone">Apikey</th>';
echo '<th data-hide="phone, tablet" data-sort-initial="true">Creatietijdstip</th>';
echo '<th data-hide="phone, tablet" data-sort-ignore="true">Verwijderen</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach($apikeys as $a)
{
	echo '<tr>';
	echo '<td>' . $a['id'] . '</td>';
	echo '<td>' . $a['comment'] . '</td>';
	echo '<td>' . $a['apikey'] . '</td>';
	echo '<td>' . $a['created'] . '</td>';
	echo '<td>';
	echo aphp('apikeys', 'del=' . $a['id'], 'Verwijderen', 'btn btn-danger btn-xs', false, 'times');
	echo '</td>';
	echo '</tr>';
}

echo '</tbody>';
echo '</table>';

echo '</div></div>';

echo '<ul><li>Apikeys zijn enkel nodig voor het leggen van interlets verbindingen naar letsgroepen die ';
echo 'eLAS draaien. Voor het leggen van interlets verbindingen naar andere letsgroepen met eLAS-Heroku op ';
echo 'dezelfde server moet je geen apikey aanmaken.</li></ul>';

$key = sha1($systemname . microtime());

echo '<h3>Apikey toevoegen</h3>';

echo '<div class="panel panel-info" id="add">';
echo '<div class="panel-heading">';

echo '<form method="post" class="form-horizontal" >';

echo '<div class="form-group">';
echo '<label for="apikey" class="col-sm-2 control-label">Apikey</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="apikey" name="apikey" ';
echo 'value="' . $key . '" required readonly>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="comment" class="col-sm-2 control-label">Comment</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="comment" name="comment" ';
echo 'value="' . $apikey['comment'] . '">';
echo '</div>';
echo '</div>';

echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-success">';
echo '</form>';

echo '</div>';
echo '</div>';

include $rootpath.'includes/inc_footer.php';

function cancel($id = '')
{
	$id = ($id) ? 'id=' . $id : '';
	header('Location: ' . generate_url('apikeys', $id));
	exit;
}
