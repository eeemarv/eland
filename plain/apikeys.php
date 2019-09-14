<?php declare(strict_types=1);

if (!$app['pp_admin'])
{
	exit;
}

$del = $_GET['del'] ?? false;
$add = $_GET['add'] ?? false;

/*
if (!$app['intersystem_en'])
{
	redirect_default_page();
}
*/

if ($del)
{
	if($submit)
	{
		if ($error_token = $app['form_token']->get_error())
		{
			$app['alert']->error($error_token);
			$app['link']->redirect('apikeys', $app['pp_ary'], []);
		}

		if ($app['db']->delete($app['pp_schema'] . '.apikeys',
			['id' => $del]))
		{
			$app['alert']->success('Apikey verwijderd.');
			$app['link']->redirect('apikeys', $app['pp_ary'], []);
		}

		$app['alert']->error('Apikey niet verwijderd.');
	}
	$apikey = $app['db']->fetchAssoc('select *
		from ' . $app['pp_schema'] . '.apikeys
		where id = ?', [$del]);

	$app['heading']->add('Apikey verwijderen?');
	$app['heading']->fa('key');

	include __DIR__ . '/../include/header.php';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post">';
	echo '<dl>';
	echo '<dt>Apikey</dt>';
	echo '<dd>';
	echo $apikey['apikey'] ?: '<i class="fa fa-times"></i>';
	echo '</dd>';
	echo '<dt>Commentaar</dt>';
	echo '<dd>';
	echo $apikey['comment'] ?: '<i class="fa fa-times"></i>';
	echo '</dd>';
	echo '</dl>';
	echo $app['link']->btn_cancel('apikeys', $app['pp_ary'], []);
	echo '&nbsp;';
	echo '<input type="submit" value="Verwijderen" ';
	echo 'name="zend" class="btn btn-danger">';
	echo $app['form_token']->get_hidden_input();
	echo '</form>';

	echo '</div>';
	echo '</div>';

	include __DIR__ . '/../include/footer.php';
	exit;
}

$apikey = [
	'comment'	=> '',
];

if ($add)
{
	if ($app['request']->isMethod('POST'))
	{
		if ($error_token = $app['form_token']->get_error())
		{
			$app['alert']->error($error_token);
			$app['link']->redirect('apikeys', $app['pp_ary'], []);
		}

		$apikey = [
			'apikey' 	=> $app['request']->request->get('apikey', ''),
			'comment'	=> $app['request']->request->get('comment', ''),
			'type'		=> 'interlets',
		];

		if($app['db']->insert($app['pp_schema'] . '.apikeys', $apikey))
		{
			$app['alert']->success('Apikey opgeslagen.');
			$app['link']->redirect('apikeys', $app['pp_ary'], []);
		}

		$app['alert']->error('Apikey niet opgeslagen.');
	}

	$key = sha1($app['config']->get('systemname', $app['pp_schema']) . microtime());

	$app['heading']->add('Apikey toevoegen');
	$app['heading']->fa('key');

	include __DIR__ . '/../include/header.php';

	echo get_apikey_explain();

	echo '<div class="panel panel-info" id="add">';
	echo '<div class="panel-heading">';

	echo '<form method="post">';

	echo '<div class="form-group">';
	echo '<label for="apikey" class="control-label">';
	echo 'Apikey</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon" id="name_addon">';
	echo '<span class="fa fa-key"></span></span>';
	echo '<input type="text" class="form-control" ';
	echo 'id="apikey" name="apikey" ';
	echo 'value="';
	echo $key;
	echo '" required readonly>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="comment" class="control-label">';
	echo 'Commentaar</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon" id="name_addon">';
	echo '<span class="fa fa-comment-o"></span></span>';
	echo '<input type="text" class="form-control" ';
	echo 'id="comment" name="comment" ';
	echo 'value="';
	echo $apikey['comment'] ?? '';
	echo '">';
	echo '</div>';
	echo '</div>';

	echo $app['link']->btn_cancel('apikeys', $app['pp_ary'], []);
	echo '&nbsp;';
	echo '<input type="submit" name="zend" ';
	echo 'value="Opslaan" class="btn btn-success">';
	echo $app['form_token']->get_hidden_input();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include __DIR__ . '/../include/footer.php';
	exit;
}

$apikeys = $app['db']->fetchAll('select *
	from ' . $app['pp_schema'] . '.apikeys');

$app['btn_top']->add('apikeys', $app['pp_ary'], ['add' => '1'], 'Apikey toevoegen');

$app['heading']->add('Apikeys');
$app['heading']->fa('key');

include __DIR__ . '/../include/header.php';

echo get_apikey_explain();

echo '<div class="panel panel-default printview">';

echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-hover table-striped footable">';
echo '<thead>';
echo '<tr>';
echo '<th>Id</th>';
echo '<th>Commentaar</th>';
echo '<th data-hide="phone">Apikey</th>';
echo '<th data-hide="phone, tablet" data-sort-initial="true">Gecreëerd</th>';
echo '<th data-hide="phone, tablet" data-sort-ignore="true">Verwijderen</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach($apikeys as $a)
{
	$td = [];
	$td[] = $a['id'];
	$td[] = $a['comment'];
	$td[] = $a['apikey'];
	$td[] = $app['date_format']->get_td($a['created'], 'min', $app['pp_schema']);
	$td[] = $app['link']->link_fa('apikeys', $app['pp_ary'],
		['del' => $a['id']], 'Verwijderen',
		['class' => 'btn btn-danger btn-xs'], 'times');

	echo '<tr><td>';
	echo implode('</td><td>', $td);
	echo '</td></tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div></div>';

include __DIR__ . '/../include/footer.php';

function get_apikey_explain():string
{
	$out = '<p>';
	$out .= '<ul>';
	$out .= '<li>';
	$out .= 'Apikeys zijn enkel nodig voor het leggen van ';
	$out .= 'interSysteem verbindingen naar andere Systemen die ';
	$out .= 'eLAS draaien.</li>';
	$out .= '<li>Voor het leggen van interSysteem ';
	$out .= 'verbindingen naar andere Systemen op ';
	$out .= 'deze eLAND-server ';
	$out .= 'moet je geen Apikey aanmaken.';
	$out .= '</li></ul>';
	$out .= '</p>';
	return $out;
}