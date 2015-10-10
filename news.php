<?php
ob_start();
$rootpath = './';

$approve = ($_GET['approve']) ?: false;
$edit = ($_GET['edit']) ?: false;
$add = ($_GET['add']) ?: false;
$del = ($_GET['del']) ?: false;
$id = ($_GET['id']) ?: false;
$submit = ($_POST['zend']) ? true : false;

if ($approve)
{	
	$role = 'admin';
	require_once $rootpath . 'includes/inc_default.php';

	if ($db->update('news', array('approved' => 't', 'published' => 't'), array('id' => $approve)))
	{
		$alert->success('Nieuwsbericht goedgekeurd');
	}
	else
	{
		$alert->error('Goedkeuren nieuwsbericht mislukt.');
	}
	header('Location: news.php?id=' . $approve);
	exit;
}

if ($add || $edit)
{
	$role = 'user';
	require_once $rootpath . 'includes/inc_default.php';

	$news = array();

	if ($submit)
	{
		$news = array(
			'itemdate'		=> $_POST['itemdate'],
			'location'		=> $_POST['location'],
			'sticky'		=> ($_POST['sticky']) ? 't' : 'f',
			'newsitem'		=> $_POST['newsitem'],
			'headline'		=> $_POST['headline'],
		);

		$errors = array();

		if (!isset($news['headline']) || (trim($news['headline']) == ''))
		{
			$errors[] = 'Titel is niet ingevuld';
		}
	}

	if (count($errors))
	{
		$alert->error(implode('<br>', $errors));
	}
}

if ($add && $submit && !count($errors))
{
	$news['approved'] = ($s_admin) ? 't' : 'f';
	$news['published'] = ($s_admin) ? 't' : 'f';
	$news['id_user'] = $s_id;
	$news['cdate'] = date('Y-m-d H:i:s');
	
	if ($db->insert('news', $news))
	{
		$alert->success('Nieuwsbericht opgeslagen.');

		$id = $db->lastInsertId('news_id_seq');
		if(!$s_admin)
		{
			// Send a notice to ask for approval
			$http = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? "https://" : "http://";
			$port = ($_SERVER['SERVER_PORT'] == '80') ? '' : ':' . $_SERVER['SERVER_PORT'];
			$url = $http . $_SERVER["SERVER_NAME"] . $port . '/news.php?id=' . $id;

			$from = readconfigfromdb('from_address');
			$to = readconfigfromdb('newsadmin');
			$systemtag = readconfigfromdb('systemtag');
			$subject = "[eLAS-".$systemtag."] Nieuwsbericht wacht op goedkeuring";
			$content .= "-- Dit is een automatische mail van het eLAS systeem, niet beantwoorden aub --\r\n";
			$content .= "\nEen lid gaf een nieuwsbericht met titel [";
			$content .= $news['headline'];
			$content .= "] in, dat bericht wacht op goedkeuring.  Log in als beheerder op eLAS en ga naar nieuws om het bericht goed te keuren.\n";
			$content .= 'link: ' .  $url . "\n";
			sendemail($from, $to, $subject, $content);
			echo '<br><strong>Bericht wacht op goedkeuring van een beheerder</strong>';
			$alert->success('Nieuwsbericht wacht op goedkeuring van een beheerder');
			header('Location: ' . $rootpath . 'news.php');
			exit;
		}
		header('Location: news.php?id=' . $id);
		exit;
	}
	else
	{
		$alert->error('Nieuwsbericht niet opgeslagen.');
	}
}

if ($edit && $submit && !count($errors))
{
	if($db->update('news', $news, array('id' => $edit)))
	{
		$alert->success('Nieuwsbericht aangepast.');
		header('Location: ' . $rootpath . 'news.php?id=' . $edit);
		exit;
	}
	else
	{
		$alert->error('Nieuwsbericht niet aangepast.');
	}
}

if ($edit)
{
	$news = $db->fetchAssoc('SELECT * FROM news WHERE id = ?', array($edit));
	list($news['itemdate']) = explode(' ', $news['itemdate']);
}

if ($add)
{
	$news['itemdate'] = date('Y-m-d');
}

if ($add || $edit)
{
	$includejs = '
		<script src="' . $cdn_jquery . '"></script>
		<script src="' . $cdn_datepicker . '"></script>
		<script src="' . $cdn_datepicker_nl . '"></script>';

	$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_datepicker_css . '" />';

	$h1 = 'Nieuwsbericht ';
	$h1 .= ($add) ? 'toevoegen' : 'aanpassen';
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

	$btn = ($add) ? 'success' : 'primary';
	echo '<a href="' . $rootpath . 'news.php" class="btn btn-default">Annuleren</a>&nbsp;';
	echo '<input type="submit" name="zend" value="Opslaan" class="btn btn-' . $btn . '">';

	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

if ($del)
{
	$role = 'admin';
	require_once $rootpath . 'includes/inc_default.php';

	if(isset($_POST['zend']))
	{
		if($db->delete('news', array('id' => $del)))
		{
			$alert->success('Nieuwsbericht verwijderd.');
			header('Location: ' . $rootpath . 'news.php');
			exit;
		}
		$alert->error('Nieuwsbericht niet verwijderd.');
	}

	$news = $db->fetchAssoc('SELECT n.*
		FROM news n  
		WHERE n.id = ?', array($del));

	$h1 = 'Nieuwsbericht ' . $news['headline'] . ' verwijderen?';
	$fa = 'calendar';

	include $rootpath . 'includes/inc_header.php';

	echo '<div >';
	echo '<strong>Agendadatum: ';
	list($itemdate) = explode(' ', $news['itemdate']);
	if(trim($itemdate) != "00/00/00")
	{
		echo $itemdate;
	}
	echo "<br>Locatie: " .$news["location"];
	echo "</strong>";
	echo "<br><i>Ingegeven door : ";
	echo link_user($news['id_user']);
	echo "</i>";
	echo ($news['approved'] == 't') ? '<br><i>Goedgekeurd.</i>' : '<br><i>Nog niet goedgekeurd.</i>';
	echo ($news['sticky'] == 't') ? '<br><i>Behoud na datum.</i>' : '<br><i>Wordt verwijderd na datum.</i>';

	echo "<p>";
	echo nl2br(htmlspecialchars($news["newsitem"],ENT_QUOTES));
	echo "</p>";

	echo "<table width='100%' border=0><tr><td>";
	echo "<div id='navcontainer'>";
	echo "</div>";
	echo "</td></tr></table>";

	echo "</p>";
	echo "</div>";

	echo "<p><font color='red'><strong>Ben je zeker dat dit nieuwsbericht";
	echo " moet verwijderd worden?</strong></font></p>";

	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo '<form method="post">';
	echo '<a href="' . $rootpath . 'news.php" class="btn btn-default">Annuleren</a>&nbsp;';
	echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
	echo '</form>';

	echo '</div>';
	echo '</div>';

	include $rootpath. 'includes/inc_footer.php';
	exit;
}

if ($id)
{
	$role = 'guest';
	require_once $rootpath . 'includes/inc_default.php';

	$news = $db->fetchAssoc('SELECT n.*
		FROM news n  
		WHERE n.id = ?', array($id));

	$top_buttons = '';

	if($s_user || $s_admin)
	{
		$top_buttons .= '<a href="' .$rootpath . 'news.php?add=1" class="btn btn-success"';
		$top_buttons .= ' title="nieuws toevoegen"><i class="fa fa-plus"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

		if($s_admin)
		{
			$top_buttons .= '<a href="' . $rootpath . 'news.php?edit=' . $id . '" class="btn btn-primary"';
			$top_buttons .= ' title="Nieuwsbericht aanpassen"><i class="fa fa-pencil"></i>';
			$top_buttons .= '<span class="hidden-xs hidden-sm"> Aanpassen</span></a>';

			$top_buttons .= '<a href="' . $rootpath . 'news.php?del=' . $id . '" class="btn btn-danger"';
			$top_buttons .= ' title="Nieuwsbericht verwijderen">';
			$top_buttons .= '<i class="fa fa-times"></i>';
			$top_buttons .= '<span class="hidden-xs hidden-sm"> Verwijderen</span></a>';

			if (!$news['approved'])
			{
				$top_buttons .= '<a href="' . $rootpath . 'news.php?approve=' . $id . '" class="btn btn-warning"';
				$top_buttons .= ' title="Nieuwsbericht goedkeuren">';
				$top_buttons .= '<i class="fa fa-check"></i>';
				$top_buttons .= '<span class="hidden-xs hidden-sm"> Goedkeuren</span></a>';
			}
		}
	}

	$top_buttons .= '<a href="' .$rootpath . 'news.php" class="btn btn-default"';
	$top_buttons .= ' title="Lijst"><i class="fa fa-calendar"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Lijst</span></a>';

	$h1 = 'Nieuwsbericht: ' . $news['headline'];
	$fa = 'calendar';

	include $rootpath . 'includes/inc_header.php';

	echo '<div class="panel panel-default">';
	echo '<div class="panel-heading">';

	echo '<p>Bericht</p>';
	echo '</div>';
	echo '<div class="panel-body">';
	echo nl2br(htmlspecialchars($news['newsitem'],ENT_QUOTES));
	echo '</div></div>';

	echo '<div class="panel panel-default">';
	echo '<div class="panel-heading">';
	
	echo '<dl>';
	echo '<dt>Agendadatum</dt>';
	list($itemdate) = explode(' ', $news['itemdate']);
	echo '<dd>' . $itemdate . '</dd>';

	echo '<dt>Locatie</dt>';
	echo '<dd>' . htmlspecialchars($news['location'], ENT_QUOTES) . '</dd>';

	echo '<dt>Ingegeven door</dt>';
	echo '<dd>';
	echo link_user($news['id_user']);
	echo '</dd>';

	if ($s_admin)
	{
		echo '<dt>Goedgekeurd</dt>';
		echo '<dd>';
		echo ($news['approved']) ? 'Ja' : 'Nee';
		echo '</dd>';

		echo '<dt>Behoud na datum?</dt>';
		echo '<dd>';
		echo ($news['sticky']) ? 'Ja' : 'Nee';
		echo '</dd>';
		echo '</dl>';
	}

	echo '</div>';
	echo '</div>';

	include $rootpath . 'includes/inc_footer.php';
	exit;
}

$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';

$query = 'SELECT * FROM news';

if($s_accountrole != 'admin')
{
	$query .= ' where approved = \'t\'';
}

$query .= ' ORDER BY cdate DESC';

$news = $db->fetchAll($query);

if($s_user || $s_admin)
{
	$top_buttons = '<a href="' .$rootpath . 'news.php?add=1" class="btn btn-success"';
	$top_buttons .= ' title="nieuws toevoegen"><i class="fa fa-plus"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';
}

$h1 = 'Nieuws';
$fa = 'calendar';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-warning">';
echo '<div class="table-responsive">';
echo '<table class="table table-striped table-hover table-bordered footable">';

echo '<thead>';
echo '<tr>';
echo '<th>Titel</th>';
echo '<th data-hide="phone" data-sort-initial="true">Agendadatum</th>';
echo ($s_admin) ? '<th data-hide="phone, tablet">Goedgekeurd</th>' : '';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach ($news as $value)
{
	echo '<tr>';

	echo '<td>';
	echo '<a href="' . $rootpath . 'news.php?id=' . $value['id'] . '">';
	echo htmlspecialchars($value['headline'],ENT_QUOTES);
	echo '</a>';
	echo '</td>';

	echo '<td>';
	if(trim($value['itemdate']) != '00/00/00')
	{
		list($date) = explode(' ', $value['itemdate']);
		echo $date;
	}
	echo '</td>';

	if ($s_admin)
	{
		echo '<td>';
		echo ($value['approved']) ? 'Ja' : 'Nee';
		echo '</td>';
	}
	echo '</tr>';
}
echo '</tbody>';
echo '</table></div></div>';

include $rootpath . 'includes/inc_footer.php';
