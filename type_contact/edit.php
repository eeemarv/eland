<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

if(isset($s_id) && ($s_accountrole == "admin")){
	show_ptitle();
	$id = $_GET["id"];
	if(isset($id)){
		if(isset($_POST["zend"])){
			$posted_list = array();
			$posted_list["name"] = $_POST["name"];
			$posted_list["abbrev"] = $_POST["abbrev"];
			if ($_POST["protect"] == TRUE){
                                        $posted_list["protect"] = TRUE;
                        }else{
                                        $posted_list["protect"] = FALSE;
                        }

			echo "Posted protect value is " .$_POST["protect"];
			$posted_list["id"] = $_GET["id"];
			$error_list = validate_input($posted_list);
	
			if (!empty($error_list)){
				show_form($posted_list, $error_list);
			}else{
				update_contacttype($id, $posted_list);
				redirect_overview();
			}
		}else{
			$contacttype = get_contacttype($id);
			show_form($contacttype, $error_list);
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

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle(){
	echo "<h1>Contacttype aanpassen</h1>";
}

function validate_input($posted_list){
	$error_list = array();
	if (!isset($posted_list["name"])|| (trim($posted_list["name"] )=="")){
		$error_list["name"]="<font color='#F56DB5'>Vul <strong>Type contact</strong> in!</font>";
	}
	return $error_list;
}

function update_contacttype($id, $posted_list){
  	global $db;
	$posted_list["mdate"] = date("Y-m-d H:i:s");
	echo "Protect is " .$posted_list["protect"];
	$result = $db->AutoExecute("type_contact", $posted_list, 'UPDATE', "id=$id");
	
}

function show_form($contacttype, $error_list){
	echo "<div class='border_b'><p>";
	echo "<form action='edit.php?id=".$contacttype["id"]."' method='POST'>";
	echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>";
	echo "<tr><td valign='top' align='right'>Type contact </td><td>";
	echo "<input type='text' name='name' size='40' ";
	echo "value='". htmlspecialchars($contacttype["name"],ENT_QUOTES). "'>";  
	echo "</td><td>";
	if (isset($error_list["name"])){
		echo $error_list["name"];
	}
	echo "</td></tr>";
	
	echo "<tr><td valign='top' align='right'>Afkorting </td><td>";
	echo "<input type='text' name='abbrev' size='40' ";
	echo "value='". htmlspecialchars($contacttype["abbrev"],ENT_QUOTES). "'>";  
	echo "</td><td></td></tr>";
	echo "<tr><td valign='top' align='right'>Beschermd </td><td>";
	if($contacttype["protect"] == 1) {
        	echo "<input type='checkbox' name='protect' value='1' CHECKED>";
	} else {
		echo "<input type='checkbox' name='protect'>";
	}

	echo "</td><td></td></tr>";
	
	echo "<tr><td colspan='2' align='right'>";
	echo "<input type='submit' value='Opslaan' name='zend'>";
	echo "</td><td>&nbsp;</td></tr></table>";
	echo "</form>";
	echo "</p></div>";
}

function get_contacttype($id){
global $db;
	$query = "SELECT * FROM type_contact WHERE id=".$id;
	$contacttype = $db->GetRow($query);
	return $contacttype;
}

function redirect_overview(){
	header("Location: overview.php");
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>

