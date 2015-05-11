<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if ($_POST['cancel'])
{
	header('Location: ' . $rootpath . 'users/overview.php');
	exit;
}

$password = $_POST['password'];
$activate = ($_POST['activate']) ? true : false;
$deactivate = ($_POST['deactivate']) ? true : false;

if ($activate || $deactivate)
{
	if ($password)
	{
		$sha512 = hash('sha512', $password);

		if ($sha512 == $db->GetOne('select password from users where id = ' . $s_id))
		{
			$bool = ($activate) ? 't' : 'f';
			$de = ($activate) ? '' : 'de';

			if ($db->Execute('update users set cron_saldo = \'' . $bool . '\' where status in (1, 2)'))
			{
				$msg = 'De saldo mail met recent vraag en aanbod is ge' . $de . 'activeerd voor alle activieve gebruikers.';
				$alert->success($msg);
				log_event($s_id, 'update', $msg);
				header('Location: ' . $rootpath . 'users/overview.php');
				exit;
			}
			else
			{
				$alert->error('Fout, saldo mail niet ge' . $de . 'activeerd');
			}
		}
		else
		{
			$alert->error('Paswoord is niet correct.');
		}
	}
	else
	{
		$alert->error('Paswoord is niet ingevuld.');
	}
}

include($rootpath."includes/inc_header.php");

echo "<h1>Periodieke saldo mail met overzicht nieuw vraag en aanbod activeren voor alle actieve gebruikers?</h1>";

echo "<div class='border_b'>";
echo "<form method='POST'>";
echo "<table class='data'>";
echo "<tr><td>Paswoord:</td><td>";
echo '<input type="password" name="password" value="" autocomplete="off">';
echo "</td></tr>";
echo "<tr><td colspan='2'>";
echo "<input type='submit' name='cancel' value='Anneleren'>&nbsp;";
echo "<input type='submit' name='activate' value='Activeren'>&nbsp;";
echo "<input type='submit' name='deactivate' value='Deactiveren'>";
echo "</td></tr>";
echo "</table></form></div>";
echo '<p>Opmerking: gebruikers kunnen steeds individueel de saldo mail aan- of uitzetten in hun instellingen.</p>';
include($rootpath."includes/inc_footer.php");
