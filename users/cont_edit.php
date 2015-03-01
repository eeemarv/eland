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

$uid = $_GET["uid"];
$cid = $_GET["cid"];
if(isset($s_id) && ($s_accountrole == "admin")){
	if(isset($cid)){
		show_ptitle();
		if(isset($_POST["zend"])){
			$posted_list = array();
			$posted_list["id_type_contact"] = $_POST["id_type_contact"];
			$posted_list["value"] = $_POST["value"];

			if (trim($_POST["flag_public"]) == 1){
					$posted_list["flag_public"] = 1;
			}else{
					$posted_list["flag_public"] = 0;
			}


			$posted_list["comments"] = $_POST["comments"];
			$uid = $_POST["uid"];
			$error_list = validate_input($posted_list);
			if(!empty($error_list)){
				$contact = get_contact($cid);
				$typecontactrow = get_type_contacts();
				show_form($cid, $uid, $contact, $typecontactrow, $error_list, $posted_list);	
			}else{
				update_contact($posted_list, $uid, $cid);
				redirect_view($uid);
			}
		}else{
			$contact = get_contact($cid);
			$typecontactrow = get_type_contacts();
			show_form($cid, $uid, $contact, $typecontactrow, $error_list, $posted_list);
		}	
	}else{
		redirect_view();
	}
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function update_contact($posted_list, $uid, $cid){
 	global $db;
    $posted_list["id_user"] = $uid;
    $result = $db->AutoExecute("contact", $posted_list, 'UPDATE', "id=$cid");
}

function validate_input($posted_list){
	global $db;
	$error_list = array();
	if (empty($posted_list["value"]) || (trim($posted_list["value"]) == "")){
		$error_list["value"] = "<font color='#F56DB5'>Vul <strong>waarde</strong> in!</font>";
	}
	
	$query =" SELECT * FROM type_contact ";
	$query .=" WHERE  id = '".$posted_list["id_type_contact"]."' ";
	$result = $db->GetArray($query);
	if( count($result)  == 0 ){
		$error_list["id_type_contact"]="<font color='#F56DB5'>Contacttype <strong>bestaat niet!</strong> </font>";
	}
	
	return $error_list;
}

function get_contact($cid){
	global $db;
	$query = "SELECT * FROM contact WHERE id=".$cid;
	$contact= $db->GetRow($query);
	return $contact;
}

function show_form($cid, $uid, $contact, $typecontactrow, $error_list, $posted_list){
echo "<div class='border_b'>";
	echo "<form method='POST' action='cont_edit.php?cid=".$cid."'>\n\n";
	echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>\n\n";
	echo "<tr>\n<td valign='top'><input type='hidden' name='uid' value='".$uid."'></td>\n";
	echo "<td></td>\n</tr>\n\n";
	echo "<tr>\n";
	echo "<td valign='top' align='right'>Type </td>\n";
	echo "<td>";
	echo "<select name='id_type_contact'>\n";
	foreach($typecontactrow as $key => $value){
		if($contact["id_type_contact"] == $value["id"]){
			echo "<option value='".$value["id"]."' SELECTED>".$value["name"]."</option>";
		}else{
			echo "<option value='".$value["id"]."' >".$value["name"]."</option>";
		}
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
	echo "<td valign='top' align='right'>Waarde </td>\n";
	echo "<td><input type='text' name='value' size='20' ";
	if (isset($posted_list["value"])){
		echo " value='".htmlspecialchars($posted_list["value"],ENT_QUOTES)."' ";
	}else{
		echo " value='".htmlspecialchars($contact["value"],ENT_QUOTES)."' ";
	}
	echo "></td>\n</tr>\n\n<tr>\n<td></td>\n";
	echo "<td>";
	if(isset($error_list["value"])){
		echo $error_list["value"];
	}
	echo "</td>\n";
	echo "</tr>\n\n";
	echo "<tr>\n";
	echo "<td valign='top' align='right'>Commentaar </td>\n";
	echo "<td><input type='text' name='comments' size='30' ";
	if (isset($posted_list["comments"])){
		echo " value='".htmlspecialchars($posted_list["comments"],ENT_QUOTES)."' ";
	}else{
		echo " value='".htmlspecialchars($contact["comments"],ENT_QUOTES)."' ";
	}
	echo "</td>\n</tr>\n\n<tr>\n<td></td>\n";
	echo "<td></td>";
	echo "</tr>";

	echo "<tr>\n";
	echo "<td valign='top' align='right'></td>\n";
	echo "<td>";
	echo "<input type='checkbox' name='flag_public' ";
	if (trim($posted_list["flag_public"]) == 1){
		echo " CHECKED ";
	} 
if (trim($contact["flag_public"]) == 1){
		echo " CHECKED ";
	} 
	echo " value='1' >Ja, dit contact mag zichtbaar zijn voor iedereen";


	echo "<tr>\n<td colspan='2' align='right'><input type='submit' name='zend' value='Oplaan'>";
	echo "</td>\n</tr>";
	echo "</table></form></div>";
}

function show_ptitle(){
	echo "<h1>Contact aanpassen</h1>";
}

function redirect_view($uid){
	header("Location: view.php?id=$uid");
}

function get_type_contacts(){
	global $db;
	$query = "SELECT * FROM type_contact ";
	$typecontactrow = $db->GetArray($query);
	return $typecontactrow;
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>


