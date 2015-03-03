<?php
ob_start();
$rootpath = "";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."inc_memberlist.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

$prefix_filterby = $_GET["prefix_filterby"];

if(isset($s_id)){
	show_ptitle();
	$userrows = get_all_active_users($user_orderby,$prefix_filterby);
 	show_all_users($userrows,$configuration);
	show_legend();
	
}else{
	redirect_login($rootpath);
}


////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_legend(){
echo "<table border='0'>";
echo "<tr>";
echo "<td ><strong>Vetschrift:</strong></td><td>Uitstapper</td>";
echo "</tr><tr>";
echo "<td><em>Schuinschrift:</strong></td><td> Instapper</td>";
echo "</tr>";
echo "</table></div>";
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle(){
	echo "<h1>Lets Contactlijst ";
	echo date("d-m-Y");
	echo " </h1>";
}

function get_contacts($userid){
	global $db;
	$query = "SELECT * FROM contact ";
	$query .= " WHERE id_user =".$userid;
	$contactrows = $db->GetArray($query);
	return $contactrows;
}
			
function check_timestamp($cdate,$agelimit){
        // agelimit is the time after which it expired
        $now = time();
        // age should be converted to seconds
        $limit = $now - ($agelimit * 60 * 60 * 24);
        $timestamp = strtotime($cdate);

        if($limit < $timestamp) {
                return 1;
        } else {
                return 0;
        }
}

function show_all_users($userrows,$configuration){
	global $s_accountrole;
	echo "<table  cellpadding='0' cellspacing='0' border='1' width='99%'>\n";
	echo "<tr class='header'>\n";
	echo "<td><strong>";
	echo "Code";
	echo "</strong></td>\n";
	echo "<td><strong>";
	echo "Naam";
	echo "</strong></td>\n";
	echo "<td><strong>Adres</strong></td>\n";
	echo "<td><strong>Tel</strong></td>\n";
	echo "<td><strong>gsm</strong></td>\n";
	echo "<td><strong>Mail</strong></td>\n";
	echo "</tr>\n\n";
	$rownumb=0;
	foreach($userrows as $key => $value){
	 	
		
		echo "<tr >\n";
		
		if($value["status"] == 2){
			echo "<td nowrap valign='top' ><strong>";
			echo $value["letscode"];
			echo "</strong></font>";
		}elseif(check_timestamp($value["adate"],readconfigfromdb("newuserdays")) == 1){
			echo "<td nowrap valign='top'><em>";
			echo $value["letscode"];
			echo "</em></font>";
		}else{
			echo "<td nowrap valign='top'>";
			echo $value["letscode"];
		}

		echo "</td>\n";


		if($value["status"] == 2){
			echo "<td nowrap valign='top' ><strong>";
			echo htmlspecialchars($value["fullname"],ENT_QUOTES);
			echo "</strong></font>";
		}elseif(check_timestamp($value["adate"],readconfigfromdb("newuserdays")) == 1){
			echo "<td nowrap valign='top'><em>";
			echo htmlspecialchars($value["fullname"],ENT_QUOTES);
			echo "</em></font>";
		}else{
			echo "<td nowrap valign='top'>";
			echo htmlspecialchars($value["fullname"],ENT_QUOTES);
		}
		
		echo "</td>\n";
		$userid = $value["id"];
		$contactrows = get_contacts($userid);
		echo "<td valign='top'>";
                        foreach($contactrows as $key2 => $value2){  
                                if ($value2["id_type_contact"] == 4 && ($value2["flag_public"] == 1 || $s_accountrole == "admin")){
                                        echo  $value2["value"];
                                break;
                                }
                        }
                echo "</td>\n";
		echo "<td nowrap valign='top'>";
			foreach($contactrows as $key2 => $value2){
				if ($value2["id_type_contact"] == 1 && ($value2["flag_public"] == 1 || $s_accountrole == "admin")){

					echo  $value2["value"];
				break;
				}
			}
		echo "</td>\n";
		echo "<td nowrap valign='top'>";
			foreach($contactrows as $key2 => $value2){
				if ($value2["id_type_contact"] == 2 && ($value2["flag_public"] == 1 || $s_accountrole == "admin")){

					echo $value2["value"];
					break;
				}
			}
		echo "</td>\n";
		echo "<td nowrap valign='top'>";
			foreach($contactrows as $key2 => $value2){
				if ($value2["id_type_contact"] == 3 && ($value2["flag_public"] == 1 || $s_accountrole == "admin")){

					echo $value2["value"];
					break;
				}
			}
		echo "</td>\n";
		
		echo "</tr>\n\n";
		
	}
	echo "</table></div>";
}


?>

