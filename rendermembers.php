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

$prefix = $_POST["prefix"];
$posted_list["prefix"] = $prefix;
$searchname = $_POST["searchname"];
$sortfield = $_POST["sort"];

//var_dump($_POST);

if(isset($s_id)){
	$userrows = get_all_active_users($user_orderby,$posted_list["prefix"],$searchname,$sortfield);
 	show_all_users($userrows);
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function get_contacts($userid){
	global $db;
	$query = "SELECT * FROM contact ";
	$query .= " WHERE id_user =".$userid;
 	$query .= " AND contact.flag_public = 1";
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

function show_all_users($userrows){
	echo "<div class='border_b'><table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>\n";
	echo "<tr class='header'>\n";
	echo "<td><strong>";
	echo "Code";
	echo "</strong></td>\n";
	echo "<td><strong>";
	echo "Naam";
	echo "</strong></td>\n";
	echo "<td><strong>Tel</strong></td>\n";
	echo "<td><strong>gsm</strong></td>\n";
	echo "<td><strong>";
	echo "Postc";
	echo "</strong></td>\n";
	echo "<td><strong>Mail</strong></td>\n";
	echo "<td><strong>Stand</strong></td>\n";
	echo "</tr>\n\n";
	$newuserdays = readconfigfromdb("newuserdays");
	$rownumb=0;
	foreach($userrows as $key => $value){
	 	$rownumb=$rownumb+1;
		if($rownumb % 2 == 1){
			echo "<tr class='uneven_row'>\n";
		}else{
	        	echo "<tr class='even_row'>\n";
		}

		if($value["status"] == 2){
			echo "<td nowrap valign='top' bgcolor='#f475b6'><font color='white' ><strong>";
			echo $value["letscode"];
			echo "</strong></font>";
		}elseif(check_timestamp($value["cdate"],$newuserdays) == 1){
			echo "<td nowrap valign='top' bgcolor='#B9DC2E'><font color='white'><strong>";
			echo $value["letscode"];
                        echo "</strong></font>";
		}else{
			echo "<td nowrap valign='top'>";
			echo $value["letscode"];
		}

		echo"</td>\n";
		echo "<td valign='top'>";
		echo "<a href='memberlist_view.php?id=".$value["id"]."'>".htmlspecialchars($value["fullname"],ENT_QUOTES)."</a></td>\n";
		echo "<td nowrap  valign='top'>";
		$userid = $value["id"];
		$contactrows = get_contacts($userid);
			
			foreach($contactrows as $key2 => $value2){
				if ($value2["id_type_contact"] == 1){
					echo  $value2["value"];
				break;
				}
			}
		echo "</td>\n";
		echo "<td nowrap valign='top'>";
			foreach($contactrows as $key2 => $value2){
				if ($value2["id_type_contact"] == 2){
					echo $value2["value"];
					break;
				}
			}
		echo "</td>\n";
		echo "<td nowrap valign='top'>".$value["postcode"]."</td>\n";
		echo "<td nowrap valign='top'>";
			foreach($contactrows as $key2 => $value2){
				if ($value2["id_type_contact"] == 3){
					echo "<a href='mailto:".$value2["value"]."'>".$value2["value"]."</a>";
					break;
				}
			}
		echo "</td>\n";
		
		echo "<td nowrap valign='top' align='right'>";
		$balance = $value["saldo"];
                if($balance < $value["minlimit"] || ($value["maxlimit"] != NULL && $balance > $value["maxlimit"])){
			echo "<font color='red'> $balance </font>";
		}else{
			echo $balance;
		}

		echo "</td>\n";
		echo "</tr>\n\n";
		
	}
	echo "</table></div>";
}

?>
