<?php

set_time_limit(60);

$app['page_access'] = 'admin';
require_once __DIR__ . '/include/web.php';

$db_elas = isset($_GET['db_elas']);
$db_eland_aggs = !$db_elas && isset($_GET['db_eland_aggs']);
$db_eland_events = !$db_eland_aggs && isset($_GET['db_eland_events']);
$db_download = $db_elas || $db_eland_aggs || $db_eland_events;

$exec_en = function_exists('exec');

$export_ary = [
	'users'		=> [
		'label'		=> 'Gebruikers',
		'sql'		=> 'select *
			from ' . $app['tschema'] . '.users
			order by letscode',
		'columns'	=> [
			'letscode',
			'cdate',
			'comments',
			'hobbies',
			'name',
			'postcode',
			'login',
			'mailinglist',
			'password',
			'accountrole',
			'status',
			'lastlogin',
			'minlimit',
			'maxlimit',
			'fullname',
			'admincomment',
			'adate' => 'activeringsdatum'
		],
	],
	'contacts'	=> [
		'label'	=> 'Contactgegevens',
		'sql'	=> 'select c.*, tc.abbrev, u.letscode, u.name
			from ' . $app['tschema'] . '.contact c, ' .
				$app['tschema'] . '.type_contact tc, ' .
				$app['tschema'] . '.users u
			where c.id_type_contact = tc.id
				and c.id_user = u.id',
		'columns'	=> [
			'letscode',
			'username',
			'abbrev',
			'comments',
			'value',
			'flag_public',
		],
	],
	'categories'	=> [
		'label'		=> 'Categorieën',
		'sql'		=> 'select * from ' . $app['tschema'] . '.categories',
		'columns'	=> [
			'name',
			'id_parent',
			'description',
			'cdate',
			'fullname',
			'leafnote',
		],
	],
	'messages'	=> [
		'label'		=> 'Vraag en Aanbod',
		'sql'		=> 'select m.*, u.name as username, u.letscode
			from ' . $app['tschema'] . '.messages m, ' .
				$app['tschema'] . '.users u
			where m.id_user = u.id
				and validity > ?',
		'sql_bind'	=> [gmdate('Y-m-d H:i:s')],
		'columns'	=> [
			'letscode',
			'username',
			'cdate',
			'validity',
			'content',
			'msg_type',
		],
	],
	'transactions'	=> [
		'label'		=> 'Transacties',
		'sql'		=> 'select t.transid, t.description,
							concat(fu.letscode, \' \', fu.name) as from_user,
							concat(tu.letscode, \' \', tu.name) as to_user,
							t.cdate, t.real_from, t.real_to, t.amount
						from ' . $app['tschema'] . '.transactions t, ' .
							$app['tschema'] . '.users fu, ' .
							$app['tschema'] . '.users tu
						where t.id_to = tu.id
							and t.id_from = fu.id
						order by t.date desc',
		'columns'	=> [
			'cdate'			=> 'Datum',
			'from_user'		=> 'Van',
			'real_from'		=> 'interSysteem',
			'to_user'		=> 'Aan',
			'real_to'		=> 'interSysteem',
			'amount'		=> 'Bedrag',
			'description'	=> 'Dienst',
			'transid'		=> 'transactie id',
		],
	],
];

$buttons = '';
$r = "\r\n";

if ($exec_en && $db_download)
{
	$filename = $app['tschema'] . '-';
	$filename .= $db_elas ? 'elas-db' : 'eland-xdb';
	$filename .= $db_eland_aggs ? '-aggs' : '';
	$filename .= $db_eland_events ? '-events' : '';
	$filename .= gmdate('-Y-m-d-H-i-s-');
	$filename .= substr(sha1(microtime()), 0, 4);
	$filename .= '.';
	$filename .= $db_elas ? 'sql' : 'csv';

	if ($db_elas)
	{
		$exec = 'pg_dump --dbname=';
		$exec .= getenv('DATABASE_URL');
		$exec .= ' --schema=' . $app['tschema'];
		$exec .= ' --no-owner --no-acl > ' . $filename;
	}
	else
	{
		$exec = 'psql -d ';
		$exec .= getenv('DATABASE_URL');
		$exec .= ' -c "\\copy ';
		$exec .= '(select * ';
		$exec .= 'from xdb.';
		$exec .= $db_eland_aggs ? 'aggs' : 'events';
		$exec .= ' where agg_schema = \'';
		$exec .= $app['tschema'] . '\')';
		$exec .= ' TO ' . $filename;
		$exec .= ' with delimiter \',\' ';
		$exec .= 'csv header;"';
	}

	exec($exec);

	header('Content-disposition: attachment; filename=' . $filename);
	header('Content-Type: application/force-download');
	header('Content-Transfer-Encoding: binary');
	header('Pragma: no-cache');
	header('Expires: 0');

	$handle = fopen($filename, 'rb');

	if (!$handle)
	{
		exit;
	}

	while (!feof($handle))
	{
		echo fread($handle, 8192);
	}

	fclose($handle);

	unlink($filename);

	$download_log = $db_elas ? 'elas db sql' : 'eland xdb csv ';
	$download_log .= $db_eland_aggs ? 'aggs' : '';
	$download_log .= $db_eland_events ? 'events' : '';

	$app['monolog']->info($download_log . ' downloaded',
		['schema' => $app['tschema']]);

	exit;
}

foreach ($export_ary as $ex_key => $export)
{
	if (isset($_GET[$ex_key]))
	{
		$columns = $fields = [];

		$sql_bind = $export['sql_bind'] ?? [];

		$data = $app['db']->fetchAll($export['sql'], $sql_bind);

		foreach($export['columns'] as $key => $name)
		{
			$fields[] = $name;

			$columns[] = (ctype_digit((string) $key)) ? $name : $key;
		}

		$out = '"' . implode('","', $fields) . '"' . $r;

		foreach($data as $row)
		{
			$fields = [];

			foreach($columns as $c)
			{
				$fields[] = $row[$c] ?? '';
			}

			$out .= '"' . implode('","', $fields) . '"' . $r;
		}

		header('Content-disposition: attachment; filename=elas-' . $ex_key . '-'.date('Y-m-d-H-i-S').'.csv');
		header('Content-Type: application/force-download');
		header('Content-Transfer-Encoding: binary');
		header('Pragma: no-cache');
		header('Expires: 0');

		echo $out;

		$app['monolog']->info('csv ' . $ex_key . ' exported.',
			['schema' => $app['tschema']]);

		exit;
	}

	$buttons .= '<form><input type="submit" name="' . $ex_key . '" ';
	$buttons .= 'value="' . $export['label'] . '" class="btn btn-default margin-bottom">';
	$buttons .= '<input type="hidden" value="admin" name="r">';
	$buttons .= '<input type="hidden" value="' . $app['s_id'] . '" name="u">';
	$buttons .= '</form>';
}

$app['h1']->add('Export');
$app['h1']->fa('download');

include __DIR__ . '/include/header.php';

if ($exec_en)
{
	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';
	echo '<h3>eLAS database download (SQL)';
	echo '</h3>';
	echo '</div>';
	echo '<div class="panel-heading">';

	echo '<form>';
	echo '<input type="submit" value="Download" name="db_elas" class="btn btn-default margin-bottom">';
	echo '<input type="hidden" value="admin" name="r">';
	echo '<input type="hidden" value="';
	echo $app['s_id'];
	echo '" name="u">';
	echo '</form>';

	echo '</div></div>';

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';
	echo '<h3>eLAND extra data (CSV)';
	echo '</h3>';
	echo '</div>';
	echo '<div class="panel-heading">';
	echo '<p>';
	echo 'Naast de eLAS database bevat eLAND nog ';
	echo 'deze extra data die je hier kan downloaden ';
	echo 'als csv-file. ';
	echo '"Data" bevat de huidige staat en "Events" de ';
	echo 'gebeurtenissen die de huidige staat veroorzaakt hebben.';
	echo '</p>';
	echo '</div>';
	echo '<div class="panel-heading">';

	echo '<form>';
	echo '<input type="submit" value="Download Data" ';
	echo 'name="db_eland_aggs" ';
	echo 'class="btn btn-default margin-bottom">';
	echo '&nbsp;';
	echo '<input type="submit" value="Download Events" ';
	echo 'name="db_eland_events" ';
	echo 'class="btn btn-default margin-bottom">';
	echo '<input type="hidden" value="admin" name="r">';
	echo '<input type="hidden" value="';
	echo $app['s_id'];
	echo '" name="u">';
	echo '</form>';

	echo '</div></div>';
}

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';
echo '<h3>eLAS Csv export</h3>';
echo '</div>';
echo '<div class="panel-heading">';

echo $buttons;

echo '</div></div>';

include __DIR__ . '/include/footer.php';
