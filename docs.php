<?php

$rootpath = '';
$page_access = 'guest';
require_once $rootpath . 'includes/inc_default.php';

$fa = 'files-o';

$q = $_GET['q'] ?? '';
$del = $_GET['del'] ?? false;
$edit = $_GET['edit'] ?? false;
$map = $_GET['map'] ?? false;
$map_edit = $_GET['map_edit'] ?? false;
$add = isset($_GET['add']) ? true : false;

$submit = isset($_POST['zend']) ? true : false;
$confirm_del = isset($_POST['confirm_del']) ? true : false;

if ($post)
{
	$s3 = Aws\S3\S3Client::factory([
		'signature'	=> 'v4',
		'region'	=> 'eu-central-1',
		'version'	=> '2006-03-01',
	]);
}

if (($confirm_del || $submit || $add || $edit || $del || $post || $map_edit) & !$s_admin)
{
	$alert->error('Je hebt onvoldoende rechten voor deze actie.');
	cancel();
}

/**
 * edit map
 */

if ($map_edit)
{
	$row = $exdb->get('doc', $map_edit);

	if ($row)
	{
		$map_name = $row['data']['map_name'];
	}

	if (!$map_name)
	{
		$alert->error('Map niet gevonden.');
		cancel();
	}

	if ($submit)
	{
		if ($error_token = get_error_form_token())
		{
			$alert->error($error_token);

			cancel($map_edit);
		}

		$posted_map_name = trim($_POST['map_name']);

		if (!strlen($posted_map_name))
		{
			$errors[] = 'Geen map naam ingevuld!';
		}

		if (!count($errors))
		{
			$rows = $exdb->get_many(['agg_schema' => $schema,
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
			$exdb->set('doc', $map_edit, ['map_name' => $posted_map_name]);

			$alert->success('Map naam aangepast.');

			$app['eland.typeahead']->invalidate_thumbprint('doc_map_names');

			cancel($map_edit);
		}

		$alert->error($errors);
	}

/*
	$include_ary[] = 'typeahead';
	$include_ary[] = 'typeahead.js';
*/

	$app['eland.assets']->add(['typeahead', 'typeahead.js']);

	$h1 = 'Map aanpassen: ' . aphp('docs', ['map' => $map_edit], $map_name);

	require_once $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info" id="add">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';

	echo '<div class="form-group">';
	echo '<label for="map_name" class="col-sm-2 control-label">Map naam</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="map_name" name="map_name" ';
	echo 'data-typeahead="' . $app['eland.typeahead']->get('doc_map_names') . '" ';
	echo 'value="' . $map_name . '">';
	echo '</div>';
	echo '</div>';

	echo aphp('docs', ['map' => $map_edit], 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" name="zend" value="Aanpassen" class="btn btn-primary">';
	generate_form_token();	

	echo '</form>';

	echo '</div>';
	echo '</div>';

	require_once $rootpath . 'includes/inc_footer.php';
	exit;
}

/**
 * edit
 */

if ($edit)
{
	$row = $exdb->get('doc', $edit);

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

		$access_error = $access_control->get_post_error();

		if ($access_error)
		{
			$errors[] = $access_error;
		}

		if (!count($errors))
		{
			$map_name = trim($_POST['map_name']);

			if (strlen($map_name))
			{
				$rows = $exdb->get_many(['agg_type' => 'doc',
					'agg_schema' => $schema,
					'data->>\'map_name\'' => $map_name], 'limit 1');

				if (count($rows))
				{
					$map = reset($rows)['data'];
					$map['id'] = reset($rows)['eland_id'];
				}
				else
				{
					$map = ['map_name' => $map_name];

					$mid = substr(sha1(microtime() . $schema . $map_name), 0, 24);

					$exdb->set('doc', $mid, $map);

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
				$rows = $exdb->get_many(['agg_type' => 'doc',
					'agg_schema' => $schema,
					'data->>\'map_id\'' => $doc['map_id']]);

				if (count($rows) < 2)
				{
					$exdb->del('doc', $doc['map_id']);
				}
			}

			$exdb->set('doc', $edit, $update);

			$app['eland.typeahead']->invalidate_thumbprint('doc_map_names');

			$alert->success('Document aangepast');

			cancel($update['map_id']);
		}

		$alert->error($errors);
	}

	if (isset($doc['map_id']) && $doc['map_id'] != '')
	{
		$map_id = $doc['map_id'];

		$map = $exdb->get('doc', $map_id)['data'];
	}

/*
	$include_ary[] = 'typeahead';
	$include_ary[] = 'typeahead.js';
*/

	$app['eland.assets']->add(['typeahead', 'typeahead.js']);

	$h1 = 'Document aanpassen';

	require_once $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info" id="add">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';	

	echo '<div class="form-group">';
	echo '<label for="location" class="col-sm-2 control-label">Locatie</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="location" ';
	echo 'name="location" value="' . $app['eland.s3_doc_url'] . $doc['filename'] . '" readonly>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="org_filename" class="col-sm-2 control-label">Originele bestandsnaam</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="org_filename" ';
	echo 'name="org_filename" value="' . $doc['org_filename'] . '" readonly>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="name" class="col-sm-2 control-label">Naam (optioneel)</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="name" name="name" value="' . $doc['name'] . '">';
	echo '</div>';
	echo '</div>';

	echo $access_control->get_radio_buttons('docs', $doc['access']);

	$map_name = $map['map_name'] ?? '';

	echo '<div class="form-group">';
	echo '<label for="map_name" class="col-sm-2 control-label">Map (optioneel, creëer een nieuwe map of selecteer een bestaande)</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="map_name" name="map_name" value="' . $map_name . '" ';
	echo 'data-typeahead="' . $app['eland.typeahead']->get('doc_map_names') . '">';
	echo '</div>';
	echo '</div>';

	echo aphp('docs', [], 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" name="zend" value="Aanpassen" class="btn btn-primary">';

	echo '</form>';

	echo '</div>';
	echo '</div>';

	require_once $rootpath . 'includes/inc_footer.php';
	exit;
}

/**
 * del
 */
if ($confirm_del && $del)
{
	if ($error_token = get_error_form_token())
	{
		$alert->error($error_token);
		cancel();
	}

	$row = $exdb->get('doc', $del);

	if ($row)
	{
		$doc = $row['data'];
	}

	if ($doc)
	{
		$s3->deleteObject([
			'Bucket'	=> $app['eland.s3_doc'],
			'Key'		=> $doc['filename'],
		]);

		$rows = $exdb->get_many(['agg_schema' => $schema,
			'agg_type'	=> 'doc',
			'data->>\'map_id\'' => $doc['map_id']]);

		if (count($rows) < 2)
		{
			$exdb->del('doc', $doc['map_id']);
			$app['eland.typeahead']->invalidate_thumbprint('doc_map_names');

			unset($doc['map_id']);
		}

		$exdb->del('doc', $del);

		$alert->success('Het document werd verwijderd.');

		cancel($doc['map_id']);
	}

	$alert->error('Document niet gevonden.');
}

if ($del)
{
	$row = $exdb->get('doc', $del);

	if ($row)
	{
		$doc = $row['data'];
	}

	if ($doc)
	{
		$h1 = 'Document verwijderen?';

		require_once $rootpath . 'includes/inc_header.php';
		
		echo '<div class="panel panel-info">';
		echo '<div class="panel-heading">';
		echo '<form method="post">';

		echo '<p>';
		echo '<a href="' . $app['eland.s3_doc_url'] . $doc['filename'] . '" target="_self">';
		echo ($doc['name']) ?: $doc['org_filename'];
		echo '</a>';
		echo '</p>';

		echo aphp('docs', [], 'Annuleren', 'btn btn-default') . '&nbsp;';
		echo '<input type="submit" value="Verwijderen" name="confirm_del" class="btn btn-danger">';
		generate_form_token();
		echo '</form>';

		echo '</div>';
		echo '</div>';

		require_once $rootpath . 'includes/inc_footer.php';
		exit;
	}

	$alert->error('Document niet gevonden.');
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

	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$file_type = finfo_file($finfo, $tmpfile);
	finfo_close($finfo);

	$extension_types = [
		'docx'		=> 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'docm'		=> 'application/vnd.ms-word.document.macroEnabled.12',
		'dotx'		=> 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
		'dotm'		=> 'application/vnd.ms-word.template.macroEnabled.12',
		'xlsx'		=> 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'xlsm'		=> 'application/vnd.ms-excel.sheet.macroEnabled.12',
		'xltx'		=> 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
		'xltm'		=> 'application/vnd.ms-excel.template.macroEnabled.12',
		'xlsb'		=> 'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
		'xlam'		=> 'application/vnd.ms-excel.addin.macroEnabled.12',
		'pptx'		=> 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'pptm'		=> 'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
		'ppsx'		=> 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
		'ppsm'		=> 'application/vnd.ms-powerpoint.slideshow.macroEnabled.12',
		'potx'		=> 'application/vnd.openxmlformats-officedocument.presentationml.template',
		'potm'		=> 'application/vnd.ms-powerpoint.template.macroEnabled.12',
		'ppam'		=> 'application/vnd.ms-powerpoint.addin.macroEnabled.12',
		'sldx'		=> 'application/vnd.openxmlformats-officedocument.presentationml.slide',
		'sldm'		=> 'application/vnd.ms-powerpoint.slide.macroEnabled.12',
		'one'		=> 'application/msonenote',
		'onetoc2'	=> 'application/msonenote',
		'onetmp'	=> 'application/msonenote',
		'onepkg'	=> 'application/msonenote',
		'thmx'		=> 'application/vnd.ms-officetheme',
		'doc'		=> 'application/msword',
		'dot'		=> 'application/msword',
		'xls'		=> 'application/vnd.ms-excel',
		'xlt'		=> 'application/vnd.ms-excel',
		'xla'		=> 'application/vnd.ms-excel',
		'ppt' 		=> 'application/vnd.ms-powerpoint',
		'pot'		=> 'application/vnd.ms-powerpoint',
		'pps'		=> 'application/vnd.ms-powerpoint',
		'ppa'		=> 'application/vnd.ms-powerpoint',
		'css'		=> 'text/css',
		'html'		=> 'text/html',
		'md'		=> 'text/markdown',
	];

	$media_type = $extension_types[$ext] ?? $file_type;

	if ($file_size > 1024 * 1024 * 10)
	{
		$errors[] = 'Het bestand is te groot. De maximum grootte is 10MB.';
	}

	if (!$file)
	{
		$errors[] = 'Geen bestand geselecteerd.';
	}

	$access_error = $access_control->get_post_error();

	if ($access_error)
	{
		$errors[] = $access_error;
	}

	if ($token_error = get_error_form_token())
	{
		$errors[] = $token_error;
	}

	if (count($errors))
	{
		$alert->error($errors);
	}
	else
	{
		$doc_id = substr(sha1(microtime() . mt_rand(0, 1000000)), 0, 24);

		$filename = $schema . '_d_' . $doc_id . '.' . $ext;

		$doc = [
			'filename'		=> $filename,
			'org_filename'	=> $file,
			'access'		=> $_POST['access'],
			'user_id'		=> ($s_master) ? 0 : $s_id,
		];

		$map_name = trim($_POST['map_name']);

		if (strlen($map_name))
		{
			$rows = $exdb->get_many(['agg_schema' => $schema,
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

				$exdb->set('doc', $map_id, $map);

				$app['eland.typeahead']->invalidate_thumbprint('doc_map_names');
			}

			$doc['map_id'] = $map_id;
		}

		$name = trim($_POST['name']);

		if ($name)
		{
			$doc['name'] = $name;
		}

		$exdb->set('doc', $doc_id, $doc);

		$params = [
			'CacheControl'			=> 'public, max-age=31536000',
			'ContentType'			=> $media_type,
		];

		$upload = $s3->upload($app['eland.s3_doc'], $filename, fopen($tmpfile, 'rb'), 'public-read', [
			'params'	=> $params
		]);

		$alert->success('Het bestand is opgeladen.');

		cancel($doc['map_id']);
	}
}

/**
 * add form
 */

if ($add)
{
	if ($map)
	{
		$row = $exdb->get('doc', $map);

		if ($row)
		{
			$map_name = $row['data']['map_name'];
		}
	}

/*
	$include_ary[] = 'typeahead';
	$include_ary[] = 'typeahead.js';
	$include_ary[] = 'access_input_cache.js';
*/

	$app['eland.assets']->add(['typeahead', 'typeahead.js', 'access_input_cache.js']);

	$top_buttons .= aphp('docs', [], 'Lijst', 'btn btn-default', 'Lijst', 'files-o', true);

	$h1 = 'Nieuw document opladen';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info" id="add">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal" enctype="multipart/form-data">';	

	echo '<div class="form-group">';
	echo '<label for="file" class="col-sm-2 control-label">Bestand</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="file" class="form-control" id="file" name="file" ';
	echo 'required>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="name" class="col-sm-2 control-label">Naam (optioneel)</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="name" name="name">';
	echo '</div>';
	echo '</div>';

	echo $access_control->get_radio_buttons('docs');

	echo '<div class="form-group">';
	echo '<label for="map_name" class="col-sm-2 control-label">Map (optioneel, creëer een nieuwe map of selecteer een bestaande)</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="map_name" name="map_name" value="';
	echo $map_name ?? '';
	echo '" ';
	echo 'data-typeahead="' . $app['eland.typeahead']->get('doc_map_names') . '">';
	echo '</div>';
	echo '</div>';

	$map_context = ($map) ? ['map' => $map] : [];
	echo aphp('docs', $map_context, 'Annuleren', 'btn btn-default') . '&nbsp;';
	echo '<input type="submit" name="zend" value="Document opladen" class="btn btn-success">';
	generate_form_token();

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

/**
 * list all documents
 */

if ($map)
{
	$row = $exdb->get('doc', $map);

	if ($row)
	{
		$map_name = $row['data']['map_name'];
	}

	if (!$map_name)
	{
		$alert->error('Onbestaande map id.');
		cancel();
	}
//

	$rows = $exdb->get_many(['agg_schema' => $schema,
		'agg_type' => 'doc',
		'data->>\'map_id\'' => $map,
		'access' => true], 'order by event_time asc');

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
	$rows = $exdb->get_many(['agg_schema' => $schema,
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

	$rows = $exdb->get_many(['agg_schema' => $schema,
		'agg_type' => 'doc',
		'data->>\'map_name\'' => ['is null'],
		'access' => true], 'order by event_time asc');

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
if ($map)
{
	$top_buttons .= aphp('docs', [], 'Lijst', 'btn btn-default', 'Lijst', 'files-o', true);
}

$h1 = aphp('docs', [], 'Documenten');
$h1 .= ($map) ? ': map "' . $map_name . '"' : '';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="get">';
echo '<div class="row">';
echo '<div class="col-xs-12">';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-search"></i>';
echo '</span>';
echo '<input type="text" class="form-control" id="q" name="q" value="' . $q . '" ';
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
	echo '<div class="panel panel-default printview">';

	echo '<div class="table-responsive">';
	echo '<table class="table table-bordered table-striped table-hover footable"';
	echo ' data-filter="#q" data-filter-minimum="1">';
	echo '<thead>';

	echo '<tr>';
	echo '<th data-sort-initial="true">Naam</th>';
	echo '<th data-hide="phone, tablet">Tijdstip</th>';
	echo ($s_guest) ? '' : '<th data-hide="phone, tablet">Zichtbaarheid</th>';
	echo ($s_admin) ? '<th data-hide="phone, tablet" data-sort-ignore="true">Acties</th>' : '';
	echo '</tr>';

	echo '</thead>';
	echo '<tbody>';

	foreach($docs as $d)
	{
		$did = $d['id'];

		echo '<tr>';

		echo '<td>';
		echo '<a href="' . $app['eland.s3_doc_url'] . $d['filename'] . '" target="_self">';
		echo (isset($d['name']) && $d['name'] != '') ? $d['name'] : $d['org_filename'];
		echo '</a>';
		echo '</td>';
		echo '<td>' . $date_format->get($d['ts']) . '</td>';

		if (!$s_guest)
		{
			echo '<td>' . $access_control->get_label($d['access']) . '</td>';
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

include $rootpath . 'includes/inc_footer.php';

function cancel($map = null)
{
	$params = [];

	if ($map)
	{
		$params['map'] = $map;
	}
	
	header('Location: ' . generate_url('docs', $params));
	exit;
}
