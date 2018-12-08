<?php

$rootpath = '';
$page_access = 'guest';
require_once __DIR__ . '/include/web.php';

$tschema = $app['this_group']->get_schema();

$fa = 'files-o';

$q = $_GET['q'] ?? '';
$del = $_GET['del'] ?? false;
$edit = $_GET['edit'] ?? false;
$map = $_GET['map'] ?? false;
$map_edit = $_GET['map_edit'] ?? false;
$add = isset($_GET['add']) ? true : false;
$submit = isset($_POST['zend']) ? true : false;
$confirm_del = isset($_POST['confirm_del']) ? true : false;

if (($confirm_del || $submit || $add || $edit || $del || $post || $map_edit) & !$s_admin)
{
	$app['alert']->error('Je hebt onvoldoende rechten voor deze actie.');
	cancel();
}

/**
 * edit map
 */

if ($map_edit)
{
	$row = $app['xdb']->get('doc', $map_edit, $tschema);

	if ($row)
	{
		$map_name = $row['data']['map_name'];
	}

	if (!$map_name)
	{
		$app['alert']->error('Map niet gevonden.');
		cancel();
	}

	if ($submit)
	{
		if ($error_token = $app['form_token']->get_error())
		{
			$app['alert']->error($error_token);

			cancel($map_edit);
		}

		$posted_map_name = trim($_POST['map_name']);

		if (!strlen($posted_map_name))
		{
			$errors[] = 'Geen map naam ingevuld!';
		}

		if (!count($errors))
		{

			$rows = $app['xdb']->get_many(['agg_schema' => $tschema,
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
				], $tschema);

			$app['alert']->success('Map naam aangepast.');

			$app['typeahead']->delete_thumbprint('doc_map_names', [
				'schema' => $tschema,
			]);

			cancel($map_edit);
		}

		$app['alert']->error($errors);
	}

	$app['assets']->add(['typeahead', 'typeahead.js']);

	$h1 = 'Map aanpassen: ' . aphp('docs', ['map' => $map_edit], $map_name);

	require_once __DIR__ . '/include/header.php';

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
	echo $app['typeahead']->get([['doc_map_names', ['schema' => $tschema]]]);
	echo '" ';
	echo 'value="';
	echo $map_name;
	echo '">';
	echo '</div>';
	echo '</div>';

	echo aphp('docs', ['map' => $map_edit], 'Annuleren', 'btn btn-default');
	echo '&nbsp;';
	echo '<input type="submit" name="zend" value="Aanpassen" class="btn btn-primary">';
	echo $app['form_token']->get_hidden_input();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	require_once __DIR__ . '/include/footer.php';
	exit;
}

/**
 * edit
 */

if ($edit)
{
	$row = $app['xdb']->get('doc', $edit, $tschema);

	if ($row)
	{
		$doc = $row['data'];
		$doc['ts'] = $row['event_time'];
	}

	if ($submit)
	{
		$update = [
			'user_id'		=> $doc['user_id'],
			'filename'		=> $doc['filename'],
			'org_filename'	=> $doc['org_filename'],
			'name'			=> trim($_POST['name']),
			'access'		=> $_POST['access'],
		];

		$access_error = $app['access_control']->get_post_error();

		if ($access_error)
		{
			$errors[] = $access_error;
		}

		if (!count($errors))
		{
			$map_name = trim($_POST['map_name']);

			if (strlen($map_name))
			{
				$rows = $app['xdb']->get_many(['agg_type' => 'doc',
					'agg_schema' => $tschema,
					'data->>\'map_name\'' => $map_name], 'limit 1');

				if (count($rows))
				{
					$map = reset($rows)['data'];
					$map['id'] = reset($rows)['eland_id'];
				}
				else
				{
					$map = ['map_name' => $map_name];

					$mid = substr(sha1(microtime() . $tschema . $map_name), 0, 24);

					$app['xdb']->set('doc', $mid, $map, $tschema);

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
					'agg_schema' => $tschema,
					'data->>\'map_id\'' => $doc['map_id']]);

				if (count($rows) < 2)
				{
					$app['xdb']->del('doc', $doc['map_id'], $tschema);
				}
			}

			$app['xdb']->set('doc', $edit, $update, $tschema);

			$app['typeahead']->delete_thumbprint('doc_map_names', [
				'schema' => $tschema,
			]);

			$app['alert']->success('Document aangepast');

			cancel($update['map_id']);
		}

		$app['alert']->error($errors);
	}

	if (isset($doc['map_id']) && $doc['map_id'] != '')
	{
		$map_id = $doc['map_id'];

		$map = $app['xdb']->get('doc', $map_id,
			$tschema)['data'];
	}

	$app['assets']->add(['typeahead', 'typeahead.js']);

	$h1 = 'Document aanpassen';

	require_once __DIR__ . '/include/header.php';

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
	echo $app['s3_doc_url'] . $doc['filename'];
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

	echo $app['access_control']->get_radio_buttons('docs', $doc['access']);

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
	echo $app['typeahead']->get([['doc_map_names', ['schema' => $tschema]]]);
	echo '">';
	echo '</div>';
	echo '<p>Optioneel. Creëer een nieuwe map ';
	echo 'of selecteer een bestaande.</p>';
	echo '</div>';

	echo aphp('docs', [], 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" name="zend" value="Aanpassen" class="btn btn-primary">';

	echo '</form>';

	echo '</div>';
	echo '</div>';

	require_once __DIR__ . '/include/footer.php';
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
		cancel();
	}

	$row = $app['xdb']->get('doc', $del, $tschema);

	if ($row)
	{
		$doc = $row['data'];
	}

	if ($doc)
	{
		$err = $app['s3']->doc_del($doc['filename']);

		if ($err)
		{
			$app['monolog']->error('doc delete file fail: ' . $err,
				['schema' => $tschema]);
		}

		if (isset($doc['map_id']))
		{
			$rows = $app['xdb']->get_many(['agg_schema' => $tschema,
				'agg_type'	=> 'doc',
				'data->>\'map_id\'' => $doc['map_id']]);

			if (count($rows) < 2)
			{
				$app['xdb']->del('doc', $doc['map_id'], $tschema);

				$app['typeahead']->delete_thumbprint('doc_map_names', [
					'schema' => $tschema,
				]);

				unset($doc['map_id']);
			}
		}

		$app['xdb']->del('doc', $del, $tschema);

		$app['alert']->success('Het document werd verwijderd.');

		cancel($doc['map_id'] ?? false);
	}

	$app['alert']->error('Document niet gevonden.');
}

if ($del)
{
	$row = $app['xdb']->get('doc', $del, $tschema);

	if ($row)
	{
		$doc = $row['data'];
	}

	if ($doc)
	{
		$h1 = 'Document verwijderen?';

		require_once __DIR__ . '/include/header.php';

		echo '<div class="panel panel-info">';
		echo '<div class="panel-heading">';
		echo '<form method="post">';

		echo '<p>';
		echo '<a href="';
		echo $app['s3_doc_url'] . $doc['filename'];
		echo '" target="_self">';
		echo $doc['name'] ?? $doc['org_filename'];
		echo '</a>';
		echo '</p>';

		echo aphp('docs', [], 'Annuleren', 'btn btn-default');
		echo '&nbsp;';
		echo '<input type="submit" value="Verwijderen" ';
		echo 'name="confirm_del" class="btn btn-danger">';
		echo $app['form_token']->get_hidden_input();
		echo '</form>';

		echo '</div>';
		echo '</div>';

		require_once __DIR__ . '/include/footer.php';
		exit;
	}

	$app['alert']->error('Document niet gevonden.');
}

/**
 * add
 */
if ($submit)
{
	$tmpfile = $_FILES['file']['tmp_name'];
	$file = $_FILES['file']['name'];
	$file_size = $_FILES['file']['size'];
	$type = $_FILES['file']['type'];

	$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

	if ($file_size > 1024 * 1024 * 10)
	{
		$errors[] = 'Het bestand is te groot. De maximum grootte is 10MB.';
	}

	if (!$file)
	{
		$errors[] = 'Geen bestand geselecteerd.';
	}

	$access_error = $app['access_control']->get_post_error();

	if ($access_error)
	{
		$errors[] = $access_error;
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

		$filename = $tschema . '_d_' . $doc_id . '.' . $ext;

		$error = $app['s3']->doc_upload($filename, $tmpfile);

		if ($error)
		{
			$app['monolog']->error('doc upload fail: ' . $error);
			$app['alert']->error('Bestand opladen mislukt.',
				['schema' => $tschema]);
		}
		else
		{
			$doc = [
				'filename'		=> $filename,
				'org_filename'	=> $file,
				'access'		=> $_POST['access'],
				'user_id'		=> ($s_master) ? 0 : $s_id,
			];

			$map_name = trim($_POST['map_name']);

			if (strlen($map_name))
			{
				$rows = $app['xdb']->get_many(['agg_schema' => $tschema,
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

					$app['xdb']->set('doc', $map_id, $map, $tschema);

					$app['typeahead']->delete_thumbprint('doc_map_names', [
						'schema' => $tschema,
					]);
				}

				$doc['map_id'] = $map_id;
			}

			$name = trim($_POST['name']);

			if ($name)
			{
				$doc['name'] = $name;
			}

			$app['xdb']->set('doc', $doc_id, $doc, $tschema);


			$app['alert']->success('Het bestand is opgeladen.');

			cancel($doc['map_id'] ?? false);
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
		$row = $app['xdb']->get('doc', $map, $tschema);

		if ($row)
		{
			$map_name = $row['data']['map_name'];
		}
	}

	$app['assets']->add(['typeahead', 'typeahead.js', 'access_input_cache.js']);

	$h1 = 'Nieuw document opladen';

	include __DIR__ . '/include/header.php';

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

	echo $app['access_control']->get_radio_buttons('docs');

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
	echo $app['typeahead']->get([['doc_map_names', ['schema' => $tschema]]]);
	echo '">';
	echo '</div>';
	echo '<p>Optioneel. Creëer een nieuwe map of ';
	echo 'selecteer een bestaande.</p>';
	echo '</div>';

	$map_context = $map ? ['map' => $map] : [];

	echo aphp('docs', $map_context, 'Annuleren', 'btn btn-default');
	echo '&nbsp;';
	echo '<input type="submit" name="zend" ';
	echo 'value="Document opladen" class="btn btn-success">';
	echo $app['form_token']->get_hidden_input();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include __DIR__ . '/include/footer.php';
	exit;
}

/**
 * list all documents
 */

if ($map)
{
	$row = $app['xdb']->get('doc', $map, $tschema);

	if ($row)
	{
		$map_name = $row['data']['map_name'];
	}

	if (!$map_name)
	{
		$app['alert']->error('Onbestaande map id.');
		cancel();
	}

	$rows = $app['xdb']->get_many(['agg_schema' => $tschema,
		'agg_type' => 'doc',
		'data->>\'map_id\'' => $map,
		'access' => $app['access_control']->get_visible_ary()], 'order by event_time asc');

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
	$rows = $app['xdb']->get_many(['agg_schema' => $tschema,
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

	$rows = $app['xdb']->get_many(['agg_schema' => $tschema,
		'agg_type' => 'doc',
		'data->>\'map_name\'' => ['is null'],
		'access' => $app['access_control']->get_visible_ary()], 'order by event_time asc');

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

if ($s_admin)
{
	$add_buttom_params = ['add' => 1];

	if ($map)
	{
		$add_buttom_params['map'] = $map;
	}

	$top_buttons .= aphp('docs', $add_buttom_params, 'Document opladen', 'btn btn-success', 'Document opladen', 'plus', true);

	if ($map)
	{
		$top_buttons .= aphp('docs', ['map_edit' => $map], 'Map aanpassen', 'btn btn-primary', 'Map aanpassen', 'pencil', true);
	}
}

$csv_en = $s_admin;

$h1 = aphp('docs', [], 'Documenten');
$h1 .= $map ? ': map "' . $map_name . '"' : '';

include __DIR__ . '/include/header.php';

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
	echo ($s_admin) ? '<th data-sort-ignore="true">Aanpassen</th>' : '';
	echo '</tr>';

	echo '</thead>';
	echo '<tbody>';

	foreach($maps as $d)
	{
		$did = $d['id'];

		if (isset($d['count']) && $d['count'])
		{
			echo '<tr class="info">';
			echo '<td>';
			echo aphp('docs', ['map' => $did], $d['map_name'] . ' (' . $d['count'] . ')');
			echo '</td>';

			if ($s_admin)
			{
				echo '<td>';
				echo aphp('docs', ['map_edit' => $did], 'Aanpassen', 'btn btn-primary btn-xs', false, 'pencil');
				echo '</td>';
			}
			echo '</tr>';

			continue;
		}
	}
	echo '</tbody>';
	echo '</table>';

	echo '</div>';
	echo '</div>';
}

if (count($docs))
{
	$show_visibility = ($s_user
		&& $app['config']->get('template_lets', $tschema)
		&& $app['config']->get('interlets_en', $tschema))
		|| $s_admin ? true : false;

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

	echo $s_admin ? '<th data-hide="phone, tablet" data-sort-ignore="true">Acties</th>' : '';
	echo '</tr>';

	echo '</thead>';
	echo '<tbody>';

	foreach($docs as $d)
	{
		$did = $d['id'];

		echo '<tr>';

		echo '<td>';
		echo '<a href="' . $app['s3_doc_url'] . $d['filename'] . '" target="_self">';
		echo (isset($d['name']) && $d['name'] != '') ? $d['name'] : $d['org_filename'];
		echo '</a>';
		echo '</td>';
		echo '<td>' . $app['date_format']->get($d['ts']) . '</td>';

		if ($show_visibility)
		{
			echo '<td>' . $app['access_control']->get_label($d['access']) . '</td>';
		}

		if ($s_admin)
		{
			echo '<td>';
			echo aphp('docs', ['edit' => $did], 'Aanpassen', 'btn btn-primary btn-xs', false, 'pencil');
			echo '&nbsp;';
			echo aphp('docs', ['del' => $did], 'Verwijderen', 'btn btn-danger btn-xs', false, 'times');
			echo '</td>';
		}

		echo '</tr>';
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

include __DIR__ . '/include/footer.php';

function cancel(string $map = ''):void
{
	$params = [];

	if ($map)
	{
		$params['map'] = $map;
	}

	header('Location: ' . generate_url('docs', $params));
	exit;
}
