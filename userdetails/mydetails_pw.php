<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_passwords.php");

if (!isset($s_id)){
	header("Location: ".$rootpath."login.php");
}

$errorlist = array();

if(isset($_POST["zend"])){

	$posted_list = array();
	$pw1 = $posted_list["pw1"] = $_POST["pw1"];
	$pw2 = $posted_list["pw2"] = $_POST["pw2"];
	$errorlist = validate_input($posted_list,$configuration);

	if (empty($errorlist))
	{
		if (update_password($s_id, $posted_list))
		{
			$alert->success('Paswoord opgeslagen');
			header('Location: '.$rootpath.'userdetails/mydetails.php');
			exit;
		}
		else
		{
			$alert->error('Fout, paswoord niet opgeslagen.');
		}
	}
	else
	{
		foreach ($errorlist as $error)
		{
			$alert->error($error);
		}
	}
}
else
{
	$pw1 = $pw2 = generatePassword(9); 
}

include $rootpath."includes/inc_header.php";

echo "<h1>Paswoord veranderen</h1>";

echo "<div class='border_b'>";
echo '<form method="post">';
echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>";
echo "<tr><td valign='top' align='right'>Paswoord</td>";
echo "<td valign='top'>";
echo '<input  type="text" name="pw1" size="30" value="' . $pw1 . '" >';
echo "</td>";
echo "</tr>";
echo "<tr><td valign='top' align='right'>Herhaal paswoord</td>";
echo "<td valign='top'>";
echo '<input  type="test" name="pw2" size="30" value="' . $pw2 . '" >';
echo "</td>";
echo "</tr>";
echo "<tr><td></td><td>";
echo "<input type='submit' id='zend' value='Passwoord wijzigen' name='zend'>";
echo "</td><td>&nbsp;</td></tr>";
echo "</table>";
echo "</form>";
echo "</div>";

include($rootpath."includes/inc_footer.php");

///////////////////////////////


function validate_input($posted_list){
	$errorlist = array();
	if (empty($posted_list["pw1"]) || (trim($posted_list["pw1"]) == "")){
		$errorlist["pw1"] = "Passwoord 1 is niet ingevuld";
	}

	$pwscore = Password_Strength($posted_list["pw1"]);
	$pwreqscore = readconfigfromdb("pwscore");
	if ($pwscore < $pwreqscore){
		$errorlist["pw1"] = "Paswoord is te zwak (score $pwscore/$pwreqscore), kies een passwoord dat lang genoeg is (8 tekens) en gebruik hoofdletters, cijfers en eventueel een leesteken";
	}
	if (empty($posted_list["pw2"]) || (trim($posted_list["pw2"]) == "")){
		$errorlist["pw2"] = "Passwoord 2 is niet ingevuld";
	}
	if ($posted_list["pw1"] !== $posted_list["pw2"]){
		$errorlist["pw3"] = "De 2 passwoorden zijn niet hetzelfde";
	}
	return $errorlist;
}
