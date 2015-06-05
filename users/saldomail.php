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
				$msg = 'De saldo mail met recent vraag en aanbod is ge' . $de . 'activeerd voor alle actieve gebruikers.';
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

$h1 = 'Periodieke saldo mail';

include $rootpath . 'includes/inc_header.php';

echo '<h3>Periodieke saldo mail met overzicht nieuw vraag en aanbod activeren voor alle actieve gebruikers?</h3>';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post" class="form-horizontal">';

echo '<div class="form-group">';
echo '<label for="password" class="col-sm-2 control-label">Paswoord</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="password" name="password" ';
echo 'value="" required autocomplete="off">';
echo '</div>';
echo '</div>';

echo '<a href="' . $rootpath . 'users/overview.php" class="btn btn-default">Anneleren</a>&nbsp;';
echo '<input type="submit" name="activate" value="Activeren" class="btn btn-success">&nbsp;';
echo '<input type="submit" name="deactivate" value="Deactiveren" class="btn btn-danger">';

echo '</form>';

echo '</div>';
echo '</div>';

echo '<p>Opmerking: gebruikers kunnen steeds individueel de saldo mail aan- of uitzetten in hun instellingen.</p>';
include $rootpath . 'includes/inc_footer.php';
