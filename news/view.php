<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");

if (!isset($_GET["id"]))
{
	header("Location: overview.php");
	exit;
}

$id = $_GET["id"];

$news = $db->fetchAssoc('SELECT n.*, u.name, u.letscode
	FROM news n, users u  
	WHERE n.id = ?
	AND n.id_user = u.id', array($id));

$top_buttons = '';

if($s_accountrole == 'user' || $s_accountrole == 'admin')
{
	$top_buttons .= '<a href="' .$rootpath . 'news/edit.php?mode=new" class="btn btn-success"';
	$top_buttons .= ' title="nieuws toevoegen"><i class="fa fa-plus"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

	if($s_accountrole == 'admin')
	{
		$top_buttons .= '<a href="' . $rootpath . 'news/edit.php?mode=edit&id=' . $id . '" class="btn btn-primary"';
		$top_buttons .= ' title="Nieuwsbericht aanpassen"><i class="fa fa-pencil"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Aanpassen</span></a>';

		$top_buttons .= '<a href="' . $rootpath . 'news/delete.php?id=' . $id . '" class="btn btn-danger"';
		$top_buttons .= ' title="Nieuwsbericht verwijderen">';
		$top_buttons .= '<i class="fa fa-times"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Verwijderen</span></a>';

		if ($news['appreved'] == 'f')
		{
			$top_buttons .= '<a href="' . $rootpath . 'news/activate.php?id=' . $id . '" class="btn btn-warning"';
			$top_buttons .= ' title="Nieuwsbericht goedkeuren">';
			$top_buttons .= '<i class="fa fa-ckeck"></i>';
			$top_buttons .= '<span class="hidden-xs hidden-sm"> Goedkeuren</span></a>';
		}
	}
}

$top_buttons .= '<a href="' .$rootpath . 'news/overview.php" class="btn btn-default"';
$top_buttons .= ' title="Lijst"><i class="fa fa-calendar"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Lijst</span></a>';

$h1 = 'Nieuwsbericht: ' . $news['headline'];
$fa = 'calendar';

include $rootpath . 'includes/inc_header.php';

echo '<dl>';
echo '<div class="panel panel-default">';
echo '<div class="panel-body">';
echo nl2br(htmlspecialchars($news["newsitem"],ENT_QUOTES));
echo '</div>';
echo '</div>';

echo '<dt>Agendadatum</dt>';
list($itemdate) = explode(' ', $news['itemdate']);
echo '<dd>' . $itemdate . '</dd>';

echo '<dt>Locatie</dt>';
echo '<dd>' . htmlspecialchars($news['location'], ENT_QUOTES) . '</dd>';

echo '<dt>Ingegeven door</dt>';
echo '<dd>';
echo '<a href="' . $rootpath . 'memberlist_view.php?id=' . $news['id_user'] . '">';
echo $news['letscode'] . ' ' . htmlspecialchars($news['name'],ENT_QUOTES);
echo '</a>';
echo '</dd>';

echo '<dt>Goedgekeurd</dt>';
echo '<dd>';
echo ($news['approved'] == 't') ? 'Ja' : 'Nee';
echo '</dd>';

echo '<dt>Behoud na datum?</dt>';
echo '<dd>';
echo ($news['sticky'] == 't') ? 'Ja' : 'Nee';
echo '</dd>';
echo '</dl>';

include $rootpath . 'includes/inc_footer.php';
