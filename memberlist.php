<?php
ob_start();
$rootpath = '';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';

$filter = $_GET['filter'];

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

$h1 = 'Contactlijst';
$fa = 'users';

$top_right = '<a href="print_memberlist.php';
$top_right .= '">';
$top_right .= '<i class="fa fa-print"></i>&nbsp;print</a>&nbsp;&nbsp;';
$top_right .= '<a href="' . $rootpath . 'csv_memberlist.php';
$top_right .= '" target="new">';
$top_right .= '<i class="fa fa-file"></i>';
$top_right .= '&nbsp;csv</a>';

$includejs = '<script src="' . $rootpath . 'js/fooprefilter.js"></script>';

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
echo '<input type="text" class="form-control" id="filter" name="filter" value="' . $filter . '">';
echo '</div>';
echo '</div>';
echo '</div>';
echo '</form>';

echo '</div>';
echo '</div>';

//show table
echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-striped table-hover footable"';
echo ' data-filter="#filter" data-filter-minimum="1">';
echo '<thead>';

echo '<tr>';
echo '<th data-sort-initial="true">Code</th>';
echo '<th data-filter="#filter">Naam</th>';
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

	$class = ($newusertreshold < strtotime($value['adate'])) ? ' class="success"' : '';
	$class = ($value["status"] == 2) ? ' class="danger"' : $class;

	echo '<tr' . $class . '>';

	echo '<td>';
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

// active legend
/*
echo '<table class="table">';
echo "<tr>";
echo "<td bgcolor='#B9DC2E'><font color='white'>";
echo "<strong>Groen blokje:</strong></font></td><td> Instapper<br>";
echo "</tr>";
echo "<tr>";
echo "<td bgcolor='#f56db5'><font color='white'>";
echo "<strong>Rood blokje:</strong></font></td><td>Uitstapper<br>";
echo "</tr>";
echo "</tr></table>";
*/

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
