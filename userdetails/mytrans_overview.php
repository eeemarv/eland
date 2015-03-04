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

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

if (isset($s_id)){
        if($s_accountrole == "user" || $s_accountrole == "admin" || $s_accountrole == "interlets"){
		$user = get_user($s_id);
		show_ptitle();
		//$transactions = get_all_transactions($s_id);
		//$unprocessed = get_interlets_transactions($s_id);

		echo $user["$minlimit"];
		$balance = $user["saldo"];

		//show_form($list_users, $user, $balance,$s_letscode);
		show_balance($balance, $user);
		show_outputdiv();
	}else{
		redirect_login($rootpath);
	}
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_form($list_users){
	global $s_accountrole;
	global $s_letscode;
	$date = date("Y-m-d");

	$user = readuser($s_id);
	$list_users = get_users($s_id);

	echo "<script type='text/javascript' src='/js/posttransaction.js'></script>";
	echo "<script type='text/javascript' src='/js/userinfo.js'></script>";
	$currency = readconfigfromdb("currency");
	echo "<div id='transformdiv'>";
	echo "<form action=\"javascript:showloader('serveroutput'); get(document.getElementById('transform'));\" name='transform' id='transform'>";
		echo "<table cellspacing='0' cellpadding='0' border='0'>";
	echo "<tr><td align='right'>";
	echo "Van";
	echo "</td><td>";
	//echo "<input name='letscode_from' id='letscode_from' ";
	echo "<select name='letscode_from' id='letscode_from' accesskey='2'\n";
	if($s_accountrole != "admin") {
                echo " DISABLED";
	}
	echo " onchange=\"javascript:document.getElementById('baldiv').innerHTML = ''\">";
	foreach ($list_users as $value){
		echo "<option value='".$value["letscode"]."' >";
		echo htmlspecialchars($value["fullname"],ENT_QUOTES) ." (" .$value["letscode"] .")";
		echo "</option>\n";
	}
	echo "</select>\n";

	echo "</td><td width='150'><div id='fromoutputdiv'></div>";
	echo "</td></tr>";

	echo "<tr><td valign='top' align='right'>Datum</td><td>";
        echo "<input type='text' name='date' id='date' size='18' value='" .$date ."'";
	if($s_accountrole != "admin") {
                echo " DISABLED";
        }
        echo ">";
        echo "</td><td>";
        echo "</td></tr><tr><td></td><td>";
        echo "</td></tr>";

	echo "<tr><td align='right'>";
        echo "Aan LETS groep";
        echo "</td><td>";
        echo "<select name='letgroup' id='letsgroup' onchange=\"document.getElementById('letscode_to').value='';\">\n";
	$letsgroups = get_letsgroups();
	foreach($letsgroups as $key => $value){
		$id = $value["id"];
		$name = $value["groupname"];
		echo "<option value='$id'>$name</option>";
	}
	echo "</select>";
	echo "</td><td>";
	echo "</td></tr><tr><td></td><td>";
	echo "<tr><td align='right'>";
	echo "Aan LETSCode";
	echo "</td><td>";
	echo "<input type='text' name='letscode_to' id='letscode_to' size='10' onchange=\"javascript:showsmallloader('tooutputdiv');loaduser('letscode_to','tooutputdiv')\">";
	echo "</td><td><div id='tooutputdiv'></div>";
	echo "</td></tr><tr><td></td><td>";
	echo "</td></tr>";

	echo "<tr><td valign='top' align='right'>Aantal {$currency}</td><td>";
	echo "<input type='text' id='amount' name='amount' size='10' ";
	echo ">";
	echo "</td><td>";
	echo "</td></tr>";
	echo "<tr><td></td><td>";
	echo "</td></tr>";

	echo "<tr><td valign='top' align='right'>Dienst</td><td>";
	echo "<input type='text' name='description' id='description' size='40' MAXLENGTH='60' ";
	echo ">";
	echo "</td><td>";
	echo "</td></tr><tr><td></td><td>";
	echo "</td></tr>";
	echo "<tr><td colspan='3' align='right'>";
	echo "<input type='submit' name='zend' id='zend' value='Overschrijven'>";
	echo "</td></tr></table>";
	echo "</form>";
	echo "<script type='text/javascript'>loaduser('letscode_from','fromoutputdiv')</script>";
	echo "</div>";

	echo "<script type='text/javascript'>document.getElementById('letscode_from').value = '$s_letscode';</script>";
}

function show_outputdiv(){
        echo "<div id='output'><img src='/gfx/ajax-loader.gif' ALT='loading'>";
        echo "<script type=\"text/javascript\">loadurl('mytrans_render.php')</script>";
        echo "</div>";
}

function show_balance($balance, $user){
	$currency = readconfigfromdb("currency");
	$minlimit = $user["minlimit"];
	if ($balance < $minlimit ){
		echo "<strong><font color='red'>Je hebt de limiet minstand bereikt.<br>";
		echo " Je kunt geen {$currency} uitschrijven!</font></strong>";
	}
	if($user["maxlimit"] != NULL && $balance > $user["maxlimit"]){
		echo "<strong><font color='red'>Je hebt de limiet maxstand bereikt.<br>";
                echo " Je kunt geen {$currency} meer ontvangen</font></strong>";
        }
	echo "<p><strong>Huidige {$currency}stand: ".$balance."</strong></br>";
	echo "Limiet minstand: ".$minlimit."</p>";
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
		echo htmlspecialchars($value["fromname"],ENT_QUOTES)." (".trim($value["fromcode"]).")";
		echo "</td><td valign='top'>";
		echo htmlspecialchars($value["toname"],ENT_QUOTES)." (".trim($value["tocode"]).")";
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

function show_addlink(){
	global $rootpath;
	$currency = readconfigfromdb("currency");
	echo "<div class='border_b'>| ";
	echo "<a href='{$rootpath}transactions/add.php'>{$currency} uitschrijven</a> |</div>";
}

function show_ptitle(){
	echo "<h1>Mijn transacties</h1>";
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
