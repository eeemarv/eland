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
	$list_type_contact = get_type_contact();	
	$list_users = get_users();
	if (isset($_POST["zend"])){
		$posted_list = array();
		$posted_list["id_type_contact"] = $_POST["id_type_contact"];
		$posted_list["comments"] = $_POST["comments"];
		$posted_list["value"] = $_POST["value"];
		$posted_list["id_user"] = $_POST["id_user"];
		
		$error_list = validate_input($posted_list);
				
		if (!empty($error_list)){
			show_form($error_list, $posted_list, $list_users, $list_type_contact);	
		}else{
			insert_contact($posted_list);
			redirect_overview();
		}
	}else{
		show_form($error_list, $posted_list, $list_users, $list_type_contact);
	}

}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
////////////////////////////////F U N C T I E S ////////////////////////////
////////////////////////////////////////////////////////////////////////////
function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle(){
	echo "<h1>Nieuw contact ingeven</h1>";
}

function redirect_overview(){
	header("Location: overview.php");
}

function insert_contact($posted_list){
	global $db;
    $result = $db->AutoExecute("contact", $posted_list, 'INSERT');
}

function validate_input($posted_list){
	$error_list = array();
	//value may not be empty
	if (!isset($posted_list["value"])|| (trim($posted_list["value"])=="")){
		$error_list["value"]="<font color='#F56DB5'>Vul <strong>Waarde</strong> in!</font>";
	}
	
	//contacttype must exist
	global $db;
	$query =" SELECT * FROM type_contact ";
	$query .=" WHERE id = '".$posted_list["id_type_contact"]."' ";
	$rs = $db->Execute($query);
    $number = $rs->recordcount();
	if( $number == 0 ){
		$error_list["id_type_contact"]="<font color='#F56DB5'>Contacttype <strong>bestaat niet!</strong></font>";
	}
	
	//user must exist 
	$query = "SELECT * FROM users ";
	$query .= " WHERE id = '".$posted_list["id_user"]."'" ;
	$query .= " AND status <> '0'" ;
	$rs = $db->Execute($query);
    $number2 = $rs->recordcount();
	if( $number2 == 0 ){
		$error_list["id_user"]="<font color='#F56DB5'>Gebruiker <strong>bestaat niet!</strong></font>";
	}
	
	
	return $error_list;
}

function show_form($error_list, $posted_list, $list_users, $list_type_contact){
	echo "<div class='border_b'>";
	echo "<form method='POST' action='add.php'>\n\n";
	echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>\n";
	
	echo "<tr>\n<td  valign='top' align='right'>Type</td>\n";
	echo "<td>"; 
	echo "<select name='id_type_contact'>\n";
	foreach($list_type_contact as $value){
		if($posted_list["id_type_contact"] == $value["id"]){
			echo "<option value='" .$value["id"]. "' SELECTED>";
		}else{
			echo "<option value='" .$value["id"]. "'>";
		}
		echo $value["name"];
		echo "</option>\n";
	}
	echo "</select>\n";
	echo "</td>\n</tr>\n\n<tr>\n<td></td>\n<td>";
	if(isset($error_list["id_type_contact"])){
		echo $error_list["id_type_contact"];
	}
	echo "</td>\n</tr>\n\n";
	
	echo "<tr>\n<td valign='top' align='right'>Gebruiker</td>\n<td>";
	echo "<select name='id_user'>\n";
	foreach($list_users as $value2){
		if ($posted_list["id_user"] == $value2["id"]){
			echo "<option value='" .$value2["id"]. "' SELECTED>";
		}else{
			echo "<option value='".$value2["id"]."'>";
		}
		echo htmlspecialchars($value2["name"],ENT_QUOTES);
		echo "</option>\n";
	}
	echo "</select>\n";
	echo "</td></tr>\n\n<tr>\n<td></td>\n<td>";
			if(isset($error_list["id_user"])){
		echo $error_list["id_user"];
	}
	echo "</td>\n</tr>";
	
	echo "<tr>\n<td valign='top' align='right'>Waarde</td>\n<td>";
	echo "<input type='text' name='value' size='30' ";
	if (isset($posted_list["value"])){
		echo  "value ='".$posted_list["value"]."'>";
	}		
	echo "</td>\n</tr>\n\n<tr>\n<td></td>\n<td>";
	if(isset($error_list["value"])){
		echo $error_list["value"];
	}
	echo "</td>\n</tr>\n\n";
	
	echo "<tr>\n<td  valign='top' align='right'>Commentaar</td>\n<td>";
	echo "<input type='text' name='comments' size='30' ";
	if (isset($posted_list["comments"])){
		echo  "value ='".$posted_list["comments"]."'>";
	}		
	echo "</td>\n</tr>\n\n<tr>\n<td></td>\n<td></td>\n</tr>\n\n";
	echo "<tr>\n<td colspan='2'>";
	echo "<input type='submit' name='zend' value='OK'>";
	echo "</td>\n</tr>\n\n</table>\n";
	echo "</form>\n";
	echo "</p></div>";
}

function get_users(){
	global $db;
	$query = "SELECT * FROM users ";
	$query .= " WHERE status <> '0'" ;
	$list_users = $db->GetArray($query);
	return $list_users;
}

function get_type_contact(){
global $db;
	$query = "SELECT * FROM type_contact ";
	$list_type_contact = $db->GetArray($query);
	return $list_type_contact;
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>