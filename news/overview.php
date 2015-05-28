<?php
ob_start();
$rootpath = "../";
$role = 'guest';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$query = 'SELECT * FROM news';

if($s_accountrole != "admin"){
	$query .= " where approved = True";
}

$query .= " ORDER BY cdate DESC";
$newsitems = $db->GetArray($query);

if($s_accountrole == 'user' || $s_accountrole == 'admin')
{
	$top_buttons = '<a href="' .$rootpath . 'news/edit.php?mode=new" class="btn btn-success"';
	$top_buttons .= ' title="nieuws toevoegen"><i class="fa fa-plus"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';
}

$h1 = 'Nieuws';
$fa = 'newspaper-o';

include $rootpath . 'includes/inc_header.php';

echo '<div class="table-responsive">';
echo '<table class="table table-striped table-hover table-bordered footable">';

echo '<thead>';
echo '<tr>';
echo '<th>Titel</th>';
echo '<th data-hide="phone" data-sort-initial="true">Agendadatum</th>';
echo ($s_accountrole == 'admin') ? '<th data-hide="phone, tablet">Goedgekeurd</th>' : '';
echo '</tr>';
echo '</thead>';

echo '<tbody>';
foreach ($newsitems as $value)
{
	echo '<tr>';

	echo "<td>";
	echo "<a href='view.php?id=".$value["id"]."'>";
	echo htmlspecialchars($value["headline"],ENT_QUOTES);
	echo "</a>";
	echo "</td>";

	echo '<td>';
	if(trim($value["itemdate"]) != "00/00/00")
	{
		list($date) = explode(' ', $value['itemdate']);
		echo $date;
	}
	echo "</td>";

	if ($s_accountrole == 'admin')
	{
		echo '<td>';
		echo ($value['approved'] == 't') ? 'Goedgekeurd' : 'Nee';
		echo '</td>';
	}
	echo "</tr>";
}
echo '</tbody>';
echo "</table></div>";

include $rootpath . 'includes/inc_footer.php';
