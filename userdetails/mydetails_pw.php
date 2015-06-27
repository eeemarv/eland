<?php
ob_start();
$rootpath = '../';
$role = 'user';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';
require_once $rootpath . 'includes/inc_passwords.php';

if(isset($_POST["zend"]))
{
	$pw = array();
	$pw['pw1'] = trim($_POST['pw1']);
	$pw['pw2'] = trim($_POST['pw2']);
	$errorlist = validate_input($pw);
	if (empty($errorlist))
	{
		$update["password"] = hash('sha512', $pw["pw1"]);
		$update["mdate"] = date("Y-m-d H:i:s");
		if ($db->AutoExecute("users", $update, 'UPDATE', "id=$s_id"))
		{
			readuser($id, true);
			$alert->success('Paswoord opgeslagen.');
			header('Location: ' . $rootpath . 'userdetails/mydetails.php');
			exit;
		}
		else
		{
			$alert->error('Paswoord niet opgeslagen.');
		}
	}
	else
	{
		$alert->error(implode('<br>', $errorlist));
	}
}

$h1 = 'Mijn paswoord aanpassen';
$fa = 'key';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post" class="form-horizontal">';

echo '<div class="form-group">';
echo '<label for="pw1" class="col-sm-2 control-label">Paswoord</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="pw1" name="pw1" ';
echo 'value="' . $pw['pw1'] . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="pw2" class="col-sm-2 control-label">Herhaal paswoord</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="pw2" name="pw2" ';
echo 'value="' . $pw['pw2'] . '" required>';
echo '</div>';
echo '</div>';

echo '<a href="' . $rootpath . 'userdetails/mydetails.php" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" value="Opslaan" name="zend" class="btn btn-primary">';

echo '</form>';

echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';

function validate_input($pw)
{
	$errorlist = array();
	if (empty($pw['pw1']) || (trim($pw['pw1']) == ''))
	{
		$errorlist['pw1'] = 'Vul paswoord in!';
	}

	if (empty($pw['pw2']) || (trim($pw['pw2']) == ''))
	{
		$errorlist['pw2'] = 'Vul paswoord in!';
	}
	
	if ($pw['pw1'] !== $pw['pw2'])
	{
		$errorlist['pw3'] = 'Paswoorden zijn niet identiek!';
	}
	return $errorlist;
}
