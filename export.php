<?php

set_time_limit(60);

$page_access = 'admin';
require_once __DIR__ . '/include/web.php';

$export_ary = [
	'users'		=> [
		'label'		=> 'Gebruikers',
		'sql'		=> 'select * from users order by letscode',
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
			from contact c, type_contact tc, users u
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
		'sql'		=> 'select * from categories',
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
			from messages m, users u
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
						from transactions t, users fu, users tu
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

foreach ($export_ary as $ex_key => $export)
{
	if (isset($_GET['db']) && function_exists('exec'))
	{
		$schema = $app['this_group']->get_schema();
		$filename = $schema . '-elas-db-' . date('Y-m-d-H-i-s') . '-' . substr(sha1(microtime()), 0, 8) . '.sql';

		exec('pg_dump --dbname=' . getenv('DATABASE_URL') .' --schema=' . $schema . ' --no-owner --no-acl > ' . $filename);

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

		$app['monolog']->info('db downloaded');

		exit;
	}

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

		$app['monolog']->info('csv ' . $ex_key . ' exported.');

		exit;
	}

	$buttons .= '<form><input type="submit" name="' . $ex_key . '" ';
	$buttons .= 'value="' . $export['label'] . '" class="btn btn-default margin-bottom">';
	$buttons .= '<input type="hidden" value="admin" name="r">';
	$buttons .= '<input type="hidden" value="' . $s_id . '" name="u">';
	$buttons .= '</form>';
}

$h1 = 'Export';
$fa = 'download';

include __DIR__ . '/include/header.php';


if (function_exists('exec'))
{
	echo '<div class="panel panel-default">';
	echo '<div class="panel-heading">';
	echo '<h3>eLAS database download (SQL)';
	echo '</h3>';
	echo '</div>';
	echo '<div class="panel-heading">';

	echo '<form>';
	echo '<input type="submit" value="Download" name="db" class="btn btn-default margin-bottom">';
	echo '<input type="hidden" value="admin" name="r">';
	echo '<input type="hidden" value="' . $s_id . '" name="u">';
	echo '</form>';

	echo '</div></div>';
}


echo '<div class="panel panel-default">';
echo '<div class="panel-heading">';
echo '<h3>eLAS Csv export</h3>';
echo '</div>';
echo '<div class="panel-heading">';

echo $buttons;

echo '</div></div>';

include __DIR__ . '/include/footer.php';
