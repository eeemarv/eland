<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';

//status 0: inactief
//status 1: letser
//status 2: uitstapper
//status 3: instapper
//status 4: secretariaat
//status 5: infopakket
//status 6: stapin
//status 7: extern

$status_ary = array(
	0 	=> 'inactief',
	1 	=> 'actief',
	2 	=> 'uitstapper',
	3	=> 'instapper',		// not used
	4	=> 'secretariaat',	// not used
	5	=> 'info pakket',
	6	=> 'info moment',
	7	=> 'extern',
);

$status_class = array(
	0	=> 'black',
	1	=> '',
	2	=> 'danger',
	3	=> 'success',
	4	=> '',
	5	=> 'warning',
	6	=> 'info',
	7	=> 'inactif',
);
	

/*
$orderby = ($_GET["orderby"]) ? $_GET['orderby'] : 'letscode';

$query = "SELECT * FROM users WHERE status IN (1, 2, 3, 4)
	ORDER BY ".$orderby;
$active_users = $db->GetArray($query);
*/

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


include $rootpath . 'includes/inc_header.php';

echo '<h1><span class="label label-danger">Admin</span> Gebruikers</h1>';

// active legend
/*
echo "<table>";
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
//

echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-striped table-hover footable">';
echo '<thead>';

echo '<tr>';
echo '<th data-sort-initial="true">Code</th>';
echo '<th>Naam</th>';
echo '<th data-hide="phone">Rol</th>';
echo '<th data-hide="phone">Status</th>';
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
echo '<th data-hide="all" data-sort-ignore="true">Aanpassen</th>';
echo '</tr>';

echo '</thead>';
echo '<tbody>';

foreach($users as $u)
{
	$id = $u['id'];
	$status = $u['status'];
	$new_user = ($newusertreshold < strtotime($u['adate'])) ? true : false;
	$class = ($new_user && $status == 1) ? 'success' : '';
	$class = ($status_class[$u['status']]) ?: $class;
	$class = ($class) ? ' class="' . $class . '"' : '';

	echo '<tr' . $class . '>';

	echo '<td>';
	echo '<a href="memberlist_view.php?id=' .$id .'">';
	echo $u['letscode'];
	echo '</a></td>';
	
	echo '<td>';
	echo '<a href="memberlist_view.php?id=' .$id .'">'.htmlspecialchars($u['fullname'],ENT_QUOTES);
	echo '</a></td>';

	echo '<td>';
	echo $u['accountrole'];
	echo '</td>';

	echo '<td>';
	echo $status_ary[$u['status']];
	echo '</td>';
	
	echo '<td>';
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
	echo '<span class="' . $text_danger  . 'label label-default">' . $balance . '</span>';
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
	echo $u['logdate'];
	echo '</td>';
	
	echo '<td>';
	echo ($u['PictureFile']) ? 'Ja' : 'Nee';
	echo '</td>';

	echo '<td>';
	echo '<a href="' . $rootpath . 'users/edit.php?mode=edit&id=' . $id . '" class="btn btn-default btn-xs">Aanpassen</a>';
	echo '</td>';
		
	echo '</tr>';

}
echo '</tbody>';
echo '</table>';
echo '</div>';
echo '</div>';
echo '</div>';





echo "<div class='border_b'><table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
echo "<tr class='header'>";
echo "<td valign='top'><strong>";
echo "<a href='overview.php?user_orderby=letscode'>Code</a>";
echo "</strong></td>";
echo "<td valign='top'><strong>";
echo "<a href='overview.php?user_orderby=fullname'>Naam</a>";
echo "</strong></td>";
echo "<td valign='top'><strong>";
echo "<a href='overview.php?user_orderby=accountrole'>Rol</a>";
echo "</strong></td>";
//
echo "<td><strong>Tel</strong></td>\n";
echo "<td><strong>gsm</strong></td>\n";
//
echo "<td valign='top'><strong>";
echo "<a href='overview.php?user_orderby=postcode'>Postc</a>";
echo "</strong></td>";
echo "<td valign='top'><strong>";
echo "Mail";
echo "</strong></td>";
echo "<td valign='top'><strong>";
echo "Stand";
echo "</strong></td>";
echo "</tr>\n\n";
$rownumb=0;
$newusertreshold = time() - readconfigfromdb('newuserdays') * 86400;
foreach($active_users as $value)
{
	$myurl = "view.php?id=".$value["id"];
	$rownumb++;
	echo "<tr";
	if($rownumb % 2)
	{
		echo " class='uneven_row'";
	}
	else
	{
		echo " class='even_row'";
	}
	echo ">";

	$letscode = ($value['letscode']) ?: '<i>* leeg *</i>';

	if($value["status"] == 2)
	{
		echo "<td nowrap valign='top' bgcolor='#f475b6'><font color='white' ><strong>";
		echo "<a href='$myurl'>" . $letscode ."</a>";
		echo "</strong></font>";
	}
	else if ($newusertreshold < strtotime($value['adate']))
	{
		echo "<td nowrap valign='top' bgcolor='#B9DC2E'><font color='white'><strong>";
		echo "<a href='$myurl'>" . $letscode ."</a>";
		echo "</strong></font>";
	}
	else
	{
		echo "<td nowrap valign='top'>";
		echo "<a href='$myurl'>" . $letscode ."</a>";
	}

	echo "</td>\n";

	$fullname = ($value['fullname']) ? htmlspecialchars($value['fullname'], ENT_QUOTES) : '<i>* leeg *</i>';

	echo "<td nowrap valign='top'>";
	echo "<a href='$myurl'>". $fullname ."</a>";
	echo "</td>\n";

	echo "<td nowrap valign='top'>";
	echo $value["accountrole"];
	echo "</td>\n";

	echo "<td nowrap  valign='top'>";
	echo $contacts[$value['id']]['tel'];
	echo "</td>\n";
	echo "<td nowrap valign='top'>";
	echo $contacts[$value['id']]['gsm'];
	echo "</td>\n";

	$userid = $value["id"];
	echo "<td nowrap valign='top'>".$value["postcode"]."</td>\n";
	echo "<td nowrap valign='top'>";
	echo "<a href='mailto:".$contacts[$value['id']]['mail']."'>".$contacts[$value['id']]['mail']."</a>";
	echo "</td>\n";
	$balance = $value["saldo"];
	if($balance < $value["minlimit"] || ($value["maxlimit"] != NULL && $balance > $value["maxlimit"]))
	{
		echo "<td align='right'><font color='red'>".$balance."</font></td>\n";
	}
	else
	{
		echo "<td align='right'>".$balance."</td>\n";
	}
	echo "</tr>\n\n";
}
	echo "</table>\n</div>\n";


$query = "SELECT *
	FROM users
	WHERE status IN (0, 5, 6, 7)
	ORDER BY users.".$orderby;
$inactive_users = $db->GetArray($query);

// INACTIVE
echo "<h1>Overzicht inactieve gebruikers</h1>";

$color_codes = array(0 => 'black', 5 => 'orange', 6 => 'blue', 7 => 'grey');
echo "<table>";
echo "<tr>";
echo "<td bgcolor='#000000'><font color='white'>";
echo "<strong>Zwart blokje:</strong></td><td> gedesactiveerd</td>";
echo "</tr>";
echo "<tr>";
echo "<td bgcolor='orange'><font color='white'>";
echo "<strong>Oranje blokje:</strong></td><td> Infopakket</td>";
echo "</tr>";
echo "<tr>";
echo "<td bgcolor='blue'><font color='white'>";
echo "<strong>Blauw blokje:</strong></td><td> Infoavond</td>";
echo "</tr>";
echo "<tr>";
echo "<td bgcolor='#999999'><font color='white'>";
echo "<strong>Grijs blokje:</strong></td><td> Extern</td>";

echo "</tr>";
echo "</table>";

echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
echo "<tr class='header'>";
echo "<td valign='top'><strong>";
echo "<a href='overview.php?orderby=letscode'>Code</a>";
echo "</strong></td>";
echo "<td valign='top'><strong>";
echo "<a href='overview.php?orderby=fullname'>Naam</a>";
echo "</strong></td>";
//
echo "<td><strong>Tel</strong></td>\n";
echo "<td><strong>gsm</strong></td>\n";
//
echo "<td valign='top'><strong>";
echo "<a href='overview.php?orderby=postcode'>Postc</a>";
echo "</strong></td>";
echo "<td valign='top'><strong>";
echo "Mail";
echo "</strong></td>";
echo "<td valign='top'><strong>";
echo "Stand";
echo "</strong></td>";
echo "</tr>\n\n";
$rownumb = 0;
foreach($inactive_users as $key => $value)
{
	$rownumb++;
	echo "<tr";
	if($rownumb % 2 == 1)
	{
		echo " class='uneven_row'";
	}
	else
	{
		echo " class='even_row'";
	}
	echo ">\n";

	echo '<td nowrap valign="top" bgcolor="' . $color_codes[$value['status']] . '"><font color="white"><strong>';

	echo '<a href="view.php?id='.$value["id"].'" style="color: white">';
	echo trim($value["letscode"]);
	echo "</a>";

	echo "</strong></font>";

	echo "</td>";

	echo "<td nowrap valign='top'>";
	echo "<a href='view.php?id=".$value["id"]."'>".htmlspecialchars($value["fullname"],ENT_QUOTES)."</a>";
	echo "</td><td>";
	echo $contacts[$value['id']]['tel'];
	echo "</td>";
	echo "<td nowrap valign='top'>";
	echo $contacts[$value['id']]['gsm'];
	echo "</td>";
	echo "<td nowrap valign='top'>".$value["postcode"]."</td>\n";
	echo "<td nowrap valign='top'>";
	echo $contacts[$value['id']]['mail'];
	echo "</td>";
	$balance = $value["saldo"];
	if($balance < $value["minlimit"] || ($value["maxlimit"] != NULL && $value["maxlimit"] != 0 && $balance > $value["maxlimit"]))
	{
		echo "<td align='right'><font color='red'>".$balance."</font></td>\n";
	}
	else
	{
		echo "<td align='right'>".$balance."</td>";
	}
	echo "</tr>";
}

echo "</table>";

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
