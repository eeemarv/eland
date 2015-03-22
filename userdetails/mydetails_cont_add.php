<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_form.php");

if (!isset($s_id)){
	header("Location: ".$rootpath."login.php");
	exit;
}

if(isset($_POST["zend"]))
{
	$posted_list = array();
	$posted_list["id_type_contact"] = $_POST["id_type_contact"];
	$posted_list["value"] = $_POST["value"];
	$posted_list["comments"] = $_POST["comments"];

	if (trim($_POST["flag_public"]) == 'on'){
			$posted_list["flag_public"] = 't';
	}else{
			$posted_list["flag_public"] = 'f';
	}

	$error_list = validate_input($posted_list);

	if(empty($error_list)){
		add_contact($s_id, $posted_list);
		$alert->success('Contact toegevoegd.');
		header('Location: mydetails.php');
		exit;
	}

	$alert->error('Contact niet toegevoegd.');
}

include $rootpath . 'includes/inc_header.php';
echo "<h1>Contact toevoegen</h1>";

$typecontacts = $db->GetAssoc('SELECT id, name FROM type_contact');

echo "<div class='border_b'>\n";
echo "<form method='POST' action='mydetails_cont_add.php'>\n";
echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>\n\n";
echo "<tr>\n";
echo "<td valign='top' align='right'>Type</td>\n";
echo "<td>";
echo "<select name='id_type_contact'>\n";
render_select_options($typecontacts, $posted_list['id_type_contact']);
echo "</select>\n</td>\n";

echo "</tr>\n\n<tr>\n<td></td>\n<td>";
if(isset($error_list["id_type_contact"])){
	echo $error_list["id_type_contact"];
}
echo "</td>\n";
echo "</tr>\n\n";

echo "<tr>\n";
echo "<td valign='top' align='right'>Waarde</td>\n";
echo "<td>";
echo "<input type='text' name='value' size='20' required ";
if (isset($posted_list["value"])){
	echo " value='".$posted_list["value"]."' ";
}
echo ">";
echo "</td>\n";
echo "</tr>\n\n<tr>\n<td></td>\n<td>";
if(isset($error_list["value"])){
	echo $error_list["value"];
}
echo "</td>\n";
echo "</tr>\n\n";

echo "<tr>\n";
echo "<td valign='top' align='right'>Commentaar</td>\n";
echo "<td>";
echo "<input type='text' name='comments' size='50' ";
if (isset($posted_list["comments"])){
	echo " value='".$posted_list["comments"]."' ";
}
echo "</td>\n";
echo "</tr>\n\n<tr>\n<td></td>\n<td>";
echo "</td>\n";
echo "</tr>\n\n";

echo "<tr>\n";
echo "<td valign='top' align='right'></td>\n";
echo "<td>";
echo "<input type='checkbox' name='flag_public' CHECKED";
echo " value='1' >Ja, dit contact mag zichtbaar zijn voor iedereen";

echo "</td>\n";
echo "</tr>\n\n<tr>\n<td></td>\n<td>";
echo "</td>\n";
echo "</tr>\n\n";

echo "<tr>\n<td colspan='2' align='right'><input type='submit' name='zend' value='Opslaan'>";
echo "</td>\n</tr>\n\n";
echo "</table></form></div>";

include($rootpath."includes/inc_footer.php");

////////////////////////

function validate_input($posted_list){
  	global $db;
	$error_list = array();
	if (empty($posted_list["value"]) || (trim($posted_list["value"]) == "")){
		$error_list["value"] = "<font color='#F56DB5'>Vul <strong>waarde</strong> in!</font>";
	}

	$query =" SELECT * FROM type_contact ";
	$query .=" WHERE  id = '".$posted_list["id_type_contact"]."' ";
	$rs = $db->Execute($query);
    $number = $rs->recordcount();
	if( $number == 0 ){
		$error_list["id_type_contact"]="<font color='#F56DB5'>Contacttype <strong>bestaat niet!</strong></font>";
	}
	return $error_list;
}


function show_form($typecontactrow, $error_list, $posted_list){
	echo "<div class='border_b'>\n";
	echo "<form method='POST' action='mydetails_cont_add.php'>\n";
	echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>\n\n";
	echo "<tr>\n";
	echo "<td valign='top' align='right'>Type</td>\n";
	echo "<td>";
	echo "<select name='id_type_contact'>\n";
	foreach($typecontactrow as $key => $value){
		echo "<option value='".$value["id"]."'>".$value["name"]."</option>\n";
	}
	echo "</select>\n</td>\n";

	echo "</tr>\n\n<tr>\n<td></td>\n<td>";
	if(isset($error_list["id_type_contact"])){
		echo $error_list["id_type_contact"];
	}
	echo "</td>\n";
	echo "</tr>\n\n";

	echo "<tr>\n";
	echo "<td valign='top' align='right'>Waarde</td>\n";
	echo "<td>";
	echo "<input type='text' name='value' size='20' ";
	if (isset($posted_list["value"])){
		echo " value='".$posted_list["value"]."' ";
	}
	echo ">";
	echo "</td>\n";
	echo "</tr>\n\n<tr>\n<td></td>\n<td>";
	if(isset($error_list["value"])){
		echo $error_list["value"];
	}
	echo "</td>\n";
	echo "</tr>\n\n";

	echo "<tr>\n";
	echo "<td valign='top' align='right'>Commentaar</td>\n";
	echo "<td>";
	echo "<input type='text' name='comments' size='50' ";
	if (isset($posted_list["comments"])){
		echo " value='".$posted_list["comments"]."' ";
	}
	echo "</td>\n";
	echo "</tr>\n\n<tr>\n<td></td>\n<td>";
	echo "</td>\n";
	echo "</tr>\n\n";

	echo "<tr>\n";
	echo "<td valign='top' align='right'></td>\n";
	echo "<td>";
	echo "<input type='checkbox' name='flag_public' CHECKED";
	echo " value='1' >Ja, dit contact mag zichtbaar zijn voor iedereen";

	echo "</td>\n";
	echo "</tr>\n\n<tr>\n<td></td>\n<td>";
	echo "</td>\n";
	echo "</tr>\n\n";

	echo "<tr>\n<td colspan='2' align='right'><input type='submit' name='zend' value='Opslaan'>";
	echo "</td>\n</tr>\n\n";
	echo "</table></form></div>";
}

function add_contact($s_id, $posted_list){
	global $db;
    $posted_list["id_user"] = $s_id;
    $result = $db->AutoExecute("contact", $posted_list, 'INSERT');
}
