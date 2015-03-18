<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if(!(isset($s_id) && ($s_accountrole == "admin"))){
	header("Location: ".$rootpath."login.php");
	exit;
}

include($rootpath."includes/inc_header.php");

echo "<div class='border_b'>| <a href='add.php'>Contacttype toevoegen</a> |</div>";
echo "<h1>Overzicht contacttypes</h1>";
$contacttypes = get_all_contacttypes();
show_all_contacttypes($contacttypes);

include($rootpath."includes/inc_footer.php");


//////////////////


function show_all_contacttypes($contacttypes){
	echo "<div class='border_b'>";
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td><strong>Naam </strong></td>";
	echo "<td><strong>Afkorting</strong></td>";
	echo '<td>Aanpassen</td>';
	echo '<td>Verwijderen</td>';
	echo "</tr>";
	$rownumb=0;
	foreach($contacttypes as $value){

		$protected = (in_array($value['abbrev'], array('mail', 'gsm', 'tel', 'adr'))) ? true : false;
	 	$rownumb=$rownumb+1;
		if($rownumb % 2 == 1){
			echo "<tr class='uneven_row'>";
		}else{
	        	echo "<tr class='even_row'>";
		}
		echo "<td valign='top'>";
		echo ($protected) ? '' : "<a href='edit.php?id=".$value["id"]."'>";
		echo htmlspecialchars($value["name"],ENT_QUOTES);
		echo ($protected) ? '*': '</a>';
		echo "</td><td>";
		if(!empty($value["abbrev"])){
			echo htmlspecialchars($value["abbrev"],ENT_QUOTES);
		}
		echo "</td><td>";
		echo ($protected) ? '' : '<a href="edit.php?id=' . $value['id'] . '">Aanpassen</a>';
		echo '</td><td>';
		echo ($protected) ? '' : '<a href="delete.php?id=' . $value['id'] . '">Verwijderen</a>';
		echo '</td>';
		echo '</tr>';
	}
	echo "</table></div>";
	echo '<p>* Beschermd contact type: kan niet aangepast of verwijderd worden.</p>';
}

function get_all_contacttypes(){
	global $db;
	$query = "SELECT * FROM type_contact";
	$contacttypes = $db->GetArray($query);
	return $contacttypes;
}
