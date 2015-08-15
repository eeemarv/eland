<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$q = ($_GET['q']) ?: '';
$hsh = ($_GET['hsh']) ?: '';

$st = array(
	'all'		=> array(
		'lbl'	=> 'Alle',
	),
	'active'	=> array(
		'lbl'	=> 'Actief',
		'st'	=> 1,
		'hsh'	=> '58d267',
	),
	'leaving'	=> array(
		'lbl'	=> 'Uitstappers',
		'st'	=> 2,
		'hsh'	=> 'ea4d04',
		'cl'	=> 'danger',
	),
	'new'		=> array(
		'lbl'	=> 'Instappers',
		'st'	=> 3,
		'hsh'	=> 'e25b92',
		'cl'	=> 'success',
	),
	'inactive'	=> array(
		'lbl'	=> 'Inactief',
		'st'	=> 0,
		'hsh'	=> '79a240',
		'cl'	=> 'inactive',
	),
	'info-packet'	=> array(
		'lbl'	=> 'Info-pakket',
		'st'	=> 5,
		'hsh'	=> '2ed157',
		'cl'	=> 'warning',
	),
	'info-moment'	=> array(
		'lbl'	=> 'Info-moment',
		'st'	=> 6,
		'hsh'	=> '065878',
		'cl'	=> 'info',
	),
	'extern'	=> array(
		'lbl'	=> 'Extern',
		'st'	=> 7,
		'hsh'	=> '05306b',
		'cl'	=> 'extern',
	),
);

$status_ary = array(
	0 	=> 'inactive',
	1 	=> 'active',
	2 	=> 'leaving',
	3	=> 'new',
	5	=> 'info-packet',
	6	=> 'info-moment',
	7	=> 'extern',
);

$users = $db->GetArray('select * from users order by letscode asc');

$newusertreshold = time() - readconfigfromdb('newuserdays') * 86400;

$c_ary = $db->GetArray('SELECT tc.abbrev, c.id_user, c.value
	FROM contact c, type_contact tc
	WHERE tc.id = c.id_type_contact
		AND tc.abbrev IN (\'mail\', \'tel\', \'gsm\')');

$contacts = array();

foreach ($c_ary as $c)
{
	$contacts[$c['id_user']][$c['abbrev']][] = $c['value'];
}

$top_buttons = '<a href="' . $rootpath . 'users/edit.php?mode=new" class="btn btn-success"';
$top_buttons .= ' title="Gebruiker toevoegen"><i class="fa fa-plus"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

$top_buttons .= '<a href="' . $rootpath . 'users/saldomail.php" class="btn btn-default"';
$top_buttons .= ' title="Saldo mail aan/uitzetten"><i class="fa fa-envelope-o"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Saldo mail</span></a>';

$h1 = 'Gebruikers';
$fa = 'users';

$includejs = '<script src="' . $rootpath . 'js/combined_filter.js"></script>';

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

echo '<input type="hidden" value="" id="combined-filter">';
echo '<input type="hidden" value="' . $hsh . '" name="hsh" id="hsh">';
echo '</form>';

echo '</div>';
echo '</div>';

echo '<ul class="nav nav-tabs" id="nav-tabs">';

foreach ($st as $k => $s)
{
	$class_li = ($k == 'all') ? ' class="active"' : '';
	$class_a  = ($s['cl']) ?: 'white';
	echo '<li' . $class_li . '><a href="#" class="bg-' . $class_a . '" ';
	echo 'data-filter="' . (($s['hsh']) ?: '') . '">' . $s['lbl'] . '</a></li>';
}

echo '</ul>';
echo '<input type="hidden" value="" id="combined-filter">';

echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-striped table-hover footable"';
echo ' data-filter="#combined-filter" data-filter-minimum="1">';
echo '<thead>';

echo '<tr>';
echo '<th data-sort-initial="true">Code</th>';
echo '<th>Naam</th>';
echo '<th data-hide="phone">Rol</th>';
echo '<th data-hide="phone, tablet" data-sort-ignore="true">Tel</th>';
echo '<th data-hide="phone, tablet" data-sort-ignore="true">gsm</th>';
echo '<th data-hide="phone">Postc</th>';
echo '<th data-hide="phone, tablet" data-sort-ignore="true">Mail</th>';
echo '<th data-hide="phone">Saldo</th>';
echo '<th data-hide="all">Min</th>';
echo '<th data-hide="all">Max</th>';
echo '<th data-hide="all">Ingeschreven</th>';
echo '<th data-hide="all">Geactiveerd</th>';
echo '<th data-hide="all">Laatst aangepast</th>';
echo '<th data-hide="all">Laatst ingelogd</th>';
echo '<th data-hide="all">Profielfoto</th>';
echo '<th data-hide="all">Admin commentaar</th>';
echo '<th data-hide="all" data-sort-ignore="true">Aanpassen</th>';
echo '</tr>';

echo '</thead>';
echo '<tbody>';

foreach($users as $u)
{
	$id = $u['id'];

	$status_key = $status_ary[$u['status']];
	$status_key = ($status_key == 'active' && $newusertreshold < strtotime($u['adate'])) ? 'new' : $status_key;

	$hsh = ($st[$status_key]['hsh']) ?: '';
	$hsh .= ($status_key == 'leaving' || $status_key == 'new') ? $st['active']['hsh'] : '';

	$class = ($st[$status_key]['cl']) ? ' class="' . $st[$status_key]['cl'] . '"' : '';

	echo '<tr' . $class . '>';

	echo '<td>';
	echo '<a href="' . $rootpath . 'users/view.php?id=' .$id .'">';
	echo $u['letscode'];
	echo '</a></td>';

	echo '<td>';
	echo '<a href="' . $rootpath . 'users/view.php?id=' .$id .'">'.htmlspecialchars($u['fullname'],ENT_QUOTES);
	echo '</a></td>';

	echo '<td>';
	echo $u['accountrole'];
	echo '</td>';

	echo '<td data-value="' . $hsh . '">';
	echo render_contacts($contacts[$id]['tel']);
	echo '</td>';
	
	echo '<td>';
	echo render_contacts($contacts[$id]['gsm']);
	echo '</td>';
	
	echo '<td>' . $u['postcode'] . '</td>';
	
	echo '<td>';
	echo render_contacts($contacts[$id]['mail'], 'mail');
	echo '</td>';

	echo '<td>';
	$balance = $u['saldo'];
	$text_danger = ($balance < $u['minlimit'] || ($u['maxlimit'] != NULL && $balance > $u['maxlimit'])) ? 'text-danger ' : '';
	echo '<span class="' . $text_danger  . '">' . $balance . '</span>';
	echo '</td>';

	echo '<td>';
	echo '<span class="label label-danger">' . $u['minlimit'] . '</span>';
	echo '</td>';

	echo '<td>';
	echo '<span class="label label-success">' . $u['maxlimit'] . '</span>';
	echo '</td>';

	echo '<td>';
	echo $u['cdate'];
	echo '</td>';

	echo '<td>';
	echo $u['adate'];
	echo '</td>';

	echo '<td>';
	echo $u['mdate'];
	echo '</td>';

	echo '<td>';
	echo $u['lastlogin'];
	echo '</td>';
	
	echo '<td>';
	echo ($u['PictureFile']) ? 'Ja' : 'Nee';
	echo '</td>';

	echo '<td>';
	echo htmlspecialchars($u['admincomment'], ENT_QUOTES);
	echo '</td>';

	echo '<td>';
	echo '<a href="' . $rootpath . 'users/edit.php?mode=edit&id=' . $id . '" ';
	echo 'class="btn btn-primary btn-xs"><i class="fa fa-pencil"></i> Aanpassen</a>';
	echo '</td>';

	echo '</tr>';

}
echo '</tbody>';
echo '</table>';
echo '</div>';
echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';

function render_contacts($contacts, $abbrev = null)
{
	if (count($contacts))
	{
		end($contacts);
		$end = key($contacts);

		$f = ($abbrev == 'mail') ? '<a href="mailto:%1$s">%1$s</a>' : '%1$s';

		foreach ($contacts as $key => $contact)
		{
			echo sprintf($f, htmlspecialchars($contact, ENT_QUOTES));

			if ($key == $end)
			{
				break;
			}
			echo '<br>';
		}
	}
	else
	{
		echo '&nbsp;';
	}
}
