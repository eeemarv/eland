<?php
ob_start();
$rootpath = '../';
$role = 'user';
require_once $rootpath . 'includes/inc_default.php';

$mode = $_GET['mode'];
$id = $_GET['id'];

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
			$news['published'] = ($s_accountrole == 'admin') ? 't' : 'f';
			$news['id_user'] = $s_id;
			$news['cdate'] = date('Y-m-d H:i:s');
			
			if ($db->insert('news', $news))
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
				header('Location: view.php?id=' . $db->lastInsertId('news_id_seq'));
				exit;
			}
			else
			{
				$alert->error('Nieuwsbericht niet opgeslagen.');
			}
		}
		else if ($id)
		{
			if($db->update('news', $news, array('id' => $id)))
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
		$news = $db->fetchAssoc('SELECT * FROM news WHERE id = ?', array($id));
		list($news['itemdate']) = explode(' ', $news['itemdate']);
	}
	else
	{
		$news['itemdate'] = date("Y-m-d");
	}
}

$includejs = '
	<script src="' . $cdn_jquery . '"></script>
	<script src="' . $cdn_datepicker . '"></script>
	<script src="' . $cdn_datepicker_nl . '"></script>';

$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_datepicker_css . '" />';

$h1 = 'Nieuwsbericht ';
$h1 .= ($mode == 'new') ? 'toevoegen' : 'aanpassen';
$fa = 'calendar';


include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post" class="form-horizontal">';

echo '<div class="form-group">';
echo '<label for="itemdate" class="col-sm-2 control-label">Agendadatum (wanneer gaat dit door?)</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="itemdate" name="itemdate" ';
echo 'data-provide="datepicker" data-date-format="yyyy-mm-dd" ';
echo 'data-date-language="nl" ';
echo 'data-date-today-highlight="true" ';
echo 'data-date-autoclose="true" ';
echo ' value="' . $news['itemdate'] . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="location" class="col-sm-2 control-label">Locatie</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="location" name="location" ';
echo 'value="' . $news['location'] . '">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="headline" class="col-sm-2 control-label">Titel</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="headline" name="headline" ';
echo 'value="' . $news['headline'] . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="newsitem" class="col-sm-2 control-label">Bericht</label>';
echo '<div class="col-sm-10">';
echo '<textarea name="newsitem" id="newsitem" class="form-control" rows="10" required>';
echo $news['newsitem'];
echo '</textarea>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="sticky" class="col-sm-2 control-label">Behoud na datum</label>';
echo '<div class="col-sm-10">';
echo '<input type="checkbox" id="sticky" name="sticky" ';
echo 'value="1"';
echo  ($news['sticky'] == 't') ? ' checked="checked"' : '';
echo '>';
echo '</div>';
echo '</div>';

$btn = ($mode == 'new') ? 'success' : 'primary';
echo '<a href="' . $rootpath . 'news/overview.php" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-' . $btn . '">';

echo '</form>';

echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';

function validate_input($posted_list){
	$error_list = array();
	if (!isset($posted_list["headline"]) || (trim($posted_list["headline"]) == ""))
	{
		$error_list["headline"]="Titel is niet ingevuld";
	}
	return $error_list;
}
