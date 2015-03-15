<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

include($rootpath."includes/inc_header.php");

if(!isset($s_id)){
	header("Location: ".$rootpath."login.php");
	exit;
}

if(!$s_id){
	header('Location: ' . $rootpath . 'userdetails/mydetails.php');
}

$user = readuser($s_id);

if(isset($_POST["zend"])){
	$posted_list = array();

	$posted_list["ostatus_id"] = $_POST["ostatus_id"];
	$posted_list["postcode"] = $_POST["postcode"];
	$posted_list["birthday"] = $_POST["birthday"];
	$posted_list["login"] = $_POST["login"];
	if($_POST["cronsaldo"] == "on"){
		$posted_list["cron_saldo"] = 't';
	} else {
		$posted_list["cron_saldo"] = 'f';
	}

	$posted_list["comments"] = $_POST["comments"];
	$posted_list["hobbies"] = $_POST["hobbies"];
	$error_list = validate_input($posted_list);

	if (empty($error_list)){
		update_user($s_id, $posted_list);
		$alert->success('Je gegevens zijn aangepast.');
		header('Location: ' . $rootpath . 'userdetails/mydetails.php');
		exit;
	}

	foreach($errorlist as $error)
	{
		$alert->error($error);
	}

	$user = array_merge($user, $posted_list);
}

echo "<h1>Mijn gegevens aanpassen</h1>";

echo "<table cellpadding='0' cellspacing='0' border='0'>";
echo "<tr><td colspan='2'><strong>".$user["name"]." ";
echo $user["letscode"]."</strong></td></tr>";
echo "</table>";

echo "<div class='border_b'>";
echo "<form action='mydetails_edit.php?id=".$user["id"]."' method='POST'>";
echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>\n";

echo "<tr>\n<td valign='top' align='right'>Chatter ID</td>\n<td>";
echo "<input  type='text' name='ostatus_id' size='40' ";
echo " value='";
echo $user["ostatus_id"];
echo "'>";
echo "</td>\n</tr>\n\n<tr>\n<td></td>";

echo "<tr>\n<td valign='top' align='right'>Postcode</td>\n<td>";
echo "<input  type='text' name='postcode' size='40' ";
echo " value='";
echo $user["postcode"];
echo "'>";
echo "</td>\n</tr>\n\n<tr>\n<td></td>\n<td>";
if (isset($error_list["postcode"])){
	echo $error_list["postcode"];
}
echo "</td>\n</tr>\n\n";

echo "<tr>\n<td valign='top' align='right'>Verjaardag (jjjj-mm-dd):</td>\n<td>";
echo "<input name='birthday' size='10'";
echo " value='" ;
echo $user["birthday"];
echo "' >";
echo "</td>\n</tr>\n\n<tr>\n<td></td>\n<td>";

echo "</td>\n</tr>\n\n<tr>\n<td></td>\n<td>";
if (isset($error_list["adress"])){
	echo $error_list["adress"];
}
echo "</td>\n</tr>\n\n";

echo "<tr>\n<td valign='top' align='right'>Login:</td>\n<td>";
echo "<input  type='text' name='login' size='40' ";
echo " value='";
echo htmlspecialchars($user["login"],ENT_QUOTES);
echo "' >";
echo "</td>\n</tr>\n\n<tr>\n<td></td>\n<td>";
if (isset($error_list["login"])){
	echo $error_list["login"];
}
echo "</td>\n</tr>\n\n";

echo "<tr>\n<td valign='top' align='right'>Hobbies/interesses:</td>\n<td>";
echo "<textarea name='hobbies' cols='40' rows='2' >";
echo htmlspecialchars($user["hobbies"],ENT_QUOTES);
echo "</textarea>";
echo "</td>\n</tr>\n\n<tr>\n<td></td>\n<td>";
echo "</td>\n</tr>\n\n";
echo "<tr>\n<td valign='top' align='right'>Commentaar:</td>\n<td>";
echo "<input type='text' name='comments' size='60' ";
echo " value='" ;
echo htmlspecialchars($user["comments"],ENT_QUOTES);
echo "'>";
echo "</td>\n</tr>\n\n<tr>\n<td></td>\n<td>";
echo "</td>\n</tr>\n\n";
echo "<tr>\n<td valign='top' align='right'>Mail saldo: </td>\n<td>";
echo "<input type='checkbox' name='cronsaldo' ";
if ($user["cron_saldo"] == 't'){
		  echo ' checked="checked"';
}
echo ">";
echo " Mail mij periodiek mijn saldo door";
echo "</td>\n</tr>\n\n<tr>\n<td></td>\n<td></td>\n</tr>\n\n";
echo "<tr><td></td><td>";
echo "<input type='submit' value='Opslaan' name='zend'>";
echo "</td>\n</tr>\n\n</table>";
echo "</form>";
echo "</div>";

include $rootpath . 'includes/inc_footer.php';

//////////

function validate_input($posted_list){
global $db, $s_id;
	$error_list = array();

	//login may not be empty
	if (empty($posted_list["login"]) || (trim($posted_list["login"]) == "")){
		$error_list["login"] = "<font color='#F56DB5'>Vul <strong>login</strong> in!</font>";
	}

	//login may not exist, exept while editing your own record!
	$query = "SELECT * FROM users ";
	$query .= "WHERE login = '".$posted_list["login"]."' ";
	$query .= "AND id <> '".$s_id."' ";

	$rs = $db->Execute($query);
    $number = $rs->recordcount();
	if ($number !== 0){
		$error_list["login"]="<font color='#F56DB5'>Login bestaat al!</font>";
	}

	return $error_list;
}

function update_user($id, $posted_list){
	global $db;
    $posted_list["mdate"] = date("Y-m-d H:i:s");
    $result = $db->AutoExecute('users', $posted_list, 'UPDATE', ' id =' . $id);
    readuser($id, true);
    return $result;
}
