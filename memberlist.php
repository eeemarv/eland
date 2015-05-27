<?php
ob_start();
$rootpath = "";
$role = 'guest';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_form.php");

$prefix = ($_GET["prefix"]) ?: 'ALL';
$posted_list["prefix"] = $prefix;
$searchname = $_GET["searchname"];

$sort = ($sort) ? $sort : 'letscode';

$query = "SELECT prefix, shortname FROM letsgroups WHERE apimethod ='internal' AND prefix IS NOT NULL";
$prefixes = $db->GetAssoc($query);
$prefixes['ALL'] = 'ALLE';

$h1 = 'Contactlijst';
$fa = 'users';

include $rootpath . 'includes/inc_header.php';

echo '<div class="pull-right hidden-xs">';
echo '<a href="print_memberlist.php?prefix_filterby=' .$prefix_filterby . '">';
echo '<i class="fa fa-print"></i>&nbsp;print</a>&nbsp;&nbsp;';
echo '<a href="' . $rootpath . 'csv_memberlist.php?prefix_filterby=' . $prefix_filterby;
echo '" target="new">';
echo '<i class="fa fa-file"></i>';
echo '&nbsp;csv</a>';
echo '</div>';

echo '<form method="GET" class="form-horizontal">';

echo '<div class="form-group">';
echo '<label for="prefix" class="col-sm-2 control-label">Groep</label>';
echo '<div class="col-sm-10">';
echo '<select class="form-control" id="prefix" name="prefix">'; 
render_select_options($prefixes, $prefix);
echo '</select>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="searchname" class="col-sm-2 control-label">Naam</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" name="searchname" value="' . $searchname . '" id="searchname" class="form-control">';
echo '</div>';
echo '</div>';

echo '<input type="submit" name="zend" value="Weergeven" class="btn btn-default">';

echo '</form>';

$query = 'SELECT * FROM users u
		WHERE status IN (1, 2, 3) 
		AND u.accountrole <> \'guest\' ';
if ($prefix_filterby <> 'ALL')
{
	 $query .= 'AND u.letscode like \'' . $prefix_filterby .'%\' ';
}
if(!empty($searchname))
{
	$query .= 'AND (LOWER(u.fullname) like \'%' .strtolower($searchname) . '%\'
		OR LOWER(u.name) like \'%' .strtolower($searchname) . '%\') ';
}

$userrows = $db->GetArray($query);

$newusertreshold = time() - readconfigfromdb('newuserdays') * 86400;

$query = 'SELECT tc.abbrev, c.id_user, c.value
	FROM contact c, type_contact tc, users u
	WHERE tc.id = c.id_type_contact
		AND tc.abbrev IN (\'mail\', \'tel\', \'gsm\')
		AND u.id = c.id_user
		AND u.status IN (1, 2, 3)
		AND c.flag_public = 1';
$c_ary = $db->GetArray($query);

$contacts = array();

foreach ($c_ary as $c)
{
	$contacts[$c['id_user']][$c['abbrev']][] = $c['value'];
}

//show table
echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-striped table-hover footable">';
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

foreach($userrows as $value)
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
	echo "</td>";
	echo "<td>";
	echo render_contacts($contacts[$value['id']]['gsm']);
	echo "</td>";
	echo "<td>".$value["postcode"]."</td>";
	echo "<td>";
	echo render_contacts($contacts[$value['id']]['mail'], 'mail');
	echo "</td>";

	echo "<td align='right'>";
	$balance = $value["saldo"];
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
