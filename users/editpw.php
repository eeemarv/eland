<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_mailfunctions.php';

if (!isset($_GET['id']))
{
	header("Location: overview.php");
	exit;
}

$id = $_GET['id'];

$user = readuser($id);

if(isset($_POST["zend"]))
{
	$pw = trim($_POST['pw']);

	$errors = array();

	if (empty($pw) || (trim($pw) == ''))
	{
		$errors[] = 'Vul paswoord in!';
	}

	if (empty($errors))
	{
		$update['password']=hash('sha512', $pw);
		$update['mdate'] = date('Y-m-d H:i:s');
		if ($db->AutoExecute('users', $update, 'UPDATE', 'id=' . $id))
		{
			$user = readuser($id, true);
			$alert->success('Paswoord opgeslagen.');

			if ($user['status'] == 1 && $_POST['notify'])
			{
				$from = readconfigfromdb('from_address');
				$to = $db->GetOne('select c.value
					from contact c, type_contact tc
					where tc.id = c.id_type_contact
						and tc.abbrev = \'mail\'
						and c.id_user = ' . $id);

				if ($user['login'])
				{
					if ($to)
					{
						$http = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? "https://" : "http://";
						$port = ($_SERVER['SERVER_PORT'] == '80') ? '' : ':' . $_SERVER['SERVER_PORT'];
						$url = $http . $_SERVER["SERVER_NAME"] . $port . '?login=' . $user['login'];

						$subj = '[eLAS-' . readconfigfromdb('systemtag');
						$subj .= '] nieuw paswoord voor je account';

						$con = '*** Dit is een automatische mail van het eLAS systeem van ';
						$con .= readconfigfromdb('systemname');
						$con .= '. Niet beantwoorden astublieft. ';
						$con .= "***\n\n";
						$con .= 'Beste ' . $user['name'] . ',' . "\n\n";
						$con .= 'Er werd een nieuw paswoord voor je ingesteld.';
						$con .= "\n\n";
						$con .= 'Je kan inloggen op eLAS met de volgende gegevens:';
						$con .= "\n\nLogin: " . $user['login'];
						$con .= "\nPaswoord: " .$pw . "\n\n";
						$con .= 'eLAS adres waar je kan inloggen: ' . $url;
						$con .= "\n\n";
						$con .= 'Veel letsgenot!';
						sendemail($from, $to, $subj, $con);
						log_event($s_id, 'Mail', 'Pasword change notification mail sent to ' . $to);
						$alert->success('Notificatie mail verzonden naar ' . $to);
					}
					else
					{
						$alert->warning('Geen E-mail adres bekend voor deze gebruiker, stuur het paswoord op een andere manier door!');
					}
				}
				else
				{
					$alert->warning('Deze gebruiker heeft geen login! Er werd geen notificatie email verstuurd.');
				}
			}

			header('Location: view.php?id=' . $id);
			exit;
		}
		else
		{
			$alert->error('Paswoord niet opgeslagen.');
		}
	}
	else
	{
		$alert->error(implode('<br>', $errors));
	}

}

$status = array(
	0 => 'niet actief',
	1 => 'actief',
	2 => 'uitstapper',
	3 => 'instapper',		// not used
	4 => 'secretariaat',	// not used
	5 => 'info-pakket',
	6 => 'info-moment',
	7 => 'extern',
);

$includejs = '<script src="' . $rootpath . 'js/generate_password.js"></script>';

$h1 = 'Paswoord aanpassen';
$fa = 'key';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<p>Gebruiker: ' . $user['letscode'] . ' ' . $user['fullname'] . '</p>';

echo '<p>Status: ' . $status[$user['status']] . '</p>';

echo '<button class="btn btn-default" id="generate">Genereer automatisch</button>';
echo '<br><br>';

echo '<form method="post" class="form-horizontal">';

echo '<div class="form-group">';
echo '<label for="pw" class="col-sm-2 control-label">Paswoord</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="pw" name="pw" ';
echo 'value="' . $pw . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="notify" class="col-sm-2 control-label">Notificatie-mail (enkel mogelijk wanneer status actief en login ingesteld is)</label>';
echo '<div class="col-sm-10">';
echo '<input type="checkbox" name="notify" id="notify"';
echo ($user['status'] == 1 && $user['login']) ? ' checked="checked"' : ' readonly';
echo '>';
echo '</div>';
echo '</div>';

echo '<a href="' . $rootpath . 'users/view.php?id=' . $id . '" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" value="Opslaan" name="zend" class="btn btn-primary">';

echo '</form>';

echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';

