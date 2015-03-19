<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

include($rootpath."includes/inc_header.php");

$id = $_GET["id"];
if(empty($id)){
	header('Location: overview.php');
	exit;
}

if(isset($_POST["zend"])){
	if($db->Execute("DELETE FROM news WHERE id =".$id))
	{
		$alert->success('Nieuwsbericht verwijderd.');
		header('Location: overview.php');
		exit;
	}
	$alert->error('Nieuwsbericht niet verwijderd.');
}

$query = 'SELECT n.*, u.name, u.letscode
	FROM news n, users u  
	WHERE n.id=' . $id . '
	AND n.id_user = u.id';
$news = $db->GetRow($query);

echo "<h1>Nieuwsbericht verwijderen?</h1>";

echo '<h2>' . $news['headline'] . '</h2>';

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
echo "</div>";
echo "</td></tr></table>";

echo "</p>";
echo "</div>";

echo "<p><font color='red'><strong>Ben je zeker dat dit nieuwsbericht";
echo " moet verwijderd worden?</strong></font></p>";


echo "<div><p><form method='POST'>";
echo "<input type='submit' value='Verwijderen' name='zend'>";
echo "</form></p>";
echo "</div>";

include($rootpath."includes/inc_footer.php");
