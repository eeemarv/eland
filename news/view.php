<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

include($rootpath."includes/inc_header.php");

if (!isset($_GET["id"])){
	header("Location: overview.php");
	exit;
}

$id = $_GET["id"];

$query = 'SELECT n.*, u.name, u.letscode
	FROM news n, users u  
	WHERE n.id=' . $id . '
	AND n.id_user = u.id';
$news = $db->GetRow($query);

echo '<h1>Nieuwsbericht: ' . $news['headline'] . '</h1>';

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

echo "<p>";
echo "<table width='100%' border=0><tr><td>";
echo "<div id='navcontainer'>";
echo "<ul class='hormenu'>";
if($s_accountrole == 'admin')
{
	echo "<li><a href='" . $rootpath . "news/edit.php?mode=edit&id=" .$news["id"] . "'>Aanpassen</a></li>";
	echo '<li><a href="approve.php?id=' . $news['id']. '">Goedkeuren</a></li>';
	echo '<li><a href="delete.php?id=' . $news['id'] . '">Verwijderen</a></li>';
}
echo "</ul>";
echo "</div>";
echo "</td></tr></table>";

echo "</p>";
echo "</div>";

include($rootpath."includes/inc_footer.php");
