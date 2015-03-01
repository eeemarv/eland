<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_passwords.php");
session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];


include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

if(isset($s_id) && ($s_accountrole == "admin")){
	if (isset($_GET["id"])){
		$id = $_GET["id"];
		show_ptitle();
		if(isset($_POST["zend"])){
			$posted_list = array();
			$posted_list["pw1"] = $_POST["pw1"];
			$posted_list["pw2"] = $_POST["pw2"];
			$errorlist = validate_input($posted_list,$configuration);
				if (!empty($errorlist)){
					show_pwform($errorlist, $id);
				}else{
					update_password($id, $posted_list);
					redirect_view($id);
				}
		}else{
			show_pwform($errorlist, $id);
		}
	}else{
		redirect_overview();
	}
}else{
	redirect_login($rootpath);
}


////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_overview(){
	header("Location: overview.php");
}

function redirect_view($id){
	header("Location: view.php?id=".$id."");
}

function validate_input($posted_list,$configuration){
	$errorlist = array();
	if (empty($posted_list["pw1"]) || (trim($posted_list["pw1"]) == "")){
		$errorlist["pw1"] = "<font color='#F56DB5'>Vul <strong>paswoord</strong> in!</font>";
	}
	//$pwscore = Password_Strength($posted_list["pw1"]);
        //$pwreqscore = $configuration["system"]["pwscore"];
        //if ($pwscore < $pwreqscore){
        //        $errorlist["pw1"] = "<font color='#F56DB5'>Paswoord is te zwak (score $pwscore/$pwreqscore)</font>";
        //}

	
	if (empty($posted_list["pw2"]) || (trim($posted_list["pw2"]) == "")){
		$errorlist["pw2"] = "<font color='#F56DB5'>Vul <strong>paswoord</strong> in!</font>";
	}
	if ($posted_list["pw1"] !== $posted_list["pw2"]){
	$errorlist["pw3"] = "<font color='#F56DB5'><strong>Paswoorden zijn niet identiek</strong>!</font>";
	}
	return $errorlist;
}

function show_pwform($errorlist, $id){
	echo "<div class='border_b'>";
	echo "<form action='editpw.php?id=".$id."' method='POST'>";
	echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>";
	echo "<tr><td valign='top' align='right'>Paswoord</td>";
	echo "<td valign='top'>";
	echo "<input  type='password' name='pw1' size='30' >";
	echo "</td>";
	echo "<td>";
		if (isset($errorlist["pw1"])){
			echo $errorlist["pw1"];
		}
	echo "</td>";
	echo "</tr>";
	echo "<tr><td valign='top' align='right'>Herhaal paswoord</td>";
	echo "<td valign='top'>";
	echo "<input  type='password' name='pw2' size='30' >";
	echo "</td>";
	echo "<td>";
		if (isset($errorlist["pw2"])){
			echo $errorlist["pw2"];
		}
	echo "</td>";
	echo "</tr>";
	echo "<tr><td colspan='3'>";
		if (isset($errorlist["pw3"])){
			echo $errorlist["pw3"];
		}
	echo "</td></tr>";
	echo "<tr><td colspan='2' align='right'>";
	echo "<input type='submit' value='paswoord wijzigen' name='zend'>";
	echo "</td><td>&nbsp;</td></tr>";
	echo "</table>";
	echo "</form>";
	echo "</div>";
}


function show_ptitle(){
	echo "<h1>Paswoord veranderen</h1>";
}
function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}
include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
