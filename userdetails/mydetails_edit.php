<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$user = readuser($s_id);

if(isset($_POST["zend"])){
	$posted_list = array();

	$posted_list["ostatus_id"] = $_POST["ostatus_id"];
	$posted_list["postcode"] = $_POST["postcode"];
	$posted_list["birthday"] = $_POST["birthday"];
	$posted_list["login"] = $_POST["login"];
	$posted_list["cron_saldo"] =  ($_POST["cron_saldo"]) ? 't' : 'f';

	$posted_list["comments"] = $_POST["comments"];
	$posted_list["hobbies"] = $_POST["hobbies"];
	
	$error_list = validate_input($posted_list);

	if (empty($error_list))
	{
		$posted_list["mdate"] = date("Y-m-d H:i:s");

		if ($db->AutoExecute('users', $posted_list, 'UPDATE', 'id = ' . $s_id))
		{
			$alert->success('Je gegevens zijn aangepast.');
			readuser($s_id, true);
		}
		else
		{
			$alert->error('Je gegevens konden niet aangepast worden.');
		}
		header('Location: ' . $rootpath . 'userdetails/mydetails.php');
		exit;
	}

	foreach($errorlist as $error)
	{
		$alert->error($error);
	}

	$user = array_merge($user, $posted_list);
}

$includejs = '
	<script src="' . $cdn_jquery . '"></script>
	<script src="' . $cdn_datepicker . '"></script>
	<script src="' . $cdn_datepicker_nl . '"></script>';

$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_datepicker_css . '" >';

include($rootpath."includes/inc_header.php");

echo "<h1>Mijn gegevens aanpassen</h1>";

echo "<table cellpadding='0' cellspacing='0' border='0'>";
echo "<tr><td colspan='2'><strong>".$user["name"]." ";
echo $user["letscode"]."</strong></td></tr>";
echo "</table>";

echo "<div class='border_b'>";
echo "<form method='POST'>";
echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>";

echo "<tr><td valign='top' align='right'>Chatter ID</td><td>";
echo "<input  type='text' name='ostatus_id' size='40' ";
echo " value='";
echo $user["ostatus_id"];
echo "'>";
echo "</td></tr><tr><td></td>";

echo "<tr><td valign='top' align='right'>Postcode</td><td>";
echo "<input  type='text' name='postcode' size='40' ";
echo " value='";
echo $user["postcode"];
echo "'>";
echo "</td></tr><tr><td></td><td>";
if (isset($error_list["postcode"])){
	echo $error_list["postcode"];
}
echo "</td></tr>";

echo "<tr><td valign='top' align='right'>Verjaardag (jjjj-mm-dd):</td><td>";
echo "<input name='birthday' size='10'";
echo " value='" ;
echo $user["birthday"];
echo "' ";
echo 'data-provide="datepicker" data-date-format="yyyy-mm-dd" ';
echo 'data-date-default-view="2" ';
echo 'data-date-end-date="' . date('Y-m-d') . '" ';
echo 'data-date-language="nl" ';
echo 'data-date-start-view="2" ';
echo 'data-date-today-highlight="true" ';
echo 'data-date-autoclose="true" ';
echo 'data-date-immediate-updates="true" ';
echo ">";
echo "</td></tr>";

echo "<tr><td></td><td>";

echo "<tr><td valign='top' align='right'>Login:</td><td>";
echo "<input  type='text' name='login' size='40' ";
echo " value='";
echo htmlspecialchars($user["login"],ENT_QUOTES);
echo "' >";
echo "</td></tr><tr><td></td><td>";
if (isset($error_list["login"])){
	echo $error_list["login"];
}
echo "</td></tr>";

echo "<tr><td valign='top' align='right'>Hobbies/interesses:</td><td>";
echo "<textarea name='hobbies' cols='40' rows='2' >";
echo htmlspecialchars($user["hobbies"],ENT_QUOTES);
echo "</textarea>";
echo "</td></tr><tr><td></td><td>";
echo "</td></tr>";
echo "<tr><td valign='top' align='right'>Commentaar:</td><td>";
echo "<input type='text' name='comments' size='40' ";
echo " value='" ;
echo htmlspecialchars($user["comments"],ENT_QUOTES);
echo "'>";
echo "</td></tr><tr><td></td><td>";
echo "</td></tr>";

echo "<tr><td valign='top' align='right'>Saldo mail: </td><td>";
echo "<input type='checkbox' name='cron_saldo' value='1' ";
if ($user["cron_saldo"] == 't')
{
	echo ' checked="checked"';
}
echo ">";
echo " Mail mij periodiek mijn saldo en recent vraag en aanbod";
echo "</td></tr><tr><td></td><td></td></tr>";

echo "<tr><td></td><td>";
echo "<input type='submit' value='Opslaan' name='zend'>";
echo "</td></tr></table>";
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

	//login may not exist, except while editing your own record!
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


