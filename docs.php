<?php
ob_start();
$rootpath = '';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';

$q = ($_GET['q']) ?: '';

$bucket = getenv('S3_BUCKET_DOC') ?: die('No "S3_BUCKET_DOC" env config var in found!');

if ($_POST['zend'])
{
	$s3 = Aws\S3\S3Client::factory(array(
		'signature'	=> 'v4',
		'region'	=> 'eu-central-1',
		'version'	=> '2006-03-01',
	));

	$tmpfile = $_FILES['file']['tmp_name'];
	$file = $_FILES['file']['name'];
	$file_size = $_FILES['file']['size'];
	$type = $_FILES['file']['type'];
	$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

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

		$mid = new MongoId();

		$filename = $schema . '_d_' . $mid . '_' . sha1(time() . mt_rand(0, 1000000)) . '.' . $ext;

		$elas_mongo->connect();

		$doc = array(
			'_id' 			=> $mid,
			'filename'		=> $filename,
			'org_filename'	=> $file,
			'access'		=> $access,
			'time'			=> gmdate('Y-m-d H:i:s'),
		);

		if ($name = $_POST['name'])
		{
			$doc['name'] = $name;
		}

		$elas_mongo->docs->insert($doc);

		$upload = $s3->upload($bucket, $filename, fopen($tmpfile, 'rb'), 'public-read', array(
			'params'	=> array(
				'CacheControl'	=> 'public, max-age=31536000',
				'ContentType'	=> $type,
			),
		));

		$alert->success('Het bestand is opgeladen.');
		header('Location: ' . $rootpath . 'docs.php');
		exit;
	}

}

$token = sha1(time() . mt_rand(0, 1000000));
$redis->set($schema . '_d_' . $token, '1');
$redis->expire($schema . '_d_' . $token, 3600);

$elas_mongo->connect();
$docs = $elas_mongo->docs->find();

$h1 = 'Documenten';
$fa = 'files-o';

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

//show table
echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-striped table-hover footable"';
echo ' data-filter="#q" data-filter-minimum="1">';
echo '<thead>';

echo '<tr>';
echo '<th data-sort-initial="true">Naam</th>';
echo '</tr>';

echo '</thead>';
echo '<tbody>';

foreach($docs as $val)
{
	echo '<tr>';

	echo '<td>';
	echo '<a href="https://s3.eu-central-1.amazonaws.com/' . $bucket . '/' . $val['filename'] . '" target="_self">';
	echo ($val['name']) ?: $val['org_filename'];
	echo '</a>';
	echo '</td>';

	echo '</tr>';

}
echo '</tbody>';
echo '</table>';

echo '<form method="post" class="form-horizontal" enctype="multipart/form-data">';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

/*
echo '<div class="form-group">';
echo '<label for="id_type_contact" class="col-sm-2 control-label">Type</label>';
echo '<div class="col-sm-10">';
echo '<select name="id_type_contact" id="id_type_contact" class="form-control" required>';
render_select_options($tc, $contact['id_type_contact']);
echo "</select>";
echo '</div>';
echo '</div>';
*/
echo '<h3><span class="label label-default">Admin</span> Nieuw document opladen</h3>';

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
echo '<label for="access" class="col-sm-2 control-label">Zichtbaar</label>';
echo '<div class="col-sm-10">';
echo '<select type="file" class="form-control" id="access" name="access" ';
echo 'required>';
echo '<option value="0">Admin</option>';
echo '<option value="1">Leden</option>';
echo '<option value="2">Interlets</option>';
echo '</select>';
echo '</div>';
echo '</div>';

echo '<input type="submit" name="zend" value="Opladen" class="btn btn-default">';
echo '<input type="hidden" value="' . $token . '" name="token">';

echo '</div>';
echo '</div>';
echo '</form>';

echo '</div>';
echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';

