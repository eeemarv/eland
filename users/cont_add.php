<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userfunctions.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

if(isset($s_id) && ($s_accountrole == "admin")){
	$uid = $_GET["uid"];
	show_ptitle();
	if(isset($_POST["zend"])){
		$posted_list = array();
		$posted_list["id_type_contact"] = $_POST["id_type_contact"];
		$posted_list["value"] = $_POST["value"];
		$posted_list["comments"] = $_POST["comments"];
		$uid = $_POST["uid"];

			if (trim($_POST["flag_public"]) == 1){
					$posted_list["flag_public"] = 1;
			}else{
					$posted_list["flag_public"] = 0;
			}

		$error_list = validate_input($posted_list);
		if(!empty($error_list)){
			$typecontactrow = get_type_contacts();
			show_form($typecontactrow, $error_list, $posted_list, $uid);
		}else{
			add_contact($posted_list, $uid);
			redirect_view($uid);
		}
	}else{
		$typecontactrow = get_type_contacts();
		show_form($typecontactrow, $error_list, $posted_list, $uid);
	}
}else{
	redirect_login($rootpath);
}
////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function validate_input($posted_list){
	$error_list = array();
	if (empty($posted_list["value"]) || (trim($posted_list["value"]) == "")){
		$error_list["value"] = "<font color='#F56DB5'>Vul <strong>waarde</strong> in!</font>";
	}
	global $db;
	$query =" SELECT * FROM type_contact ";
	$query .=" WHERE  id = '".$posted_list["id_type_contact"]."' ";
	$rs = $db->Execute($query);
    $number = $rs->recordcount();
	if( $number == 0 ){
		$error_list["id_type_contact"]="<font color='#F56DB5'>Contacttype <strong>bestaat niet!</strong></font>";
	}
	return $error_list;
}

function show_ptitle(){
	echo "<h1>Contact toevoegen</h1>";
}

function get_type_contacts(){
	global $db;
	$query = "SELECT * FROM type_contact ORDER BY id ";
	$typecontactrow = $db->GetArray($query);
	return $typecontactrow;
}

function show_form($typecontactrow, $error_list, $posted_list, $uid){
	echo "<div class='border_b'>";
	echo "<form method='POST' action='cont_add.php'>\n";
	echo "<table  class='data' cellspacing='0' cellpadding='0' border='0'>\n\n";
	echo "<tr>\n<td colspan='2'><input type='hidden' name='uid' value='".$uid."'></td>\n</tr>\n\n";
	echo "<tr>\n";
	echo "<td valign='top' align='right'>Type</td>\n";
	echo "<td>";
	echo "<select name='id_type_contact'>\n";
	foreach($typecontactrow as $key => $value){
		echo "<option value='".$value["id"]."' >".$value["name"]."</option>";
	}
	echo "</select>\n";
	echo "</td>\n</tr>\n\n<tr>\n<td></td>\n";
	echo "<td>";
	if(isset($error_list["id_type_contact"])){
		echo $error_list["id_type_contact"];
	}
	echo "</td>\n";
	echo "</tr>\n\n";
	echo "<tr>\n";
	echo "<td valign='top'  align='right'>Waarde</td>\n";
	echo "<td valign='top' ><input type='text' name='value' size='30' ";
	if (isset($posted_list["value"])){
		echo " value='".$posted_list["value"]."' ";
	}
	echo "></td>\n</tr>\n\n<tr>\n<td></td>\n";
	echo "<td valign='top'>";
	if(isset($error_list["value"])){
		echo $error_list["value"];
	}
	echo "</td>\n";
	echo "</tr>\n\n";
	echo "<tr>\n";
	echo "<td valign='top'  align='right'>Commentaar</td>\n";
	echo "<td valign='top'>";
	echo "<input type='text' name='comments' size='50' ";
	if (isset($posted_list["comments"])){
		echo " value='".$posted_list["comments"]."' ";
	}
	echo "</td>\n</tr>\n\n<tr>\n<td></td>\n";
	echo "<td></td>\n";
	echo "</tr>\n\n";

	echo "<tr>\n";
	echo "<td valign='top' align='right'></td>\n";
	echo "<td>";
	echo "<input type='checkbox' name='flag_public' ";
	if (trim($posted_list["flag_public"]) == 1){
		echo " CHECKED ";
	}

	echo " value='1' >Ja, dit contact mag zichtbaar zijn voor iedereen";

	echo "<tr>\n<td colspan='2' align='right'><input type='submit' name='zend' value='Opslaan'>";
	echo "</td>\n</tr>\n\n";
	echo "</table>\n\n</form></div>";

}

function redirect_view($uid){
	header("Location: view.php?id=$uid");
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
