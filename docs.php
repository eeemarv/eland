<?php

$rootpath = '';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';

$fa = 'files-o';

$elas_mongo->connect();

$q = ($_GET['q']) ?: '';
$del = $_GET['del'];
$edit = $_GET['edit'];
$map = $_GET['map'];
$map_edit = $_GET['map_edit'];

$submit = ($_POST['zend']) ? true : false;
$confirm_del = ($_POST['confirm_del']) ? true : false;

$bucket = getenv('S3_BUCKET_DOC') ?: die('No "S3_BUCKET_DOC" env config var in found!');

if (!readconfigfromdb('docs_en'))
{
	$alert->error('De documenten pagina is niet ingeschakeld.');
	redirect_index();
}

if ($post)
{
	$s3 = Aws\S3\S3Client::factory(array(
		'signature'	=> 'v4',
		'region'	=> 'eu-central-1',
		'version'	=> '2006-03-01',
	));
}

if (($confirm_del || $submit || $edit || $del || $post || $map_edit) & !$s_admin)
{
	$alert->error('Je hebt onvoldoende rechten voor deze actie.');
	cancel();
}

if ($map_edit)
{
	$map = $elas_mongo->docs->findOne(array('_id' => new MongoId($map_edit)));

	$map_name = $map['map_name'];

	if (!$map_name)
	{
		$alert->error('Map niet gevonden.');
		cancel();
	}

	if ($submit)
	{
		if ($map_name = $_POST['map_name'])
		{
			$elas_mongo->docs->update(array('_id' => new MongoId($map_edit)), array('map_name' => $map_name));
			$alert->success('Map naam aangepast.');
			cancel($map_edit);
		}

		$alert->error('Geen map naam ingevuld!');
	}

	$h1 = 'Map aanpassen: ' . aphp('docs', 'map=' . $map_edit, $map_name);

	require_once $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info" id="add">';
	echo '<div class="panel-heading">';

	echo '<form method="post">';

	echo '<div class="form-group">';
	echo '<label for="map_name" class="col-sm-2 control-label">Map naam</label>';
	echo '<div class="col-sm-10">';
	echo '<input type="text" class="form-control" id="map_name" name="map_name" value="' . $map_name . '">';
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

if ($edit)
{
	$edit_id = new MongoId($edit);

	$doc = $elas_mongo->docs->findOne(array('_id' => $edit_id));

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
			$map = $elas_mongo->docs->findOne(array('map_name' => $map_name));

			if (!$map)
			{
				$map = array('map_name' => $map_name, 'ts' => gmdate('Y-m-d H:i:s'));
				$elas_mongo->docs->insert($map);
			}
			$update['map_id'] = (string) $map['_id'];
		}

		if ($doc['map_id'] && $update['map_id'] != $doc['map_id'])
		{
			if (count(iterator_to_array($elas_mongo->docs->find(array('map_id' => $doc['map_id'])))) == 1)
			{
				$elas_mongo->docs->remove(array('_id' => new MongoId($doc['map_id'])));
			}
		}

		$elas_mongo->docs->update(array('_id' => $edit_id), $update);

		$alert->success('Document aangepast');
		cancel($update['map_id']);
	}

	if ($map_id = $doc['map_id'])
	{
		$map = $elas_mongo->docs->findOne(array('_id' => new MongoId($map_id)));
	}

	$includejs = '<script src="' . $cdn_typeahead . '"></script>
		<script src="' . $rootpath . 'js/docs.js"></script>';

	$h1 = 'Document aanpassen';

	require_once $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-info" id="add">';
	echo '<div class="panel-heading">';

	echo '<form method="post" class="form-horizontal">';	

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
	echo '<input type="text" class="form-control" id="map_name" name="map_name" value="' . $map['map_name'] . '">';
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

if ($confirm_del && $del)
{
	$doc_id = new MongoId($del);

	if ($doc = $elas_mongo->docs->findOne(array('_id' => $doc_id)))
	{
		$s3->deleteObject(array(
			'Bucket'	=> $bucket,
			'Key'		=> $doc['filename'],
		));

		if (count(iterator_to_array($elas_mongo->docs->find(array('map_id' => $doc['map_id'])))) == 1)
		{
			$elas_mongo->docs->remove(array('_id' => new MongoId($doc['map_id'])));
		}

		$elas_mongo->docs->remove(
			array('_id' => $doc_id),
			array('justOne'	=> true)
		);

		$alert->success('Het document werd verwijderd.');
		cancel();
	}
	$alert->error('Document niet gevonden.');
}

if (isset($del))
{
	$doc_id = new MongoId($del);

	$doc = $elas_mongo->docs->findOne(array('_id' => $doc_id));

	if ($doc)
	{
		$h1 = 'Document verwijderen?';

		require_once $rootpath . 'includes/inc_header.php';
		
		echo '<div class="panel panel-info">';
		echo '<div class="panel-heading">';
		echo '<form method="post">';

		echo '<p>';
		echo '<a href="https://s3.eu-central-1.amazonaws.com/' . $bucket . '/' . $doc['filename'] . '" target="_self">';
		echo ($doc['name']) ?: $doc['org_filename'];
		echo '</a>';
		echo '</p>';

		echo aphp('docs', '', 'Annuleren', 'btn btn-default') . '&nbsp;';
		echo '<input type="submit" value="Verwijderen" name="confirm_del" class="btn btn-danger">';
		echo '</form>';

		echo '</div>';
		echo '</div>';

		require_once $rootpath . 'includes/inc_footer.php';
		exit;
	}

	$alert->error('Document niet gevonden.');
}

if ($submit)
{
	$tmpfile = $_FILES['file']['tmp_name'];
	$file = $_FILES['file']['name'];
	$file_size = $_FILES['file']['size'];
//	$type = $_FILES['file']['type'];
	$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$file_type = finfo_file($finfo, $tmpfile);
	finfo_close($finfo);

	$allowed_types = array(
		'application/pdf'			=> 1,
		'image/jpeg'				=> 1,
		'image/png'					=> 1,
		'image/gif'					=> 1,
		'image/bmp'					=> 1,
		'image/tiff'				=> 1,
		'image/svg+xml'				=> 1,
		'text/plain'				=> 1,
		'text/rtf'					=> 1,
		'text/css'					=> 1,
		'text/html'					=> 1,
		'text/markdown'				=> 1,
		'application/msword'		=> 1,
		'application/zip'			=> 1,
		'audio/mpeg'				=> 1,
		'application/x-gzip'		=> 1,
		'application/x-compressed'	=> 1,
		'application/zip'			=> 1,
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'			=> 1,
		'application/vnd.openxmlformats-officedocument.spreadsheetml.template'		=> 1,
		'application/vnd.openxmlformats-officedocument.presentationml.template'		=> 1,
		'application/vnd.openxmlformats-officedocument.presentationml.slideshow'	=> 1,
		'application/vnd.openxmlformats-officedocument.presentationml.presentation'	=> 1,
		'application/vnd.openxmlformats-officedocument.presentationml.slide'		=> 1,
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document'	=> 1,
		'application/vnd.openxmlformats-officedocument.wordprocessingml.template'	=> 1,
		'application/vnd.ms-excel.addin.macroEnabled.12'							=> 1,
		'application/vnd.ms-excel.sheet.binary.macroEnabled.12'						=> 1,
		'application/vnd.ms-excel'													=> 1,
		'application/vnd.ms-powerpoint'												=> 1,
	);

	if ($file_size > 1024 * 1024 * 10)
	{
		$alert->error('Het bestand is te groot. De maximum grootte is 10MB.');
	}
	else if (!$file)
	{
		$alert->error('Geen bestand geselecteerd.');
	}
	else if (!($token = $_POST['token']))
	{
		$alert->error('Een token ontbreekt.');
	}
	else if (!$redis->get($schema . '_d_' . $token))
	{
		$alert->error('Geen geldig token');
	}
	else
	{
		$redis->del($schema . '_d_' . $token);

		$access = $_POST['access'];

		$doc_id = new MongoId();

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
			$m = $elas_mongo->docs->findOne(array('map_name' => $map_name));

			$map_id = new MongoId($m['_id']);

			if (!$m)
			{
				$map = array(
					'_id'		=> $map_id,
					'ts'		=> gmdate('Y-m-d H:i:s'),
					'map_name'	=> $map_name,
				);

				$elas_mongo->docs->insert($map);
			}

			$doc['map_id'] = (string) $map_id;
		}

		if ($name = $_POST['name'])
		{
			$doc['name'] = $name;
		}

		$elas_mongo->docs->insert($doc);

		$params = array(
			'CacheControl'	=> 'public, max-age=31536000',
//			'ContentType'	=> $file_type,
		);


		if ($allowed_types[$file_type])
		{
			$params['ContentType'] = $file_type;
		}

		$upload = $s3->upload($bucket, $filename, fopen($tmpfile, 'rb'), 'public-read', array(
			'params'	=> $params
		));

		$alert->success('Het bestand is opgeladen.');
		cancel($map_id);
	}
}

$token = sha1(time() . mt_rand(0, 1000000));
$redis->set($schema . '_d_' . $token, '1');
$redis->expire($schema . '_d_' . $token, 3600);

$find = array(
	'access'	=> array('$gte'	=> $access_level)
);

if ($map)
{
	$map_name = $elas_mongo->docs->findOne(array('_id' => new MongoId($map)));
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
	$maps = iterator_to_array($elas_mongo->docs->find(array('map_name' => array('$exists' => true))));
}

$docs = iterator_to_array($elas_mongo->docs->find($find));

if (!$map)
{
	//$docs = array_merge($maps, $docs);

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
	$top_buttons .= '<a href="#add" class="btn btn-success" ';
	$top_buttons .= 'title="Document opladen"><i class="fa fa-plus"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Document opladen</span></a>';

	if ($map)
	{
		$top_buttons .= aphp('docs', 'map_edit=' . $map, 'Map aanpassen', 'btn btn-primary', 'Map aanpassen', 'pencil', true);
	}
}
if ($map)
{
	$top_buttons .= aphp('docs', '', 'Lijst', 'btn btn-default', 'Lijst', 'files-o', true);
}

$includejs = '<script src="' . $cdn_typeahead . '"></script>
	<script src="' . $rootpath . 'js/docs.js"></script>';

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
echo '<input type="text" class="form-control" id="q" name="q" value="' . $q . '">';
echo '</div>';
echo '</div>';
echo '</div>';
echo '</form>';

echo '</div>';
echo '</div>';

if (!$map)
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
	echo '<a href="https://s3.eu-central-1.amazonaws.com/' . $bucket . '/' . $d['filename'] . '" target="_self">';
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

if ($s_admin)
{
	echo '<h3><span class="label label-info">Admin</span> Nieuw document opladen</h3>';

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
	echo 'data-url="' . $rootpath . 'ajax/doc_map_names.php?' . get_session_query_param() . '">';
	echo '</div>';
	echo '</div>';

	echo '<input type="submit" name="zend" value="Document opladen" class="btn btn-success">';
	echo '<input type="hidden" value="' . $token . '" name="token">';

	echo '</form>';

	echo '</div>';
	echo '</div>';
}

include $rootpath . 'includes/inc_footer.php';

function cancel($map = null)
{
	$map = ($map) ? 'map=' . $map : '';
	header('Location: ' . generate_url('docs', $map));
	exit;
}
