<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");

$id = $_GET["id"];
if(empty($id))
{
	header('Location: overview.php');
	exit;
}

if(isset($_POST["zend"]))
{
	if($db->delete('news', array('id' => $id)))
	{
		$alert->success('Nieuwsbericht verwijderd.');
		header('Location: overview.php');
		exit;
	}
	$alert->error('Nieuwsbericht niet verwijderd.');
}

$news = $db->fetchAssoc('SELECT n.*, u.name, u.letscode
	FROM news n, users u  
	WHERE n.id = ?
	AND n.id_user = u.id', array($id));


$h1 = 'Nieuwsbericht ' . $news['headline'] . ' verwijderen?';
$fa = 'calendar';

include $rootpath . 'includes/inc_header.php';

echo "<div >";
echo "<strong>Agendadatum: ";
list($itemdate) = explode(' ', $news['itemdate']);
if(trim($itemdate) != "00/00/00"){
	echo $itemdate;
}
echo "<br>Locatie: " .$news["location"];
echo "</strong>";
echo "<br><i>Ingegeven door : ";
echo '<a href="' . $rootpath . 'memberlist_view.php?id=' . $news['id_user'] . '">';
echo htmlspecialchars($news["name"],ENT_QUOTES)." (".trim($news["letscode"]).")";
echo "</a></i>";
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
echo '<a href="' . $rootpath . 'news/overview.php" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';
echo '</form>';

echo '</div>';
echo '</div>';

include $rootpath. 'includes/inc_footer.php';
