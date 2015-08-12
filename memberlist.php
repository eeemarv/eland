<?php
ob_start();
$rootpath = '';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';

$q = ($_GET['q']) ?: '';
$hsh = ($_GET['hsh']) ?: '';

$users = $db->GetArray('SELECT * FROM users u
		WHERE status IN (1, 2, 3) 
		AND u.accountrole <> \'guest\'');

$newusertreshold = time() - readconfigfromdb('newuserdays') * 86400;

$c_ary = $db->GetArray('SELECT tc.abbrev, c.id_user, c.value
	FROM contact c, type_contact tc, users u
	WHERE tc.id = c.id_type_contact
		AND tc.abbrev IN (\'mail\', \'tel\', \'gsm\')
		AND u.id = c.id_user
		AND u.status IN (1, 2, 3)
		AND c.flag_public = 1');

$contacts = array();

foreach ($c_ary as $c)
{
	$contacts[$c['id_user']][$c['abbrev']][] = $c['value'];
}

$top_buttons = '';

if (in_array($s_accountrole, array('admin', 'user')))
{
	$top_buttons .= '<a href="' . $rootpath . 'userdetails/mydetails.php" class="btn btn-default"';
	$top_buttons .= ' title="Mijn gegevens"><i class="fa fa-user"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Mijn gegevens</span></a>';
}
if ($s_accountrole == 'admin')
{
	$top_buttons .= '<a href="' . $rootpath . 'users/overview.php" class="btn btn-default"';
	$top_buttons .= ' title="Beheer gebruikers"><i class="fa fa-cog"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Admin</span></a>';
}

$h1 = 'Contactlijst';
$fa = 'users';

if (in_array($s_accountrole, array('admin', 'user')))
{
	$top_right = '<a href="print_memberlist.php';
	$top_right .= '">';
	$top_right .= '<i class="fa fa-print"></i>&nbsp;print</a>&nbsp;&nbsp;';
	$top_right .= '<a href="' . $rootpath . 'csv_memberlist.php';
	$top_right .= '" target="new">';
	$top_right .= '<i class="fa fa-file"></i>';
	$top_right .= '&nbsp;csv</a>';
}

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
echo '<li class="active"><a href="#" class="bg-white" data-filter="">Alle</a></li>';
echo '<li><a href="#" class="bg-success" data-filter="6a501bbf">Instappers</a></li>';
echo '<li><a href="#" class="bg-danger" data-filter="51505c3e">Uitstappers</a></li>';
echo '</ul>';

//show table
echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-striped table-hover footable"';
echo ' data-filter="#combined-filter" data-filter-minimum="1">';
echo '<thead>';

echo '<tr>';
echo '<th data-sort-initial="true">Code</th>';
echo '<th>Naam</th>';
echo '<th data-hide="phone, tablet" data-sort-ignore="true">Tel</th>';
echo '<th data-hide="phone, tablet" data-sort-ignore="true">gsm</th>';
echo '<th data-hide="phone">Postc</th>';
echo '<th data-hide="phone, tablet" data-sort-ignore="true">Mail</th>';
echo '<th data-hide="phone">Saldo</th>';
echo '</tr>';

echo '</thead>';
echo '<tbody>';

foreach($users as $value)
{
	$id = $value['id'];

	$class = $status_filter = '';

	if ($value['status'] == 2)
	{
		$status_filter = '51505c3e';
		$class = ' class="danger"';
	}
	else if ($newusertreshold < strtotime($value['adate']))
	{
		$status_filter = '6a501bbf';
		$class = ' class="success"';
	}

	echo '<tr' . $class . '>';

	echo '<td data-value="' . $status_filter . '">';
	echo '<a href="memberlist_view.php?id=' .$id .'">';
	echo $value['letscode'];
	echo '</a></td>';
	
	echo '<td>';
	echo '<a href="memberlist_view.php?id=' .$id .'">'.htmlspecialchars($value['fullname'],ENT_QUOTES).'</a></td>';
	echo '<td>';
	echo render_contacts($contacts[$value['id']]['tel']);
	echo '</td>';
	echo '<td>';
	echo render_contacts($contacts[$value['id']]['gsm']);
	echo '</td>';
	echo '<td>' . $value['postcode'] . '</td>';
	echo '<td>';
	echo render_contacts($contacts[$value['id']]['mail'], 'mail');
	echo '</td>';

	echo '<td>';
	$balance = $value['saldo'];
	if($balance < $value['minlimit'] || ($value['maxlimit'] != NULL && $balance > $value['maxlimit']))
	{
		echo '<span class="text-danger">' . $balance . '</span>';
	}
	else
	{
		echo $balance;
	}

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
