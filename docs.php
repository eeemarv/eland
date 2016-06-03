<?php

$rootpath = '';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';

$fa = 'files-o';

$mdb->connect();

$q = (isset($_GET['q'])) ? $_GET['q'] : '';
$del = (isset($_GET['del'])) ? $_GET['del'] : false;
$edit = (isset($_GET['edit'])) ? $_GET['edit'] : false;
$map = (isset($_GET['map'])) ? $_GET['map'] : false;
$map_edit = (isset($_GET['map_edit'])) ? $_GET['map_edit'] : false;
$add = (isset($_GET['add'])) ? true : false;

$submit = ($_POST['zend']) ? true : false;
$confirm_del = ($_POST['confirm_del']) ? true : false;

if ($post)
{
	$s3 = Aws\S3\S3Client::factory(array(
		'signature'	=> 'v4',
		'region'	=> 'eu-central-1',
		'version'	=> '2006-03-01',
	));
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
	$map = $mdb->docs->findOne(array('_id' => new MongoId($map_edit)));

	$map_name = $map['map_name'];

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

		if ($map_name = $_POST['map_name'])
		{
			$mdb->docs->update(array('_id' => new MongoId($map_edit)), array('map_name' => $map_name));
			$alert->success('Map naam aangepast.');

			invalidate_typeahead_thumbprint('doc_map_names');

			cancel($map_edit);
		}

		$alert->error('Geen map naam ingevuld!');
	}

	$includejs = '<script src="' . $cdn_typeahead . '"></script>
		<script src="' . $rootpath . 'js/typeahead.js"></script>';

	$h1 = 'Map aanpassen: ' . aphp('docs', 'map=' . $map_edit, $map_name);

	require_once $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info" id="add">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';

	echo '<div class="form-group">';
	echo '<label for="map_name" class="col-sm-2 control-label">Map naam</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="map_name" name="map_name" ';
	echo 'data-typeahead="' . get_typeahead('doc_map_names') . '" ';
	echo 'value="' . $map_name . '">';
	echo '</div>';
	echo '</div>';

	echo aphp('docs', '', 'Annuleren', 'btn btn-default') . '&nbsp;';
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
	$edit_id = new MongoId($edit);

	$doc = $mdb->docs->findOne(array('_id' => $edit_id));

	if (!$doc)
	{
		$alert->error('Document niet gevonden.');
		cancel();
	}

	if ($submit)
	{
		$update = array(
			'user_id'		=> $doc['user_id'],
			'filename'		=> $doc['filename'],
			'org_filename'	=> $doc['org_filename'],
			'ts'			=> $doc['ts'],
			'name'			=> $_POST['name'],
			'access'		=> (int) $_POST['access'],
		);

		if ($map_name = $_POST['map_name'])
		{
			$map = $mdb->docs->findOne(array('map_name' => $map_name));

			if (!$map)
			{
				$map = array('map_name' => $map_name, 'ts' => gmdate('Y-m-d H:i:s'));
				$mdb->docs->insert($map);

				invalidate_typeahead_thumbprint('doc_map_names');
			}

			$update['map_id'] = (string) $map['_id'];
		}

		if ($doc['map_id'] && $update['map_id'] != $doc['map_id'])
		{
			if (count(iterator_to_array($mdb->docs->find(array('map_id' => $doc['map_id'])))) == 1)
			{
				$mdb->docs->remove(array('_id' => new MongoId($doc['map_id'])));
			}
		}

		$mdb->docs->update(array('_id' => $edit_id), $update);

		$alert->success('Document aangepast');
		cancel($update['map_id']);
	}

	if ($map_id = $doc['map_id'])
	{
		$map = $mdb->docs->findOne(array('_id' => new MongoId($map_id)));
	}

	$includejs = '<script src="' . $cdn_typeahead . '"></script>
		<script src="' . $rootpath . 'js/typeahead.js"></script>';

	$h1 = 'Document aanpassen';

	require_once $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info" id="add">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';	

	echo '<div class="form-group">';
	echo '<label for="location" class="col-sm-2 control-label">Locatie</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="location" ';
	echo 'name="location" value="' . $s3_doc_url . $doc['filename'] . '" readonly>';
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

	echo '<div class="form-group">';
	echo '<label for="access" class="col-sm-2 control-label">Zichtbaarheid</label>';
	echo '<div class="col-sm-10">';
	echo '<select type="file" class="form-control" id="access" name="access" ';
	echo 'required>';
	render_select_options($access_options, $doc['access']);
	echo '</select>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="map_name" class="col-sm-2 control-label">Map (optioneel, creëer een nieuwe map of selecteer een bestaande)</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="map_name" name="map_name" value="' . $map['map_name'] . '" ';
	echo 'data-typeahead="' . get_typeahead('doc_map_names') . '">';
	echo '</div>';
	echo '</div>';

	echo aphp('docs', '', 'Annuleren', 'btn btn-default') . '&nbsp;';
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
	$doc_id = new MongoId($del);

	if ($error_token = get_error_form_token())
	{
		$alert->error($error_token);
		cancel();
	}

	if ($doc = $mdb->docs->findOne(array('_id' => $doc_id)))
	{
		$s3->deleteObject(array(
			'Bucket'	=> $s3_doc,
			'Key'		=> $doc['filename'],
		));

		if (count(iterator_to_array($mdb->docs->find(array('map_id' => $doc['map_id'])))) == 1)
		{
			$mdb->docs->remove(array('_id' => new MongoId($doc['map_id'])));
			unset($doc['map_id']);

			invalidate_typeahead_thumbprint('doc_map_names');
		}

		$mdb->docs->remove(
			array('_id' => $doc_id),
			array('justOne'	=> true)
		);

		$alert->success('Het document werd verwijderd.');
		cancel($doc['map_id']);
	}
	$alert->error('Document niet gevonden.');
}

if ($del)
{
	$doc_id = new MongoId($del);

	$doc = $mdb->docs->findOne(array('_id' => $doc_id));

	if ($doc)
	{
		$h1 = 'Document verwijderen?';

		require_once $rootpath . 'includes/inc_header.php';
		
		echo '<div class="panel panel-info">';
		echo '<div class="panel-heading">';
		echo '<form method="post">';

		echo '<p>';
		echo '<a href="' . $s3_doc_url . $doc['filename'] . '" target="_self">';
		echo ($doc['name']) ?: $doc['org_filename'];
		echo '</a>';
		echo '</p>';

		echo aphp('docs', '', 'Annuleren', 'btn btn-default') . '&nbsp;';
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

	$extension_types = array(
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
	);

	$media_type = (isset($extension_types[$ext])) ? $extension_types[$ext] : $file_type;

	if ($file_size > 1024 * 1024 * 10)
	{
		$errors[] = 'Het bestand is te groot. De maximum grootte is 10MB.';
	}

	if (!$file)
	{
		$errors[] = 'Geen bestand geselecteerd.';
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
		$access = $_POST['access'];

		$id_str = substr(sha1(time() . mt_rand(0, 1000000)), 0, 24);

		$doc_id = new MongoId($id_str);

		$filename = $schema . '_d_' . $doc_id . '.' . $ext;

		$doc = array(
			'_id' 			=> $doc_id,
			'filename'		=> $filename,
			'org_filename'	=> $file,
			'access'		=> (int) $access,
			'ts'			=> gmdate('Y-m-d H:i:s'),
			'user_id'		=> $s_id,
		);

		if ($map_name = $_POST['map_name'])
		{
			$m = $mdb->docs->findOne(array('map_name' => $map_name));

			$map_id = new MongoId($m['_id']);

			if (!$m)
			{
				$map = array(
					'_id'		=> $map_id,
					'ts'		=> gmdate('Y-m-d H:i:s'),
					'map_name'	=> $map_name,
				);

				$mdb->docs->insert($map);

				invalidate_typeahead_thumbprint('doc_map_names');
			}

			$doc['map_id'] = (string) $map_id;
		}

		if ($name = $_POST['name'])
		{
			$doc['name'] = $name;
		}

		$mdb->docs->insert($doc);

		$params = array(
			'CacheControl'			=> 'public, max-age=31536000',
			'ContentType'			=> $media_type,
		);

		$upload = $s3->upload($s3_doc, $filename, fopen($tmpfile, 'rb'), 'public-read', array(
			'params'	=> $params
		));

		$alert->success('Het bestand is opgeladen.');
		cancel($map_id);
	}
}

/**
 * add form
 */

if ($add)
{
	if ($map)
	{
		$map_id = new MongoId($map);
		$map_name = $mdb->docs->findOne(array('_id' => $map_id))['map_name'];
	}

	$includejs = '<script src="' . $cdn_typeahead . '"></script>
		<script src="' . $rootpath . 'js/typeahead.js"></script>';

	$top_buttons .= aphp('docs', '', 'Lijst', 'btn btn-default', 'Lijst', 'files-o', true);

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

	echo '<div class="form-group">';
	echo '<label for="access" class="col-sm-2 control-label">Zichtbaarheid</label>';
	echo '<div class="col-sm-10">';
	echo '<select type="file" class="form-control" id="access" name="access" ';
	echo 'required>';
	render_select_options($access_options, 0);
	echo '</select>';
	echo '</div>';
	echo '</div>';

	echo '<div class="form-group">';
	echo '<label for="map_name" class="col-sm-2 control-label">Map (optioneel, creëer een nieuwe map of selecteer een bestaande)</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="map_name" name="map_name" value="' . $map_name . '" ';
	echo 'data-typeahead="' . get_typeahead('doc_map_names') . '">';
	echo '</div>';
	echo '</div>';

	$map_context = ($map) ? 'map=' . $map : '';
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

$find = array(
	'access'	=> array('$gte'	=> $access_level)
);

if ($map)
{
	$map_name = $mdb->docs->findOne(array('_id' => new MongoId($map)));
	$map_name = $map_name['map_name'];

	if (!$map_name)
	{
		$alert->error('Onbestaande map id.');
		cancel();
	}

	$find['map_id'] = $map;
	$maps = array();
}
else
{
	$maps = iterator_to_array($mdb->docs->find(array('map_name' => array('$exists' => true))));
}

$docs = iterator_to_array($mdb->docs->find($find));

if (!$map)
{
	foreach ($docs as $k => $d)
	{
		if (isset($d['map_id']))
		{
			$maps[$d['map_id']]['count']++;
			unset($docs[$k]);
		}
	}
}

if ($s_admin)
{
	$and_map = ($map) ? '&map=' . $map : '';

	$top_buttons .= aphp('docs', 'add=1' . $and_map, 'Document opladen', 'btn btn-success', 'Document opladen', 'plus', true);

	if ($map)
	{
		$top_buttons .= aphp('docs', 'map_edit=' . $map, 'Map aanpassen', 'btn btn-primary', 'Map aanpassen', 'pencil', true);
	}
}
if ($map)
{
	$top_buttons .= aphp('docs', '', 'Lijst', 'btn btn-default', 'Lijst', 'files-o', true);
}

$h1 = aphp('docs', '', 'Documenten');
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
		if ($d['count'])
		{
			echo '<tr class="info">';
			echo '<td>';
			echo aphp('docs', 'map=' . $d['_id'], $d['map_name'] . ' (' . $d['count'] . ')');
			echo '</td>';

			if ($s_admin)
			{
				echo '<td>';
				echo aphp('docs', 'map_edit=' . $d['_id'], 'Aanpassen', 'btn btn-primary btn-xs', false, 'pencil');
				echo '</td>';
			}
			echo '</tr>';

			continue;
		}

		echo '</tr>';
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
		$access = $acc_ary[$d['access']];

		echo '<tr>';

		echo '<td>';
		echo '<a href="' . $s3_doc_url . $d['filename'] . '" target="_self">';
		echo ($d['name']) ?: $d['org_filename'];
		echo '</a>';
		echo '</td>';
		echo '<td>' . $d['ts'] . '</td>';

		if (!$s_guest)
		{
			echo '<td>';
			echo '<span class="label label-' . $access[1] . '">' . $access[0] . '</span>';
			echo '</td>';
		}

		if ($s_admin)
		{
			echo '<td>';
			echo aphp('docs', 'edit=' . $d['_id'], 'Aanpassen', 'btn btn-primary btn-xs', false, 'pencil');
			echo '&nbsp;';
			echo aphp('docs', 'del=' . $d['_id'], 'Verwijderen', 'btn btn-danger btn-xs', false, 'times');
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
	$map = ($map) ? 'map=' . $map : '';
	header('Location: ' . generate_url('docs', $map));
	exit;
}
