<?php

$rootpath = './';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$export_ary = array(
	'users'		=> array(
		'label'		=> 'Gebruikers',
		'sql'		=> 'select * from users order by letscode',
		'columns'	=> array(
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
		),
	),
	'contacts'	=> array(
		'label'	=> 'Contactgegevens',
		'sql'	=> 'select c.*, tc.abbrev, u.letscode, u.name
			from contact c, type_contact tc, users u
			where c.id_type_contact = tc.id
				and c.id_user = u.id',
		'columns'	=> array(
			'letscode',
			'username',
			'abbrev',
			'comments',
			'value',
			'flag_public',
		),
	),
	'categories'	=> array(
		'label'		=> 'Categorieën',
		'sql'		=> 'select * from categories',
		'columns'	=> array(
			'name',
			'id_parent',
			'description',
			'cdate',
			'fullname',
			'leafnote',
		),
	),
	'messages'	=> array(
		'label'		=> 'Vraag en Aanbod',
		'sql'		=> 'select m.*, u.name as username, u.letscode
			from messages m, users u
			where m.id_user = u.id
				and validity > ?',
		'sql_bind'	=> array(gmdate('Y-m-d H:i:s')),
		'columns'	=> array(
			'letscode',
			'username',
			'cdate',
			'validity',
			'content',
			'msg_type',
		),
	),
	'transactions'	=> array(
		'label'		=> 'Transacties',
		'sql'		=> 'select t.transid, t.description,
							concat(fu.letscode, \' \', fu.name) as from_user,
							concat(tu.letscode, \' \', tu.name) as to_user,
							t.cdate, t.real_from, t.real_to, t.amount
						from transactions t, users fu, users tu
						where t.id_to = tu.id
							and t.id_from = fu.id
						order by t.date desc',
		'columns'	=> array(
			'cdate'			=> 'Datum',
			'from_user'		=> 'Van',
			'real_from'		=> 'interlets',
			'to_user'		=> 'Aan',
			'real_to'		=> 'interlets',
			'amount'		=> 'Bedrag',
			'description'	=> 'Dienst',
			'transid'		=> 'transactie id',
		),
	),
);

$buttons = '';
$r = "\r\n";

foreach ($export_ary as $ex_key => $export)
{
	if ($_GET[$ex_key])
	{
		$columns = $fields = array();

		$data = $db->fetchAll($export['sql'], $export['sql_bind'] ?: array());

		foreach($export['columns'] as $key => $name)
		{
			$fields[] = $name;

			$columns[] = (ctype_digit((string) $key)) ? $name : $key;
		}

		$out = '"' . implode('","', $fields) . '"' . $r;

		foreach($data as $row)
		{
			$fields = array();

			foreach($columns as $c)
			{
				$fields[] = $row[$c];
			}

			$out .= '"' . implode('","', $fields) . '"' . $r;
		}

		header('Content-disposition: attachment; filename=elas-' . $ex_key . '-'.date('Y-m-d').'.csv');
		header('Content-Type: application/force-download');
		header('Content-Transfer-Encoding: binary');
		header('Pragma: no-cache');
		header('Expires: 0');

		echo $out;
		exit;
	}

	$buttons .= '<form><input type="submit" name="' . $ex_key . '" ';
	$buttons .= 'value="' . $export['label'] . '" class="btn btn-default margin-bottom">';
	$buttons .= '</form>';
}

$h1 = 'Export';
$fa = 'download';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-default">';
echo '<div class="panel-heading">';

echo $buttons;

echo '</div></div>';

include $rootpath . 'includes/inc_footer.php';
