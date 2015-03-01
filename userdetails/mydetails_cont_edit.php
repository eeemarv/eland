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


$id = $_GET["id"];
if (isset($s_id)){
	if(isset($id)){
		
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
			$posted_list["id"] = $_GET["id"];
			$posted_list["s_id"] = $_POST["s_id"];
			$posted_list["id_user"] = $posted_list["s_id"];
			$error_list = validate_input($posted_list,$s_id);
			if(!empty($error_list)){
				$contact = get_contact($id);
				$typecontactrow = get_type_contacts();
				show_form($s_id, $id, $contact, $typecontactrow, $error_list, $posted_list);	
			}else{
				update_contact($posted_list);
				redirect_mydetails_view();
			}
		}else{
			$contact = get_contact($id);
			$typecontactrow = get_type_contacts();
			if($contact["id_user"] != $s_id){
				echo "UNAUTHORIZED";
			} else {
				show_form($s_id, $id, $contact, $typecontactrow, $error_list, $posted_list);
			}
		}	
	}else{
		redirect_mydetails_view();
	}
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function update_contact($posted_list){
	global $db;
    $result = $db->AutoExecute("contact", $posted_list, 'UPDATE', 'id='.$posted_list["id"]);
}



function validate_input($posted_list,$s_id){
    global $db;
	$error_list = array();
	if (empty($posted_list["value"]) || (trim($posted_list["value"]) == "")){
		$error_list["value"] = "<font color='#F56DB5'>Vul <strong>waarde</strong> in!</font>";
	}
	
	$query = " SELECT * FROM type_contact ";
	$query .= " WHERE  id =  '".$posted_list["id_type_contact"]."'";
	$rs = $db->Execute($query);
    $number = $rs->recordcount();
	if( $number == 0 ){
$error_list["id_type_contact"]="<font color='#F56DB5'>Contacttype <strong>bestaat niet!</strong></font>";
	}

	if($posted_list["id_user"] != $s_id){
		$error_list["auth"] = "UNAUTHORIZED";
	}

	return $error_list;
}


function get_contact($id){
    global $db;
	$query = "SELECT * FROM contact WHERE id=".$id;
	$contact = $db->GetRow($query);
	return $contact;
}

function show_form($s_id, $id, $contact, $typecontactrow, $error_list, $posted_list){
	echo "<div class='border_b'>";
	echo "<form method='POST' action='mydetails_cont_edit.php?id=".$id."'>\n";
	echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>\n\n";
	
	echo "<tr>\n<td valign='top'><input type='hidden' name='s_id' value='".$s_id."'></td>\n";
	echo "<td></td>\n</tr>";

	echo "<tr>\n";
	echo "<td valign='top' align='right'>Type </td>\n";
	echo "<td>";
	echo "<select name='id_type_contact'>\n";
	
	foreach($typecontactrow as $key => $value){
		if($contact["id_type_contact"] == $value["id"]){
			echo "<option value='".$value["id"]."' SELECTED>".$value["name"]."</option>\n";
		}else{
			echo "<option value='".$value["id"]."' >".$value["name"]."</option>\n";
		}
	}
	echo "</select></td>\n";
	echo "</tr>\n\n<tr>\n<td></td>\n<td>";
	if(isset($error_list["id_type_contact"])){
		echo $error_list["id_type_contact"];
	}
	echo "</td>\n";
	echo "</tr>\n\n";
	
	echo "<tr>\n";
	echo "<td valign='top' align='right'>Waarde </td>\n";
	echo "<td><input type='text' name='value' size='20' ";
	if (isset($posted_list["value"])){
		echo " value='".$posted_list["value"]."' ";
	}else{
		echo " value='".$contact["value"]."' ";
	}
	echo "></td>\n";
	echo "</tr>\n\n<tr>\n<td></td>\n<td>";
		if(isset($error_list["value"])){
		echo $error_list["value"];
	}
	echo "</td>\n";
	echo "</tr>\n\n";
	
	echo "<tr>\n";
	echo "<td valign='top' align='right'>Commentaar </td>\n";
	echo "<td><input type='text' name='comments' size='30' ";
	if (isset($posted_list["comments"])){
		echo " value='".$posted_list["comments"]."' ";
	}else{
		echo " value='".$contact["comments"]."' ";
	}
	echo "</td>\n";
	echo "</tr>\n\n<tr>\n<td></td>\n<td></td>\n";
	echo "</tr>\n\n";


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

	echo "</td>\n";
	echo "</tr>\n\n<tr>\n<td></td>\n<td>";
	echo "</td>\n";
	echo "</tr>\n\n";


	echo "<tr>\n<td colspan='2' align='right'><input type='submit' name='zend' value='Opslaan'>\n</tr>\n\n";
	echo "</table>\n\n</form></div>";
}


function show_ptitle(){
	echo "<h1>Contact aanpassen</h1>";
}

function redirect_mydetails_view(){
	header("Location:  mydetails.php");
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


