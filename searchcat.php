<?php
ob_start();
$rootpath = "";
$role = 'guest';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if(!isset($s_id)){
	header("Location: ".$rootpath."login.php");
	exit;
}

$q = $_GET['q'];

include($rootpath."includes/inc_header.php");

if (in_array($s_accountrole, array('admin', 'user')))
{
	echo "<table><tr><td>";
	echo "<div id='navcontainer'>";
	echo "<ul class='hormenu'>";
	echo '<li><a href="' . $rootpath . 'messages/edit.php?mode=new">Vraag/Aanbod toevoegen</a></li>';
	echo "</ul>";
	echo "</div>";
	echo "</td></tr></table>";
}

echo "<h1>Vraag & Aanbod</h1>";

echo "<form method='get' action='$rootpath/messages/search.php'>";
echo "<input type='text' name='q' size='40' ";
echo " value='". $q ."'";
echo ">";
echo "<input type='submit' name='zend' value='Zoeken'>";
echo "<br><small><i>Een leeg zoekveld geeft ALLE V/A als resultaat terug</i></small>";
echo "</form>";

$cats = $db->GetArray('SELECT * FROM categories ORDER BY fullname');

echo '<div class="table-responsive">';
echo '<table class="table table-striped table-hover table-bordered footable">';
echo '<thead><tr>';
echo '<th>Categorie</td>';
echo '<th data-hide="phone" data-ignore="highlight">Vraag</td>';
echo '<th data-hide="phone" data-ignore="highlight">Aanbod</td>';
echo "</tr></thead>";

echo '<tbody>';

foreach($cats as $value){

	echo '<tr>';
	echo '<td>';
	echo ($value['id_parent']) ? '' : '<strong>';
	echo "<a href='searchcat_viewcat.php?id=".$value["id"]."'>";
	echo ($value['id_parent']) ? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' : '';
	echo htmlspecialchars($value['name'],ENT_QUOTES);
	echo "</a>";
	echo ($value['id_parent']) ? '' : '</strong>';
	echo "</td>";

	echo '<td>' . (($v = $value['stat_msgs_wanted']) ? $v : '') . '</td>';
	echo '<td>' . (($v = $value['stat_msgs_offers']) ? $v : '') . '</td>';
	echo "</tr>";
}
echo '</tbody>';
echo "</table></div>";

if($s_accountrole != 'guest'){
	echo "<h1>Andere (interlets) groepen raadplegen</h1>";
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1'>";
	$letsgroups = $db->Execute("SELECT * FROM letsgroups WHERE apimethod <> 'internal'");
	foreach($letsgroups as $key => $value)
	{
		echo "<tr><td nowrap>";
		echo '<a href="'. $rootpath . 'interlets/userview.php?letsgroup_id=' .$value['id'] . '&location=searchcat.php">' .$value['groupname'] . '</a>';
		echo "</td></tr>";
	}
	echo "</table>";
}
include($rootpath."includes/inc_footer.php");
