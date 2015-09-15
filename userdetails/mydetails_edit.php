<?php
ob_start();
$rootpath = '../';
$role = 'user';
require_once $rootpath . 'includes/inc_default.php';

$user = readuser($s_id);

if(isset($_POST["zend"])){
	$posted_list = array();

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

		if ($db->update('users', $posted_list, array('id' => $s_id)))
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
	<script src="' . $cdn_datepicker . '"></script>
	<script src="' . $cdn_datepicker_nl . '"></script>';

$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_datepicker_css . '" >';

$h1 = 'Mijn gegevens aanpassen';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post" class="form-horizontal">';

echo '<div class="form-group">';
echo '<label for="name" class="col-sm-2 control-label">Naam</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="name" name="name" ';
echo 'value="' . $user['name'] . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="fullname" class="col-sm-2 control-label">Volledige naam (Voornaam en Achternaam)</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="fullname" name="fullname" ';
echo 'value="' . $user['fullname'] . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="postcode" class="col-sm-2 control-label">Postcode</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="postcode" name="postcode" ';
echo 'value="' . $user['postcode'] . '">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="birthday" class="col-sm-2 control-label">Geboortedatum (jjjj-mm-dd)</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="birthday" name="birthday" ';
echo 'value="' . $user['birthday'] . '" required ';
echo 'data-provide="datepicker" data-date-format="yyyy-mm-dd" ';
echo 'data-date-default-view="2" ';
echo 'data-date-end-date="' . date('Y-m-d') . '" ';
echo 'data-date-language="nl" ';
echo 'data-date-start-view="2" ';
echo 'data-date-today-highlight="true" ';
echo 'data-date-autoclose="true" ';
echo 'data-date-immediate-updates="true" ';
echo '>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="hobbies" class="col-sm-2 control-label">Hobbies, interesses</label>';
echo '<div class="col-sm-10">';
echo '<textarea name="hobbies" id="hobbies" class="form-control">';
echo $user['hobbies'];
echo '</textarea>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="comments" class="col-sm-2 control-label">Commentaar</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="comments" name="comments" ';
echo 'value="' . $user['comments'] . '">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="login" class="col-sm-2 control-label">Login</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="login" name="login" ';
echo 'value="' . $user['login'] . '">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="cron_saldo" class="col-sm-2 control-label">Mail mij periodiek mijn saldo en recent vraag en aanbod</label>';
echo '<div class="col-sm-10">';
echo '<input type="checkbox" name="cron_saldo" id="cron_saldo"';
echo ($user['cron_saldo'] == 't') ? ' checked="checked"' : '';
echo '>';
echo '</div>';
echo '</div>';

echo '<a href="' . $rootpath . 'userdetails/mydetails.php" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-primary">';

echo '</form>';

echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';

function validate_input($posted_list)
{
	global $db, $s_id;

	$error_list = array();

	//login may not be empty
	if (empty($posted_list["login"]) || (trim($posted_list["login"]) == ""))
	{
		$error_list['login'] = 'Vul login in!';
	}

	//login may not exist, except while editing your own record!
	if ($db->fetchColumn('select id from users where id <> ? and login = ?', array($s_id, $posted_list['login'])))
	{
		$error_list[] = 'Login bestaat al!';
	}

	return $error_list;
}


