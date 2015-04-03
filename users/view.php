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

$user = $db->GetRow('SELECT *, cdate AS date, lastlogin AS logdate FROM users WHERE id='.$id);

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

include($rootpath."includes/inc_header.php");

echo "<h1>Gebruiker</h1>";

echo "<p>| <a href='editpw.php?id=" .$id. "'>Paswoord veranderen</a> |";
echo " <a href='activate.php?id=" .$id. "'>Activeren</a> |";
//	echo " <a href='delete.php?id=" .$s_id. "'>Delete</a> |";

echo "<table cellpadding='0' cellspacing='0' border='0' width='99%'>";
echo "<tr class='even_row'><td colspan='2'><strong>".htmlspecialchars($user["name"],ENT_QUOTES)." (";
echo trim($user["letscode"]).")</strong></td></tr>";

// Wrap arround another table to show user picture
	echo "<td width='170' align='left'>";
if($user["PictureFile"] == NULL) {
	echo "<img src='" .$rootpath ."gfx/nouser.png' width='250'></img>";
} else {
	echo '<img src="https://s3.eu-central-1.amazonaws.com/' . getenv('S3_BUCKET') . '/' . $user['PictureFile'] . '" width="250"></img>';
}
echo "</td>";

// inline table
echo "<td>";
echo "<table cellpadding='0' cellspacing='0' border='0' width='100%'>";
echo "<tr><td>Naam: </td>";
	echo "<td>".$user["fullname"]."</td></tr>";

echo "<tr><td>Postcode: </td>";
echo "<td>".$user["postcode"]."</td></tr>";

echo "<tr><td>Geboortedatum: </td>";
echo "<td>".$user["birthday"]."</td></tr>";

echo "<tr><td valign='top'>Hobbies/interesses: </td>";
echo "<td>".nl2br(htmlspecialchars($user["hobbies"],ENT_QUOTES))."</td></tr>";

echo "<tr><td valign='top'>Commentaar: </td>";
echo "<td>".nl2br(htmlspecialchars($user["comments"],ENT_QUOTES))."</td></tr>";

echo "<tr><td valign='top'>Login: </td>";
echo "<td>".htmlspecialchars($user["login"],ENT_QUOTES)."</td></tr>";
echo "<tr><td valign='top'>Datum aanmaak: </td>";
		echo "<td>" .$user["cdate"]."</td></tr>";
echo "<tr><td valign='top'>Datum activering: </td>";
		echo "<td>" .$user["adate"]."</td></tr>";
echo "<tr><td valign='top'>Laatste login: </td>";
echo "<td>".$user["logdate"]."</td></tr>";
echo "<tr><td valign='top'>Rechten:</td>";
echo "<td>".$user["accountrole"]."</td></tr>";
echo "<tr><td valign='top'>Status: </td>";
echo "<td>";
if($user["status"]==0){
	echo "Gedesactiveerd";
}elseif ($user["status"]==1){
	echo "Actief";
}elseif ($user["status"]==2){
	echo "Uitstapper";
}elseif ($user["status"]==3){
	echo "Instapper";
}elseif ($user["status"]==5){
	echo "Infopakket";
}elseif ($user["status"]==6){
	echo "Infoavond";
}elseif ($user["status"]==7){
	echo "Extern";
}
echo "</td></tr>";

echo "<tr><td valign='top'>Commentaar van de admin: </td>";
echo "<td>".nl2br(htmlspecialchars($user["admincomment"],ENT_QUOTES))."</td></tr>";

echo "<tr><td valign='top'>Limiet minstand:</td>";
echo "<td>".$user["minlimit"]."</td></tr>";

echo "<tr><td valign='top'>Saldo mail:  </td>";
if($user["cron_saldo"] == 1){
				echo "<td valign='top'>Aan</td>";
		} else {
				echo "<td valign='top'>Uit</td>";
		}
echo "</tr>";

echo "</table>";
echo "</td>";

echo "<tr><td colspan='2'>&#160;</td></tr>";
echo "<tr><td colspan='2'>";

echo '| <a href="edit.php?mode=edit&id=' . $user["id"] . '" >Aanpassen</a> | ';
echo "</td></tr>";
echo "</table>";

$contact = get_contact($id);
show_contact($contact, $user_id);

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
