<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if (!isset($_GET["id"])){
	header('Location: overview.php');
}

$id = $_GET["id"];

$user = $db->GetRow('SELECT *, cdate AS date, lastlogin AS logdate FROM users WHERE id = '.$id);

$includejs = '<script type="text/javascript">var user_id = ' . $id . ';</script>
	<script src="' . $cdn_jquery . '"></script>
	<script src="' . $cdn_jqplot . 'jquery.jqplot.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.donutRenderer.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.cursor.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.dateAxisRenderer.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.canvasTextRenderer.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.canvasAxisTickRenderer.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.highlighter.min.js"></script>
	<script src="' . $rootpath . 'js/plot_user_transactions.js"></script>';

$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_jqplot . 'jquery.jqplot.min.css" />
	<link rel="stylesheet" type="text/css" href="' . $rootpath . 'gfx/tooltip.css" />';

$top_buttons = '<a href="edit.php?mode=new" class="btn btn-success"';
$top_buttons .= ' title="gebruiker toevoegen"><i class="fa fa-plus"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

$top_buttons .= '<a href="edit.php?mode=edit&id=' . $id . '" class="btn btn-primary"';
$top_buttons .= ' title="Gebruiker aanpassen"><i class="fa fa-pencil"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Aanpassen</span></a>';

$top_buttons .= '<a href="editpw.php?id='. $id . '" class="btn btn-info"';
$top_buttons .= ' title="Paswoord aanpassen"><i class="fa fa-key"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Paswoord aanpassen</span></a>';

$top_buttons .= '<a href="activate.php?id='. $id . '" class="btn btn-warning"';
$top_buttons .= ' title="Activeren"><i class="fa fa-check"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Activeren</span></a>';

if (!$db->GetOne('select id from transactions where id_to = ' . $id . ' or id_from = ' . $id))
{
	$top_buttons .= '<a href="delete.php?id=' . $id . '" class="btn btn-danger"';
	$top_buttons .= ' title="gebruiker verwijderen">';
	$top_buttons .= '<i class="fa fa-times"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Verwijderen</span></a>';
}

include($rootpath."includes/inc_header.php");

echo '<h1><i class="fa fa-user"></i> ' . $user['letscode'] . ' ' . $user['fullname'] . '</h1>';

echo '<div class="row">';
echo '<div class="col-xs-4">';

if(isset($user["PictureFile"]))
{
	echo '<img src="https://s3.eu-central-1.amazonaws.com/' . getenv('S3_BUCKET') . '/' . $user['PictureFile'] . '" width="250"></img>';
}
else
{
	echo '<i class="fa fa-user fa-5x text-muted"></i><br>Geen profielfoto';
}

echo '</div>';
echo '<div class="col-xs-8">';

echo '<dl>';
echo '<dt>';
echo 'Naam';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["name"],ENT_QUOTES);
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Volledige naam';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["fullname"],ENT_QUOTES);
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Postcode';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["postcode"],ENT_QUOTES);
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Geboortedatum';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["birthday"],ENT_QUOTES);
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Hobbies / Interesses';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["hobbies"],ENT_QUOTES);
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Commentaar';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["comments"],ENT_QUOTES);
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Login';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["login"],ENT_QUOTES);
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Tijdstip aanmaak';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["cdate"],ENT_QUOTES);
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Tijdstip activering';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["adate"],ENT_QUOTES);
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Laatste login';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["logdate"],ENT_QUOTES);
echo '</dd>';
echo '</dl>';

$status_ary = array(
	0	=> 'Gedesactiveerd',
	1	=> 'Actief',
	2	=> 'Uitstapper',
	3	=> 'Instapper', // not used
	4	=> 'Infopakket',
	5	=> 'Infoavond',
	6	=> 'Extern',
);

echo '<dl>';
echo '<dt>';
echo 'Rechten';
echo '</dt>';
echo '<dd>';
echo $status_ary[$user['status']];
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Commentaar van de admin';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["admincomment"],ENT_QUOTES);
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Saldo, limiet min, limiet max';
echo '</dt>';
echo '<dd>';
echo '<span class="label label-default">' . $user['saldo'] . '</span>&nbsp;';
echo '<span class="label label-danger">' . $user['minlimit'] . '</span>&nbsp;';
echo '<span class="label label-success">' . $user['maxlimit'] . '</span>';
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Periodieke Saldo mail met recent vraag en aanbod';
echo '</dt>';
echo '<dd>';
echo ($user["cron_saldo"] == 't') ? 'Aan' : 'Uit';
echo '</dd>';
echo '</dl>';

echo '</div></div>';

$contacts = $db->GetArray('select c.*, tc.abbrev
	from contact c, type_contact tc
	where c.id_type_contact = tc.id
		and c.id_user = ' . $id);

echo '<div class="row">';
echo '<div class="col-xs-12 col-md-12">';

echo '<h3>Contacten ';
echo '<a href="' . $rootpath . 'users/cont_add.php?uid=' . $id . '"';
echo ' class="btn btn-success" title="Contact toevoegen">';
echo '<i class="fa fa-plus"></i><span class="hidden-xs"> Toevoegen</span></a>';
echo '</h3>';





echo '<div class="table-responsive">';
echo '<table class="table table-hover table-striped table-bordered footable">';

echo '<thead>';
echo '<tr>';
echo '<th>Type</th>';
echo '<th>Waarde</th>';
echo '<th data-hide="phone, tablet">Commentaar</th>';
echo '<th data-hide="phone, tablet">Publiek</th>';
echo '<th data-sort-ignore="true" data-hide="phone, tablet">Verwijderen</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach ($contacts as $c)
{
	$a = '<a href="' . $rootpath . 'users/cont_edit.php?cid=' . $c['id'];
	$a .= '&uid=' . $c['id_user'] . '">';
	echo '<tr>';
	echo '<td>' . $a . $c['abbrev'] . '</a></td>';
	echo '<td>' . $a . htmlspecialchars($c['value'],ENT_QUOTES) . '</a></td>';
	echo '<td>' . $a . htmlspecialchars($c['comment'],ENT_QUOTES) . '</a></td>';
	echo '<td>' . $a . (($c['flag_public'] == 1) ? 'Ja' : 'Nee') . '</a></td>';
	echo '<td><a href="' . $rootpath . 'users/cont_delete.php?cid='.$c['id'];
	echo '&uid=' . $c['id_user'] . '" class="btn btn-danger btn-xs"><i class="fa fa-times"></i>';
	echo ' Verwijderen</a></td>';
	echo '</tr>';
}

echo '</tbody>';

echo '</table>';
echo '</div>';

echo '</div></div>';

echo "<div >";
echo "<table cellpadding='0' cellspacing='0' border='1' width='99%' class='data'>";

echo "<tr class='even_row'>";
echo "<td colspan='5'><p><strong>Contactinfo</strong></p></td>";
echo "</tr>";
echo "<tr>";
echo "<th>Type</th>";
echo "<th valign='top'>Waarde</th>";
echo "<th valign='top'>Commentaar</th>";
echo "<th valign='top'>Publiek</th>";
echo "<th valign='top'></th>";
echo "</tr>";

foreach($contact as $key => $value){
	echo "<tr>";
	echo "<td valign='top'>".$value["abbrev"].": </td>";
	echo "<td valign='top'>".htmlspecialchars($value["value"],ENT_QUOTES)."</td>";
	echo "<td valign='top'>".htmlspecialchars($value["comments"],ENT_QUOTES)."</td>";
	echo "<td valign='top'>";
	if (trim($value["flag_public"]) == 1){
			echo "Ja";
	}else{
			echo "Nee";
	}
	echo "</td>";
	echo "<td valign='top' nowrap>|";
	echo "<a href='cont_edit.php?cid=".$value["id"]."&uid=".$value["id_user"]."'>";
	echo " aanpassen </a> |";
	echo "<a href='cont_delete.php?cid=".$value["id"]."&uid=".$value["id_user"]."'>";
	echo "verwijderen </a>|";
	echo "</td>";
	echo "</tr>";
}
	echo "<tr><td colspan='5'><p>&#160;</p></td></tr>";
	echo "<tr><td colspan='5'>| ";
	echo "<a href='cont_add.php?uid=" . $value['id_user'] . "'>";
	echo "Contact toevoegen</a> ";
	echo "|</td></tr>";
	echo "</table></div>";




$balance = $user["saldo"];
$currency = readconfigfromdb("currency");
echo "<table cellpadding='0' cellspacing='0' border='0' width='99%'>";
echo "<tr><td>&#160;</td></tr>";
echo "<tr class='even_row'>";
echo '<td><strong>' . $currency .'stand: ' . $balance .'</strong></td><td>Interacties voorbije jaar</td></tr>';
echo "<tr><td><div id='chartdiv1' style='height:300px;width:400px;'></div></td>";
echo "<td><div id='chartdiv2' style='height:300px;width:300px;'></div></td></tr></table>";

echo "<div class='border_b'>";
echo "<a href='../print_usertransacties.php?id=".$id."'>Print transactielijst</a> ";
echo "<a href='../export_transactions.php?userid=".$id."'>Export transactielijst</a>";
echo "</div>";

$messages = $db->GetArray("SELECT * FROM messages where id_user = ".$id." and validity > now() order by cdate");

echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
echo "<tr class='header'>";
echo "<td colspan='2'><strong>Vraag & Aanbod</strong></td>";
echo "</tr>";
$rownumb=0;
foreach($messages as $key => $value){
	$rownumb=$rownumb+1;
	if($rownumb % 2 == 1){
		echo "<tr class='uneven_row'>";
	}else{
			echo "<tr class='even_row'>";
	}
	echo "<td valign='top'>";
	if($value["msg_type"]==0){
		echo "V";
	}elseif ($value["msg_type"]==1){
		echo "A";
	}
	echo "</td>";
	echo "<td valign='top'>";
	echo "<a href='../messages/view.php?id=".$value["id"]."'>";
	if(strtotime($value["validity"]) < time()) {
					echo "<del>";
			}
	$content = htmlspecialchars($value["content"],ENT_QUOTES);
	echo chop_string($content, 60);
	if(strlen($content)>60){
		echo "...";
	}
	if(strtotime($value["validity"]) < time()) {
					echo "</del>";
			}
	echo "</a>";
	echo "</td>";
	echo "</td>";
	echo "</tr>";
}
//echo "<tr><td colspan='2'>&#160;</td></tr>";
echo "</table>";




$transactions = get_all_transactions($id);

echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
echo "<tr class='header'>";
echo "<td nowrap valign='top'><strong>";
echo "Datum";
echo "</strong></td><td valign='top'><strong>Van</strong></td>";
echo "<td><strong>Aan</strong></td>";
echo "<td><strong>";
echo "Bedrag uit";
echo "</strong></td>";
echo "<td><strong>";
echo "Bedrag in";
echo "</strong></td>";
echo "<td valign='top'><strong>";
echo "Dienst";
echo "</strong></td></tr>";
$rownumb=0;

foreach($transactions as $key => $value){
	$rownumb=$rownumb+1;
	if($rownumb % 2 == 1){
		echo "<tr class='uneven_row'>";
	}else{
			echo "<tr class='even_row'>";
	}
	echo "<td nowrap valign='top'>";
	echo $value["datum"];
	echo '</td><td' . (($value['id_from'] == $id) ? ' class="me"' : '') . '>';
	echo '<a href="view.php?id=' . $value['id_from'] . '">';
	echo htmlspecialchars($value["fromusername"],ENT_QUOTES). " (" .trim($value["fromletscode"]).")";
	echo '</a></td><td' . (($value['id_to'] == $id) ? ' class="me"' : '') . '>';
	echo '<a href="view.php?id=' . $value['id_to'] . '">';
	echo htmlspecialchars($value["tousername"],ENT_QUOTES). " (" .trim($value["toletscode"]).")";
	echo "</a></td>";

	if ($value["fromusername"] == $user["name"]){
		echo "<td valign='top' nowrap>";
		echo $value["amount"];
		echo "</td>";
		echo "<td></td>";
	}else{
		echo "<td></td>";
		echo "<td valign='top' nowrap>";
		echo "+".$value["amount"];
		echo "</td>";
	}
	echo "<td valign='top'>";
	echo "<a href='".$rootpath."transactions/view.php?id=".$value["transid"]."'>";
	echo htmlspecialchars($value["description"],ENT_QUOTES);
	echo "</a> ";
	echo "</td></tr>";
}
echo "</table>";



function chop_string($content, $maxsize){
$strlength = strlen($content);
    //geef substr van kar 0 tot aan 1ste spatie na 30ste kar
    //dit moet enkel indien de lengte van de string groter is dan 30
    if ($strlength >= $maxsize){
        $spacechar = strpos($content," ", 60);
        if($spacechar == 0){
            return $content;
        }else{
            return substr($content,0,$spacechar);
        }
    }else{
        return $content;
    }
}

function get_numberoftransactions($user_id){
	global $db;

	$query_min = "SELECT count(*) ";
	$query_min .= " FROM transactions ";
	$query_min .= " WHERE id_from = ".$user_id ." or id_to = ".$user_id ;
	return $db->GetOne($query_min);
}




function get_contact($id){
	global $db;
	$query = "SELECT *, ";
	$query .= " contact.id AS cid, users.id AS uid, type_contact.id AS tcid, ";
	$query .= " type_contact.name AS tcname, users.name AS uname ";
	$query .= " FROM users, type_contact, contact ";
	$query .= " WHERE users.id=".$id;
	$query .= " AND contact.id_type_contact = type_contact.id ";
	$query .= " AND users.id = contact.id_user ";
	$contact = $db->GetArray($query);
	return $contact;
}

function show_contact($contact, $user_id ){
	echo "<div >";
	echo "<table cellpadding='0' cellspacing='0' border='1' width='99%' class='data'>";

	echo "<tr class='even_row'>";
	echo "<td colspan='5'><p><strong>Contactinfo</strong></p></td>";
	echo "</tr>";
	echo "<tr>";
	echo "<th valign='top'>Type</th>";
	echo "<th valign='top'>Waarde</th>";
	echo "<th valign='top'>Commentaar</th>";
	echo "<th valign='top'>Publiek</th>";
	echo "<th valign='top'></th>";
	echo "</tr>";

	foreach($contact as $key => $value){
		echo "<tr>";
		echo "<td valign='top'>".$value["abbrev"].": </td>";
		echo "<td valign='top'>".htmlspecialchars($value["value"],ENT_QUOTES)."</td>";
		echo "<td valign='top'>".htmlspecialchars($value["comments"],ENT_QUOTES)."</td>";
		echo "<td valign='top'>";
		if (trim($value["flag_public"]) == 1){
				echo "Ja";
		}else{
				echo "Nee";
		}
		echo "</td>";
		echo "<td valign='top' nowrap>|";
		echo "<a href='cont_edit.php?cid=".$value["id"]."&uid=".$value["id_user"]."'>";
		echo " aanpassen </a> |";
		echo "<a href='cont_delete.php?cid=".$value["id"]."&uid=".$value["id_user"]."'>";
		echo "verwijderen </a>|";
		echo "</td>";
		echo "</tr>";
	}
	echo "<tr><td colspan='5'><p>&#160;</p></td></tr>";
	echo "<tr><td colspan='5'>| ";
	echo "<a href='cont_add.php?uid=" . $value['id_user'] . "'>";
	echo "Contact toevoegen</a> ";
	echo "|</td></tr>";
	echo "</table></div>";
}

function get_all_transactions($user_id){
	global $db;
	$query = "SELECT *, ";
	$query .= " transactions.id AS transid, ";
	$query .= " fromusers.id AS userid, ";
	$query .= " fromusers.name AS fromusername, tousers.name AS tousername, ";
	$query .= " fromusers.letscode AS fromletscode, tousers.letscode AS toletscode, ";
	$query .= " transactions.date AS datum ";
	$query .= " FROM transactions, users  AS fromusers, users AS tousers";
	$query .= " WHERE transactions.id_to = tousers.id";
	$query .= " AND transactions.id_from = fromusers.id";
	$query .= " AND (transactions.id_from = ".$user_id." OR transactions.id_to = ".$user_id.")";

	if (isset($trans_orderby)){
		$query .= " ORDER BY transactions.".$trans_orderby. " ";
	}
	else {
		$query .= " ORDER BY transactions.date DESC";
	}
	$transactions = $db->GetArray($query);
	return $transactions;
}

include($rootpath."includes/inc_footer.php");
