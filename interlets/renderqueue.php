<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");
session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];
	
if($s_accountrole == "admin"){
	$interletsq = get_interletsq();
	showq($interletsq);
} else {
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function showq($interletsq){
	echo "<div class='border_b'>";
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td valign='top'>TransID</td>";
	echo "<td valign='top'>Datum</td>";
	echo "<td valign='top'>Van</td>";
	echo "<td valign='top'>Groep</td>";
	echo "<td valign='top'>Aan</td>";
	echo "<td valign='top'>Waarde</td>";
	echo "<td valign='top'>Omschrijving</td>";
	echo "<td valign='top'>Pogingen</td>";
	echo "<td valign='top'>Status</td>";
	echo "</tr>";

	$rownumb=0;
	foreach($interletsq as $key => $value){
		$rownumb=$rownumb+1;
		if($rownumb % 2 == 1){
			echo "<tr class='uneven_row'>";
		}else{
	        	echo "<tr class='even_row'>";
		}
		echo "<td nowrap valign='top'>";
		if(strtotime($value["retry_until"]) < time()){
			echo "<font color='red'>";
			echo $value["transid"];
			echo "</font>";
		} else {
			echo $value["transid"];
		}
		echo "</td>";

		echo "<td nowrap valign='top'>";
                echo $value["date_created"];         
                echo "</td>";

		echo "<td nowrap valign='top'>";
		$user = get_user($value["id_from"]);
                //echo $value["id_from"];         
		echo $user["fullname"];
                echo "</td>";

                echo "<td nowrap valign='top'>";
		$group = get_letsgroup($value["letsgroup_id"]);
		echo $group["shortname"];
                //echo $value["letsgroup_id"];
                echo "</td>";

		echo "<td nowrap valign='top'>";
                echo $value["letscode_to"];
                echo "</td>";

                echo "<td nowrap valign='top'>";
                echo $value["amount"];
                echo "</td>";

                echo "<td nowrap valign='top'>";
                echo $value["description"];
                echo "</td>";

                echo "<td nowrap valign='top'>";
                echo $value["retry_count"];
                echo "</td>";

		echo "<td nowrap valign='top'>";
                echo $value["last_status"];
                echo "</td>";

		echo "</tr>";
	}
	echo "</table></div>";
}


function get_interletsq(){
	global $db;
	$query = "SELECT * FROM interletsq";
	$interletsq = $db->GetArray($query);
	return $interletsq;
}

?>
