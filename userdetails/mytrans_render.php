<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_transactions.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

if (isset($s_id)){
        if($s_accountrole == "user" || $s_accountrole == "admin" || $s_accountrole == "interlets"){
		$transactions = get_all_user_transactions($s_id);
		$unprocessed = get_interlets_transactions($s_id);
		show_interletstransactions($unprocessed, $s_id);
		show_all_transactions($transactions, $s_id);
	}else{
		redirect_login($rootpath);
	}
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function get_interlets_transactions($s_id){
	global $db;
	$query = "SELECT * FROM interletsq WHERE id_from = " .$s_id;
	$transactions = $db->GetArray($query);
	return $transactions;
}

function show_interletstransactions($interletsq,$s_id){
	if(!empty($interletsq)){
		echo "<h2>Interlets transacties in verwerking</h2>";
		echo "<div class='border_b'>";
		echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
		echo "<tr class='header'>";
		//echo "<td valign='top'>TransID</td>";
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
                $ratio = readconfigfromdb("currencyratio");
                $realvalue = $value["amount"] * $ratio;
                echo $realvalue;
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
}

function show_all_transactions($transactions, $s_id){
	echo "<div class='border_b'>";
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td><strong>Datum</strong></td><td><strong>Van</strong></td>";
	echo "<td><strong>Aan</strong></td>";
	echo "<td><strong>Bedrag uit</strong></td>";
	echo "<td><strong>Bedrag in</strong></td>";
	echo "<td><strong>Dienst</strong></td></tr>";
	$rownumb=0;
	foreach ($transactions as $key => $value){
	 	$rownumb=$rownumb+1;
		if($rownumb % 2 == 1){
			echo "<tr class='uneven_row'>";
		}else{
	        	echo "<tr class='even_row'>";
		}
		echo "<td valign='top'>";
		echo $value["datum"];
		echo "</td><td valign='top'>";
		if(!empty($value["real_from"])){
			echo htmlspecialchars($value["real_from"],ENT_QUOTES);
		} else {
			echo htmlspecialchars($value["fromname"],ENT_QUOTES)." (".trim($value["fromcode"]).")";
		}
		echo "</td><td valign='top'>";
		if(!empty($value["real_to"])){
                        echo htmlspecialchars($value["real_to"],ENT_QUOTES);
                } else {
			echo htmlspecialchars($value["toname"],ENT_QUOTES)." (".trim($value["tocode"]).")";
		}
		echo "</td>";

		if ($value["fromid"] == $s_id){
		 		echo "<td valign='top'>";
				echo "-".$value["amount"];
				echo "</td>";
				echo "<td></td>";
		}else{
			echo "<td></td>";
			echo "<td valign='top'>";
			echo "+".$value["amount"];
			echo "</td>";
		}
		echo "<td valign='top'>";
		echo htmlspecialchars($value["description"],ENT_QUOTES);
		echo "</td></tr>";
	}
	echo "</table></div>";
}

function show_addlink($currency){
	echo "<div class='border_b'>| ";
	echo "<a href='mytrans_add.php'>{$currency} uitschrijven</a> |</div>";
}

function show_ptitle(){
	echo "<h1>Mijn transacties</h1>";
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

?>
