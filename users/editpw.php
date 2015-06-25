<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_passwords.php");

if (!isset($_GET['id']))
{
	header("Location: overview.php");
	exit;
}

$id = $_GET['id'];

if(isset($_POST["zend"]))
{
	$pw = array();
	$pw["pw1"] = trim($_POST["pw1"]);
	$pw["pw2"] = trim($_POST["pw2"]);
	$errorlist = validate_input($pw);
	if (empty($errorlist))
	{
		$update["password"]=hash('sha512', $pw["pw1"]);
		$update["mdate"] = date("Y-m-d H:i:s");
		if ($db->AutoExecute("users", $update, 'UPDATE', "id=$id"))
		{
			readuser($id, true);
			$alert->success('Paswoord opgeslagen.');
			header('Location: view.php?id=' . $id);
			exit;
		}
	}
	$alert->error('Paswoord niet opgeslagen.');
}

$user = readuser($id);

$h1 = 'Paswoord aanpassen';
$fa = 'key';

include $rootpath . 'includes/inc_header.php';


echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post" class="form-horizontal">';

echo '<p>Gebruiker: ' . $user['letscode'] . ' ' . $user['fullname'] . '</p>';

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

echo '<a href="' . $rootpath . 'users/view.php?id=' . $id . '" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" value="Opslaan" name="zend" class="btn btn-primary">';

echo '</form>';

echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';

function validate_input($pw){
	$errorlist = array();
	if (empty($pw["pw1"]) || (trim($pw["pw1"]) == "")){
		$errorlist["pw1"] = "<font color='#F56DB5'>Vul <strong>paswoord</strong> in!</font>";
	}

	if (empty($pw["pw2"]) || (trim($pw["pw2"]) == "")){
		$errorlist["pw2"] = "<font color='#F56DB5'>Vul <strong>paswoord</strong> in!</font>";
	}
	if ($pw["pw1"] !== $pw["pw2"]){
	$errorlist["pw3"] = "<font color='#F56DB5'><strong>Paswoorden zijn niet identiek</strong>!</font>";
	}
	return $errorlist;
}
