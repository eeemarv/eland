<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

include($rootpath."includes/inc_header.php");

show_ptitle();
$id = $_GET["id"];
if(isset($id)){
	$list_users = get_users();
	$list_type_contact = get_type_contact();
	if(isset($_POST["zend"])){
		$posted_list = array();
		$posted_list["id_type_contact"] = trim($_POST["id_type_contact"]);
		$posted_list["comments"] = $_POST["comments"];
		$posted_list["value"] = trim($_POST["value"]);
		$posted_list["id_user"] = $_POST["id_user"];
		$posted_list["id"] = $_GET["id"];
		$error_list = validate_input($posted_list);

		if (!empty($error_list)){
			show_form($posted_list, $error_list, $list_users, $list_type_contact);
		}else{
			update_contact($id, $posted_list);
			redirect_overview();
		}
	}else{
		$contact = get_contact($id);
		show_form($contact, $error_list, $list_users, $list_type_contact);
	}
}else{
	redirect_overview();
}


function show_ptitle(){
	echo "<h1>Contact aanpassen</h1>";
}

function validate_input($posted_list){
	$error_list = array();
	if (!isset($posted_list["value"])|| (trim($posted_list["value"])=="")){
		$error_list["value"]="<font color='#F56DB5'>Vul <strong>Waarde</strong> in!</font>";
	}

	//contacttype must exist
	global $db;
	$query = " SELECT * FROM type_contact ";
	$query .= " WHERE id= '".$posted_list["id_type_contact"]."'";
	$rs = $db->Execute($query);
	$number=$rs->recordcount();
	if( $number == 0 ){
		$error_list["id_type_contact"]="<font color='#F56DB5'>Contacttype <strong>bestaat niet!</strong></font>";
	}

	//user must exist ".$posted_list["id_user"]."
	$query2 = "SELECT * FROM users ";
	$query2 .= " WHERE id = '".$posted_list["id_user"]."'" ;
	$query2 .= " AND status <> '0'" ;
	$rs = $db->Execute($query);
	$number2 = $rs->recordcount();
	if( $number2 == 0 ){
		$error_list["id_user"]="<font color='#F56DB5'>Gebruiker <strong>bestaat niet!</strong></font>";
	}

	return $error_list;
}

function update_contact($id, $posted_list){
	global $db;
	$result = $db->AutoExecute("contact", $posted_list, 'UPDATE' , "id=$id");

}

function show_form($contact, $error_list, $list_users, $list_type_contact){
	echo "<div class='border_b'><p>";
	echo "<form action='edit.php?id=".$contact["id"]."' method='POST'>\n";
	echo "<table  class='data' cellspacing='0' cellpadding='0' border='0'>\n\n";

	echo "<tr>\n<td valign='top' align='right'>";
	echo "Type:";
	echo "</td>\n<td>";
	echo "<select name='id_type_contact'>\n";
	foreach($list_type_contact as $value2){
		if($contact["id_type_contact"] ==  $value2["id"]){
			echo "<option value='" .$value2["id"]. "' SELECTED>";
		}else{
			echo "<option value='".$value2["id"]."'>";
		}
		echo htmlspecialchars($value2["name"],ENT_QUOTES);
		echo "</option>\n";
	}
	echo "</select>\n";
	echo "</td></tr>\n\n<tr>\n<td></td>\n<td>";
		if(isset($error_list["id_type_contact"])){
		echo $error_list["id_type_contact"];
	}
	echo "</td>\n</tr>\n\n";

	echo "<tr>\n<td valign='top' align='right'>Gebruiker</td>\n<td>";
	echo "<select name='id_user'>\n";
	foreach($list_users as $value){
		if($contact["id_user"] == $value["id"]){
			echo "<option value='" .$value["id"]. "' SELECTED>";
		}else{
			echo "<option value='" .$value["id"]. "'>";
		}
		echo htmlspecialchars($value["name"],ENT_QUOTES);
		echo "</option>\n";
	}
	echo "</select>\n";
	echo "</td>\n</tr>\n\n<tr>\n<td></td>\n<td>";
	if(isset($error_list["id_user"])){
		echo $error_list["id_user"];
	}
	echo "</td>\n</tr>\n\n";

	echo "<tr>\n<td valign='top' align='right'>Waarde</td>\n<td>";
	echo "<input type='text' name='value' size='40' ";
	echo "value='". htmlspecialchars($contact["value"],ENT_QUOTES). "' required>";
	echo "</td>\n</tr>\n\n<tr>\n<td></td>\n<td valign='top'>";
	if (isset($error_list["value"])){
		echo $error_list["value"];
	}
	echo "</td>\n</tr>\n\n";

	echo "<tr>\n<td valign='top' align='right'>Commentaar </td>\n<td>";
	echo "<input type='text' name='comments' size='40' ";
	echo "value='". htmlspecialchars($contact["comments"],ENT_QUOTES). "'>";
	echo "</td>\n</tr>\n\n<tr>\n<td></td>\n<td></td>\n</tr>\n\n";

	echo "<tr>\n<td colspan='2'>";
	echo "<input type='submit' value='OK' name='zend'>";
	echo "</td>\n</tr>\n\n</table>\n";
	echo "</form>";
	echo "</p></div>";
}

function get_users(){
	global $db;
	$query = "SELECT * FROM users";
	$query .= " WHERE status <> '0'" ;
	$result = mysql_query($query) or die("Geen selectie");
	$list_users = $db->GetArray($query);
	return $list_users;
}

function get_type_contact(){
	global $db;
	$query = "SELECT * FROM type_contact ";
	$result = mysql_query($query) or die("Geen selectie");
	$list_type_contact = $db->GetArray($query);
	return $list_type_contact;
}

function get_contact($id){
	global $db;
	$query = "SELECT * FROM contact WHERE id=".$id;
	$contact = $db->GetRow($query);
	return $contact;
}

function redirect_overview(){
	header("Location: overview.php");
}

include($rootpath."includes/inc_footer.php");
