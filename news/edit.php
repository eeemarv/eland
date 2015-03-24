<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_mailfunctions.php");

$mode = $_GET["mode"];
$id = $_GET["id"];

$news = array();

if ($_POST['zend'])
{
	$news['itemdate'] = $_POST['itemdate'];
	$news['location'] = $_POST['location'];
	$news['sticky'] = ($_POST['sticky']) ? 't' : 'f';
	$news['newsitem'] = $_POST['newsitem'];
	$news['headline'] = $_POST['headline'];
	$errors = validate_input($news);
	if (!count($errors))
	{
		if ($mode == 'new')
		{
			$news['approved'] = ($s_accountrole == 'admin') ? 't' : 'f';
			$news['id_user'] = $s_id;
			$news['cdate'] = date('Y-m-d H:i:s');
			
			if ($db->AutoExecute('news', $news, 'INSERT'))
			{
				$alert->success('Nieuwsbericht opgeslagen.');
		 		if($s_accountrole != "admin"){
					// Send a notice to ask for approval
					$mailfrom = readconfigfromdb("from_address");
					$mailto = readconfigfromdb("newsadmin");
					$systemtag = readconfigfromdb("systemtag");
					$mailsubject = "[eLAS-".$systemtag."] Nieuwsbericht wacht op goedkeuring";
					$mailcontent .= "-- Dit is een automatische mail van het eLAS systeem, niet beantwoorden aub --\r\n";
					$mailcontent .= "\nEen lid gaf een nieuwsbericht met titel [";
					$mailcontent .= $news["headline"];
					$mailcontent .= "] in, dat bericht wacht op goedkeuring.  Log in als beheerder op eLAS en ga naar nieuws om het bericht goed te keuren.\n";
					sendemail($mailfrom,$mailto,$mailsubject,$mailcontent);
					echo "<br><strong>Bericht wacht op goedkeuring van een beheerder</strong>";
					$alert->success("Nieuwsbericht wacht op goedkeuring van een beheerder");
					header('Location: overview.php');
					exit;
				}
				header('Location: view.php?id=' . $db->insert_ID());
				exit;
			}
			else
			{
				$alert->error('Nieuwsbericht niet opgeslagen.');
			}
		}
		else if ($id)
		{
			if($db->AutoExecute('news', $news, 'UPDATE', 'id = ' . $id))
			{
				$alert->success('Nieuwsbericht aangepast.');
				header('Location: view.php?id=' . $id);
				exit;
			}
			else
			{
				$alert->error('Nieuwsbericht niet aangepast.');
			}
		}
		else
		{
			$alert->error('Update niet mogelijk zonder id.');
		}
	}
	else
	{
		$alert->error('Fout in formulier: ' . implode(' | ', $errors));
	}
}
else
{
	if ($mode == 'edit' && $id)
	{
		$news = $db->GetRow('SELECT * FROM news WHERE id = ' . $id);
		list($news['itemdate']) = explode(' ', $news['itemdate']);
	}
	else
	{
		$news['itemdate'] = date("Y-m-d");
	}
}

$includejs = '
	<script src="' . $cdn_jquery . '"></script>
	<script src="' . $cdn_jqueryui . '"></script>
	<script src="' . $cdn_jqueryui_i18n . '"></script>
	<script src="' . $rootpath . 'js/news_edit.js"></script>';

$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_jqueryui_css . '" />';

include($rootpath."includes/inc_header.php");

echo '<h1>Nieuwsbericht ';
echo ($mode == 'new') ? 'toevoegen' : 'aanpassen';
echo '</h1>';

echo "<div class='border_b'><p>";
echo "<table  class='data'  cellspacing='0' cellpadding='0' border='0'>";
echo "<form method='post'>";
echo "<tr><td width='10%' valign='top' align='right'>Agendadatum: <i>wanneer gaat dit door?</i></td><td>";
echo "<input type='date' name='itemdate' size='10' id='itemdate'";
echo  " value ='". $news['itemdate'] ."' required>";

echo "</td></tr>";
echo "<tr><td width='10%' valign='top' align='right'>Locatie</td><td>";
echo "<input type='text' name='location' size='40' value='" . $news['location'] . "'>";

echo "</td></tr><tr><td></td><td>";

echo "</td></tr>";

echo "<tr><td valign='top' align='right'>Titel </td><td>";
echo "<input type='text' name='headline' size='40' value='" . $news['headline'] . "' required>";
echo "</td></tr><tr><td></td><td>";
echo "</td></tr>";

echo "<tr><td valign='top' align='right'>Nieuwsbericht </td>";
echo "<td>";
echo "<textarea name='newsitem' cols='60' rows='15' required>";
echo $news['newsitem'];
echo "</textarea></td></tr><tr><td></td><td>";

echo "</td></tr>";
echo "<tr><td>Behoud na datum</td><td><input type='checkbox' name='sticky'";
echo ($news['sticky'] == 't') ? ' checked="checked"' : '';
echo "</td>";
echo "<tr><td></td><td>";
echo "<input type='submit' name='zend' id='zend' value='Opslaan'>";
echo "</td></tr></table>";
echo "</form>";
echo "</p></div>";

include($rootpath."includes/inc_footer.php");

//////////////////////

function validate_input($posted_list){
	$error_list = array();
	if (!isset($posted_list["headline"]) || (trim($posted_list["headline"]) == ""))
	{
		$error_list["headline"]="Titel is niet ingevuld";
	}
	return $error_list;
}
