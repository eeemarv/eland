<?php declare(strict_types=1);

if ($app['pp_anonymous'])
{
	exit;
}

$app['heading']->fa('files-o');

$q = $_GET['q'] ?? '';
$del = $_GET['del'] ?? false;
$edit = $_GET['edit'] ?? false;
$map = $_GET['map'] ?? false;
$map_edit = $_GET['map_edit'] ?? false;
$add = isset($_GET['add']) ? true : false;
$confirm_del = $app['request']->request->get('confirm_del', '') ? true : false;

if (($confirm_del
		|| $add || $edit || $del
		|| $app['request']->isMethod('POST')
		|| $map_edit)
	& !$app['pp_admin'])
{
	$app['alert']->error('Je hebt onvoldoende rechten voor deze actie.');
	$app['link']->redirect('docs', $app['pp_ary'], []);
}

/**
 * edit map
 */

if ($map_edit)
{
	$row = $app['xdb']->get('doc', $map_edit, $app['pp_schema']);

	if ($row)
	{
		$map_name = $row['data']['map_name'];
	}

	if (!$map_name)
	{
		$app['alert']->error('Map niet gevonden.');
		$app['link']->redirect('docs', $app['pp_ary'], []);
	}

	if ($app['request']->isMethod('POST'))
	{
		if ($error_token = $app['form_token']->get_error())
		{
			$app['alert']->error($error_token);

			$app['link']->redirect('docs', $app['pp_ary'],
				['map' => $map_edit]);
		}

		$posted_map_name = trim($app['request']->request->get('map_name', ''));

		if (!strlen($posted_map_name))
		{
			$errors[] = 'Geen map naam ingevuld!';
		}

		if (!count($errors))
		{

			$rows = $app['xdb']->get_many(['agg_schema' => $app['pp_schema'],
				'agg_type' => 'doc',
				'eland_id' => ['<>' => $map_edit],
				'data->>\'map_name\'' => $posted_map_name]);

			if (count($rows))
			{
				$errors[] = 'Er bestaat al een map met deze naam!';
			}
		}

		if (!count($errors))
		{
			$app['xdb']->set('doc', $map_edit, [
					'map_name' => $posted_map_name
				], $app['pp_schema']);

			$app['alert']->success('Map naam aangepast.');

			$app['typeahead']->delete_thumbprint('doc_map_names',
				$app['pp_ary'], []);

			$app['link']->redirect('docs', $app['pp_ary'],
				['map' => $map_edit]);
		}

		$app['alert']->error($errors);
	}

	$app['heading']->add('Map aanpassen: ');
	$app['heading']->add($app['link']->link_no_attr('docs', $app['pp_ary'],
		['map' => $map_edit], $map_name));

	require_once __DIR__ . '/../include/header.php';

	echo '<div class="panel panel-info" id="add">';
	echo '<div class="panel-heading">';

	echo '<form method="post">';

	echo '<div class="form-group">';
	echo '<label for="map_name" class="control-label">';
	echo 'Map naam</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<span class="fa fa-folder-o"></span></span>';
	echo '<input type="text" class="form-control" ';
	echo 'id="map_name" name="map_name" ';
	echo 'data-typeahead="';

	echo $app['typeahead']->ini($app['pp_ary'])
		->add('doc_map_names')
		->str();

	echo '" ';
	echo 'value="';
	echo $map_name;
	echo '">';
	echo '</div>';
	echo '</div>';

	echo $app['link']->btn_cancel('docs', $app['pp_ary'], ['map' => $map_edit]);

	echo '&nbsp;';
	echo '<input type="submit" name="zend" value="Aanpassen" class="btn btn-primary">';
	echo $app['form_token']->get_hidden_input();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	require_once __DIR__ . '/../include/footer.php';
	exit;
}

/**
 * edit
 */

if ($edit)
{
	$row = $app['xdb']->get('doc', $edit, $app['pp_schema']);

	if ($row)
	{
		$doc = $row['data'];

		$access = cnst_access::FROM_XDB[$doc['access']];
		$doc['ts'] = $row['event_time'];
	}
	else
	{
		$access = '';
	}

	if ($app['request']->isMethod('POST'))
	{
		$errors = [];

		$access = $app['request']->request->get('access', '');

		if (!$access)
		{
			$errors[] = 'Vul een zichtbaarheid in.';
			$access_xdb = 'user';
		}
		else
		{
			$access_xdb = cnst_access::TO_XDB[$access];
		}

		$update = [
			'user_id'		=> $doc['user_id'],
			'filename'		=> $doc['filename'],
			'org_filename'	=> $doc['org_filename'],
			'name'			=> trim($app['request']->request->get('name', '')),
			'access'		=> $access_xdb,
		];

		if (!count($errors))
		{
			$map_name = trim($app['request']->request->get('map_name', ''));

			if (strlen($map_name))
			{
				$rows = $app['xdb']->get_many(['agg_type' => 'doc',
					'agg_schema' => $app['pp_schema'],
					'data->>\'map_name\'' => $map_name], 'limit 1');

				if (count($rows))
				{
					$map = reset($rows)['data'];
					$map['id'] = reset($rows)['eland_id'];
				}
				else
				{
					$map = ['map_name' => $map_name];

					$mid = substr(sha1(microtime() . $app['pp_schema'] . $map_name), 0, 24);

					$app['xdb']->set('doc', $mid, $map, $app['pp_schema']);

					$map['id'] = $mid;
				}

				$update['map_id'] = $map['id'];
			}
			else
			{
				$update['map_id'] = '';
			}

			if (isset($doc['map_id'])
				&& ((isset($update['map_id']) && $update['map_id'] != $doc['map_id'])
					|| !strlen($map_name)))
			{
				$rows = $app['xdb']->get_many(['agg_type' => 'doc',
					'agg_schema' => $app['pp_schema'],
					'data->>\'map_id\'' => $doc['map_id']]);

				if (count($rows) < 2)
				{
					$app['xdb']->del('doc', $doc['map_id'], $app['pp_schema']);
				}
			}

			$app['xdb']->set('doc', $edit, $update, $app['pp_schema']);

			$app['typeahead']->delete_thumbprint('doc_map_names',
				$app['pp_ary'], []);

			$app['alert']->success('Document aangepast');

			$app['link']->redirect('docs', $app['pp_ary'],
				['map' => $update['map_id']]);
		}

		$app['alert']->error($errors);
	}

	if (isset($doc['map_id']) && $doc['map_id'] != '')
	{
		$map_id = $doc['map_id'];

		$map = $app['xdb']->get('doc', $map_id,
			$app['pp_schema'])['data'];
	}

	$app['heading']->add('Document aanpassen');

	require_once __DIR__ . '/../include/header.php';

	echo '<div class="panel panel-info" id="add">';
	echo '<div class="panel-heading">';

	echo '<form method="post">';

	echo '<div class="form-group">';
	echo '<label for="location" class="control-label">';
	echo 'Locatie</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<span class="fa fa-file-o"></span></span>';
	echo '<input type="text" class="form-control" id="location" ';
	echo 'name="location" value="';
	echo $app['s3_url'] . $doc['filename'];
	echo '" readonly>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="org_filename" class="control-label">';
	echo 'Originele bestandsnaam</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<span class="fa fa-file-o"></span></span>';
	echo '<input type="text" class="form-control" id="org_filename" ';
	echo 'name="org_filename" value="';
	echo $doc['org_filename'];
	echo '" readonly>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="name" class="control-label">';
	echo 'Naam (optioneel)</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<span class="fa fa-file-o"></span></span>';
	echo '<input type="text" class="form-control" ';
	echo 'id="name" name="name" value="';
	echo $doc['name'];
	echo '">';
	echo '</div>';
	echo '</div>';

	echo $app['item_access']->get_radio_buttons('access', $access, 'docs');

	$map_name = $map['map_name'] ?? '';

	echo '<div class="form-group">';
	echo '<label for="map_name" class="control-label">';
	echo 'Map</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-folder-o"></i>';
	echo '</span>';
	echo '<input type="text" class="form-control" id="map_name" name="map_name" value="';
	echo $map_name;
	echo '" ';
	echo 'data-typeahead="';

	echo $app['typeahead']->ini($app['pp_ary'])
		->add('doc_map_names')
		->str();

	echo '">';
	echo '</div>';
	echo '<p>Optioneel. Creëer een nieuwe map ';
	echo 'of selecteer een bestaande.</p>';
	echo '</div>';

	echo $app['link']->btn_cancel('docs', $app['pp_ary'], []);

	echo '&nbsp;';
	echo '<input type="submit" name="zend" value="Aanpassen" class="btn btn-primary">';

	echo '</form>';

	echo '</div>';
	echo '</div>';

	require_once __DIR__ . '/../include/footer.php';
	exit;
}

/**
 * del
 */
if ($confirm_del && $del)
{
	if ($error_token = $app['form_token']->get_error())
	{
		$app['alert']->error($error_token);
		$app['link']->redirect('docs', $app['pp_ary'], []);
	}

	$row = $app['xdb']->get('doc', $del, $app['pp_schema']);

	if ($row)
	{
		$doc = $row['data'];
	}

	if ($doc)
	{
		$err = $app['s3']->del($doc['filename']);

		if ($err)
		{
			$app['monolog']->error('doc delete file fail: ' . $err,
				['schema' => $app['pp_schema']]);
		}

		if (isset($doc['map_id']))
		{
			$rows = $app['xdb']->get_many(['agg_schema' => $app['pp_schema'],
				'agg_type'	=> 'doc',
				'data->>\'map_id\'' => $doc['map_id']]);

			if (count($rows) < 2)
			{
				$app['xdb']->del('doc', $doc['map_id'], $app['pp_schema']);

				$app['typeahead']->delete_thumbprint('doc_map_names',
					$app['pp_ary'], []);

				unset($doc['map_id']);
			}
		}

		$app['xdb']->del('doc', $del, $app['pp_schema']);

		$app['alert']->success('Het document werd verwijderd.');

		$app['link']->redirect('docs', $app['pp_ary'],
			$doc['map_id'] ? ['map' => $doc['map_id']] : []);
	}

	$app['alert']->error('Document niet gevonden.');
}

if ($del)
{
	$row = $app['xdb']->get('doc', $del, $app['pp_schema']);

	if ($row)
	{
		$doc = $row['data'];
	}

	if ($doc)
	{
		$app['heading']->add('Document verwijderen?');

		require_once __DIR__ . '/../include/header.php';

		echo '<div class="panel panel-info">';
		echo '<div class="panel-heading">';
		echo '<form method="post">';

		echo '<p>';
		echo '<a href="';
		echo $app['s3_url'] . $doc['filename'];
		echo '" target="_self">';
		echo $doc['name'] ?? $doc['org_filename'];
		echo '</a>';
		echo '</p>';

		echo $app['link']->btn_cancel('docs', $app['pp_ary'], []);

		echo '&nbsp;';
		echo '<input type="submit" value="Verwijderen" ';
		echo 'name="confirm_del" class="btn btn-danger">';
		echo $app['form_token']->get_hidden_input();
		echo '</form>';

		echo '</div>';
		echo '</div>';

		require_once __DIR__ . '/../include/footer.php';
		exit;
	}

	$app['alert']->error('Document niet gevonden.');
}

/**
 * add
 */
if ($app['request']->isMethod('POST'))
{
	$f_file = $app['request']->files->get('file');

	$tmpfile = $f_file['tmp_name'];
	$file = $f_file['name'];
	$file_size = $f_file['size'];
	$type = $f_file['type'];

	$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

	if ($file_size > 1024 * 1024 * 10)
	{
		$errors[] = 'Het bestand is te groot. De maximum grootte is 10MB.';
	}

	if (!$file)
	{
		$errors[] = 'Geen bestand geselecteerd.';
	}

	$access = $app['request']->request->get('access', '');

	if (!$access)
	{
		$errors[] = 'Vul een zichtbaarheid in';
	}

	if ($token_error = $app['form_token']->get_error())
	{
		$errors[] = $token_error;
	}

	if (count($errors))
	{
		$app['alert']->error($errors);
	}
	else
	{
		$doc_id = substr(sha1(microtime() . mt_rand(0, 1000000)), 0, 24);

		$filename = $app['pp_schema'] . '_d_' . $doc_id . '.' . $ext;

		$error = $app['s3']->doc_upload($filename, $tmpfile);

		if ($error)
		{
			$app['monolog']->error('doc upload fail: ' . $error);
			$app['alert']->error('Bestand opladen mislukt.',
				['schema' => $app['pp_schema']]);
		}
		else
		{
			$doc = [
				'filename'		=> $filename,
				'org_filename'	=> $file,
				'access'		=> cnst_access::TO_XDB[$access],
				'user_id'		=> $app['s_id'],
			];

			$map_name = trim($app['request']->request->get('map_name', ''));

			if (strlen($map_name))
			{
				$rows = $app['xdb']->get_many(['agg_schema' => $app['pp_schema'],
					'agg_type' => 'doc',
					'data->>\'map_name\'' => $map_name], 'limit 1');

				if (count($rows))
				{
					$map = reset($rows)['data'];
					$map_id = reset($rows)['eland_id'];
				}

				if (!$map)
				{
					$map_id = substr(sha1(time() . mt_rand(0, 220000)), 0, 24);

					$map = ['map_name' => $map_name];

					$app['xdb']->set('doc', $map_id, $map, $app['pp_schema']);

					$app['typeahead']->delete_thumbprint('doc_map_names',
						$app['pp_ary'], []);
				}

				$doc['map_id'] = $map_id;
			}

			$name = trim($app['request']->request->get('name', ''));

			if ($name)
			{
				$doc['name'] = $name;
			}

			$app['xdb']->set('doc', $doc_id, $doc, $app['pp_schema']);


			$app['alert']->success('Het bestand is opgeladen.');

			$app['link']->redirect('docs', $app['pp_ary'],
				$doc['map_id'] ? ['map' => $doc['map_id']] : []);
		}
	}
}

/**
 * add form
 */

if ($add)
{
	if ($map)
	{
		$row = $app['xdb']->get('doc', $map, $app['pp_schema']);

		if ($row)
		{
			$map_name = $row['data']['map_name'];
		}
	}

	$app['heading']->add('Nieuw document opladen');

	include __DIR__ . '/../include/header.php';

	echo '<div class="panel panel-info" id="add">';
	echo '<div class="panel-heading">';

	echo '<form method="post" enctype="multipart/form-data">';

	echo '<div class="form-group">';
	echo '<label for="file" class="control-label">';
	echo 'Bestand</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-file-o"></i>';
	echo '</span>';
	echo '<input type="file" class="form-control" id="file" name="file" ';
	echo 'required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="name" class="control-label">';
	echo 'Naam (optioneel)</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<span class="fa fa-file-o"></span></span>';
	echo '<input type="text" class="form-control" ';
	echo 'id="name" name="name">';
	echo '</div>';
	echo '</div>';

	echo $app['item_access']->get_radio_buttons('access', $access, 'docs');

	echo '<div class="form-group">';
	echo '<label for="map_name" class="control-label">';
	echo 'Map</label>';
	echo '<div class="input-group">';
	echo '<span class="input-group-addon">';
	echo '<i class="fa fa-folder-o"></i>';
	echo '</span>';
	echo '<input type="text" class="form-control" id="map_name" name="map_name" value="';
	echo $map_name ?? '';
	echo '" ';
	echo 'data-typeahead="';

	echo $app['typeahead']->ini($app['pp_ary'])
		->add('doc_map_names')
		->str();

	echo '">';
	echo '</div>';
	echo '<p>Optioneel. Creëer een nieuwe map of ';
	echo 'selecteer een bestaande.</p>';
	echo '</div>';

	echo $app['link']->btn_cancel('docs', $app['pp_ary'],
		$map ? ['map' => $map] : []);

	echo '&nbsp;';
	echo '<input type="submit" name="zend" ';
	echo 'value="Document opladen" class="btn btn-success">';
	echo $app['form_token']->get_hidden_input();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include __DIR__ . '/../include/footer.php';
	exit;
}

/**
 * list all documents
 */

if ($map)
{
	$row = $app['xdb']->get('doc', $map, $app['pp_schema']);

	if ($row)
	{
		$map_name = $row['data']['map_name'];
	}

	if (!$map_name)
	{
		$app['alert']->error('Onbestaande map id.');
		$app['link']->redirect('docs', $app['pp_ary'], []);
	}

	$rows = $app['xdb']->get_many(['agg_schema' => $app['pp_schema'],
		'agg_type' => 'doc',
		'data->>\'map_id\'' => $map,
		'access' => $app['item_access']->get_visible_ary_xdb()],
		'order by event_time asc');

	$docs = [];

	if (count($rows))
	{
		foreach ($rows as $row)
		{
			$data = $row['data'] + ['ts' => $row['event_time'], 'id' => $row['eland_id']];

			if ($row['agg_version'] > 1)
			{
				$data['edit_count'] = $row['agg_version'] - 1;
			}

			$docs[] = $data;
		}
	}

	$maps = [];
}
else
{
	$rows = $app['xdb']->get_many(['agg_schema' => $app['pp_schema'],
		'agg_type' => 'doc',
		'data->>\'map_name\'' => ['<>' => '']], 'order by event_time asc');

	$maps = [];

	if (count($rows))
	{
		foreach ($rows as $row)
		{
			$data = $row['data'] + ['ts' => $row['event_time'], 'id' => $row['eland_id']];

			if ($row['agg_version'] > 1)
			{
				$data['edit_count'] = $row['agg_version'] - 1;
			}

			$maps[$row['eland_id']] = $data;
		}
	}

	$rows = $app['xdb']->get_many(['agg_schema' => $app['pp_schema'],
		'agg_type' => 'doc',
		'data->>\'map_name\'' => ['is null'],
		'access' => $app['item_access']->get_visible_ary_xdb()],
		'order by event_time asc');

	$docs = [];

	if (count($rows))
	{
		foreach ($rows as $row)
		{
			$data = $row['data'] + ['ts' => $row['event_time'], 'id' => $row['eland_id']];

			if ($row['agg_version'] > 1)
			{
				$data['edit_count'] = $row['agg_version'] - 1;
			}

			$docs[] = $data;
		}
	}
}

if (!$map)
{
	foreach ($docs as $k => $d)
	{
		if (isset($d['map_id']))
		{
			if (!isset($maps[$d['map_id']]))
			{
				continue;
			}

			if (!isset($maps[$d['map_id']]['count']))
			{
				$maps[$d['map_id']]['count'] = 0;
			}

			$maps[$d['map_id']]['count']++;
			unset($docs[$k]);
		}
	}
}

if ($app['pp_admin'])
{
	$add_buttom_params = ['add' => 1];

	if ($map)
	{
		$add_buttom_params['map'] = $map;
	}

	$app['btn_top']->add('docs', $app['pp_ary'],
		$add_buttom_params, 'Document opladen');

	if ($map)
	{
		$app['btn_top']->edit('docs', $app['pp_ary'],
			['map_edit' => $map], 'Map aanpassen');
	}
}

if ($app['pp_admin'])
{
	$app['btn_nav']->csv();
}

$app['heading']->add($app['link']->link_no_attr('docs', $app['pp_ary'], [], 'Documenten'));

if ($map)
{
	$app['heading']->add(': map "' . $map_name . '"');
}

include __DIR__ . '/../include/header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="get">';
echo '<div class="row">';
echo '<div class="col-xs-12">';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-search"></i>';
echo '</span>';
echo '<input type="text" class="form-control" id="q" name="q" value="';
echo $q;
echo '" ';
echo 'placeholder="Zoeken">';
echo '</div>';
echo '</div>';
echo '</div>';
echo '</form>';

echo '</div>';
echo '</div>';

if (!$map && count($maps))
{
	echo '<div class="panel panel-default printview">';

	echo '<div class="table-responsive">';
	echo '<table class="table table-bordered table-striped table-hover footable"';
	echo ' data-filter="#q" data-filter-minimum="1">';
	echo '<thead>';

	echo '<tr>';
	echo '<th data-sort-initial="true">Map</th>';
	echo $app['pp_admin'] ? '<th data-sort-ignore="true">Aanpassen</th>' : '';
	echo '</tr>';

	echo '</thead>';
	echo '<tbody>';

	foreach($maps as $d)
	{
		$did = $d['id'];

		if (isset($d['count']) && $d['count'])
		{
			$out = [];

			$out[] = $app['link']->link_no_attr('docs', $app['pp_ary'],
				['map' => $did], $d['map_name'] . ' (' . $d['count'] . ')');

			if ($app['pp_admin'])
			{
				$out[] = $app['link']->link_fa('docs', $app['pp_ary'],
					['map_edit' => $did], 'Aanpassen',
					['class' => 'btn btn-primary btn-xs'], 'pencil');
			}

			echo '<tr class="info"><td>';
			echo implode('</td><td>', $out);
			echo '</td></tr>';
		}
	}

	echo '</tbody>';
	echo '</table>';

	echo '</div>';
	echo '</div>';
}

if (count($docs))
{
	$show_visibility = ($app['pp_user']
			&& $app['intersystem_en'])
		|| $app['pp_admin'];

	echo '<div class="panel panel-default printview">';

	echo '<div class="table-responsive">';
	echo '<table class="table table-bordered ';
	echo 'table-striped table-hover footable csv" ';
	echo 'data-filter="#q" data-filter-minimum="1">';
	echo '<thead>';

	echo '<tr>';
	echo '<th data-sort-initial="true">';
	echo 'Naam</th>';
	echo '<th data-hide="phone, tablet">';
	echo 'Tijdstip</th>';

	if ($show_visibility)
	{
		echo '<th data-hide="phone, tablet">';
		echo 'Zichtbaarheid</th>';
	}

	echo $app['pp_admin'] ? '<th data-hide="phone, tablet" data-sort-ignore="true">Acties</th>' : '';
	echo '</tr>';

	echo '</thead>';
	echo '<tbody>';

	foreach($docs as $d)
	{
		$did = $d['id'];

		$out = [];

		$out_c = '<a href="';
		$out_c .= $app['s3_url'] . $d['filename'];
		$out_c .= '" target="_self">';
		$out_c .= (isset($d['name']) && $d['name'] != '') ? $d['name'] : $d['org_filename'];
		$out_c .= '</a>';
		$out[] = $out_c;

		$out[] = $app['date_format']->get($d['ts'], 'min', $app['pp_schema']);

		if ($show_visibility)
		{
			$out[] = $app['item_access']->get_label_xdb($d['access']);
		}

		if ($app['pp_admin'])
		{
			$out_c = $app['link']->link_fa('docs', $app['pp_ary'],
				['edit' => $did], 'Aanpassen',
				['class' => 'btn btn-primary btn-xs'], 'pencil');
			$out_c .= '&nbsp;';
			$out_c .= $app['link']->link_fa('docs', $app['pp_ary'],
				['del' => $did], 'Verwijderen',
				['class' => 'btn btn-danger btn-xs'], 'times');
			$out[] = $out_c;
		}

		echo '<tr><td>';
		echo implode('</td><td>', $out);
		echo '</td></tr>';
	}

	echo '</tbody>';
	echo '</table>';

	echo '</div>';
	echo '</div>';
}
else if (!count($maps))
{
	echo '<div class="panel panel-default">';
	echo '<div class="panel-heading">';
	echo '<p>Er zijn nog geen documenten opgeladen.</p>';
	echo '</div></div>';
}

include __DIR__ . '/../include/footer.php';
