<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_apikeys.php");

$posted_list = array();
$posted_list["apikey"] = $_POST["apikey"];
$posted_list["comment"] = $_POST["comment"];
$posted_list["type"] = $_POST["type"];

if ($_POST['zend'])
{
	if($db->AutoExecute("apikeys", $posted_list, 'INSERT')){
		$alert->success('Apikey opgeslagen.');
		header('Location: '.$rootpath.'apikeys/overview.php');
		exit;
	}
	$alert->error('Apikey niet opgeslagen.');
}

$mykey = generate_apikey();

include($rootpath."includes/inc_header.php");
echo "<h1>Apikey toevoegen</h1>";
echo "<div id='apikeydiv' class='border_b'>";
echo "<form method='post' >";
echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>";
echo "<tr><td align='right'>";
echo "Apikey";
echo "</td><td>";
echo "<input type='text' name='apikey' id='apikey' size='40' value='";
echo $mykey;
echo "' READONLY required>";
echo "</td></tr>";

echo "<tr><td align='right'>Type</td><td>";
echo "<select name='type'>";
echo "<option value='interlets' >Interlets</option>";
echo "</select>";
echo "</td></tr>";

echo "<tr><td align='right'>Comment</td><td>";
echo "<input type='text' name='comment' id='comment' size='50'>";
echo "</td></tr>";
echo "<tr><td></td><td>";
echo "<input type='submit' name='zend' id='zend' value='opslaan'>";
echo "</td></tr></table>";
echo "</form>";
echo "</div>";
include($rootpath."includes/inc_footer.php");


