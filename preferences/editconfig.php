<?php
ob_start();
$rootpath = "../";
$role='admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_transactions.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_mailfunctions.php");

$setting = $_GET["setting"];

if ($_POST['zend'])
{
	$value = $_POST['value'];
	if ($value != '')
	{
		if (writeconfig($setting, $value))
		{
			$alert->success('Instelling aangepast.');
			header('Location: config.php');
			exit;
		}
	}
	$alert->error('Instelling niet aangepast.');
}
else
{
	$config = $db->GetRow("SELECT * FROM config WHERE setting = '" . $setting ."'");
}

include($rootpath."includes/inc_header.php");

echo "<h1>Instelling $setting aanpassen</h1>";

echo "<div class='border_b'>";
echo "<form method='post'>";
echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>";
echo "<tr><td align='right'>";
echo "</td><td>";
echo "<input type='text' name='setting' value='". $setting . "' READONLY>";
echo '</td></tr>';
echo '<tr><td>Waarde</td><td>';
echo "<input type='text' name='value' size='40' value='" . $config["value"] . "'>";
echo "</td></tr>";
echo "<tr><td align='right'>";
echo "Omschrijving";
echo "</td><td><i>";
echo $config["description"];
echo "</i></td></tr>";
echo "<tr><td align='right'>";
echo "Commentaar";
echo "</td><td><i>";
echo $config["comment"];
echo "</i></td></tr>";
echo "<tr><td></td><td>";
echo "<input type='submit' name='zend' id='zend' value='opslaan'>";
echo "</td></tr></table>";
echo "</form>";
echo "</div>";

include($rootpath."includes/inc_footer.php");
