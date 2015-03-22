<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_form.php");

include($rootpath."includes/inc_header.php");

$mode = $_GET["mode"];
$id = $_GET["id"];

$group = $_POST;

if ($mode == 'edit' && !$id)
{
	header('Location: overview.php');
	exit;
}

if ($_POST['zend'])
{
	if ($mode == 'edit')
	{
		if ($db->AutoExecute('letsgroups', $group, 'UPDATE', 'id=' . $id))
		{
			$alert->success('Letsgroep aangepast.');
			header('Location: overview.php');
			exit;
		}

		$alert->error('Letsgroep niet aangepast.');
	}
	else
	{
		if ($db->AutoExecute('letsgroups', $group, 'INSERT'))
		{
			$alert->success('Letsgroep opgeslagen.');
			header('Location: overview.php');
			exit;
		}

		$alert->error('Letsgroep niet opgeslagen.');
	}
}
else if ($mode == 'edit')
{
	$group = $db->GetRow('SELECT * FROM letsgroups WHERE id = ' . $id);
}

echo "<h1>LETS groep " . (($mode == 'new') ? 'toevoegen' : 'wijzigen') . '</h1>';

echo "<div class='border_b'><p>";
echo '<form method="post">';

echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>";

echo "<tr><td align='right' valign='top'>";
echo "Groepnaam";
echo "</td><td valign='top'>";
echo "<input type='text' name='groupname' value='" . $group['groupname'] . "' size='30' required>";
echo "</td></tr><tr><td valign='top'></td></tr>";

echo "<tr><td align='right' valign='top'>";
echo "Korte naam:<br><small><i>(kleine letters zonder spaties)</i></small>";
echo "</td><td valign='top'>";
echo "<input type='text' name='shortname' value='" . $group['shortname'] . "' size='30'>";
echo "</td></tr><tr><td valign='top'></td></tr>";

echo "<tr><td align='right' valign='top'>";
echo "Prefix:<br><small><i>(kleine letters zonder spaties)</i></small>";
echo "</td><td valign='top'>";
echo "<input type='text' name='prefix' value='" . $group['prefix'] . "' size='8'>";
echo "</td></tr><tr><td valign='top'></td></tr>";

echo "<tr><td align='right' valign='top'>";
echo "API Methode<br><small><i>(Type connectie naar de andere installatie)</i></small>";
echo "</td>\n<td valign='top'>";
echo "<select name='apimethod'>";
render_select_options(array(
	'elassoap'	=> 'eLAS naar eLAS (elassoap)',
	'internal'	=> 'Intern (eigen installatie)',
	'mail'		=> 'E-mail',
), $group['apimethod']);
echo "</select>";
echo "</td></tr><tr><td valign='top'></td></tr>";

echo "<tr><td align='right' valign='top'>";
echo "Remote API key";
echo "</td><td valign='top'>";
echo "<input type='text' name='remoteapikey' value='" . $group['remoteapikey'] . "' size='45'>";
echo "</td></tr><tr><td valign='top'></td></tr>";

echo "<tr><td align='right' valign='top'>";
echo "Lokale LETS code<br><small><i>(De letscode waarmee de andere groep op deze installatie bekend is)</i></small>";
echo "</td><td valign='top'>";
echo "<input type='text' name='localletscode' value='" . $group['localletscode'] . "' size='30'>";
echo "</td></tr><tr><td valign='top'></td></tr>";

echo "<tr><td align='right' valign='top'>";
echo "Remote LETS code<br><small><i>(De letscode waarmee deze groep bij de andere bekend is)</i></small>";
echo "</td>\n<td valign='top'>";
echo "<input type='text' name='myremoteletscode' value='" . $group['myremoteletscode'] . "' size='30'>";
echo "</td></tr><tr><td valign='top'></td></tr>";

echo "<tr><td align='right' valign='top'>";
echo "URL";
echo "</td>\n<td valign='top'>";
echo "<input type='url' name='url' value='" . $group['url'] . "' size='30'>";
echo "</td></tr><tr><td valign='top'></td></tr>";

echo "<tr><td align='right' valign='top'>";
echo "SOAP URL<br><small><i>(voor eLAS, de URL met /soap erachter)</i></small>";
echo "</td>\n<td valign='top'>";
echo "<input type='url' name='elassoapurl' value='" . $group['elassoapurl'] . "' size='30'>";
echo "</td></tr><tr><td valign='top'></td></tr>";

echo "<tr><td align='right' valign='top'>";
echo "Preshared key";
echo "</td><td valign='top'>";
echo "<input type='text' name='presharedkey' value='" . $group['presharedkey'] . "' size='30'>";
echo "</td>\n</tr>\n\n<tr>\n<td valign='top'></td></tr>";

echo "<tr><td></td><td>";
echo "<input type='submit' name='zend' value='Opslaan'>";
echo "</td></tr></table>";
echo "</form>";

include($rootpath."includes/inc_footer.php");
