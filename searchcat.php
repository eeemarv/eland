<?php
ob_start();
$rootpath = "";
$role = 'guest';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$q = $_GET['q'];

$cats = $db->GetArray('SELECT * FROM categories ORDER BY fullname');

if (in_array($s_accountrole, array('admin', 'user')))
{
	$top_buttons = '<a href="' . $rootpath . 'messages/edit.php?mode=new" class="btn btn-success"';
	$top_buttons .= ' title="Vraag of aanbod toevoegen"><i class="fa fa-plus"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';
}

$h1 = 'Vraag en aanbod';
$fa = 'newspaper-o';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="get" action="' . $rootpath . 'messages/search.php">';
echo '<div class="col-lg-12">';
echo '<div class="input-group">';
echo '<span class="input-group-btn">';
echo '<button class="btn btn-default" type="submit"><i class="fa fa-search"></i> Zoeken</button>';
echo '</span>';
echo '<input type="text" class="form-control" name="q">';
echo '</div>';
echo '</div>';
echo '<br><small><i>Een leeg zoekveld geeft ALLE V/A als resultaat terug</i></small>';
echo '</form>';

echo '</div>';
echo '</div>';

echo '<div class="table-responsive">';
echo '<table class="table table-striped table-hover table-bordered footable" data-sort="false">';
echo '<thead><tr>';
echo '<th>Categorie</td>';
echo '<th data-hide="phone" data-ignore="highlight">Vraag</td>';
echo '<th data-hide="phone" data-ignore="highlight">Aanbod</td>';
echo "</tr></thead>";

echo '<tbody>';

foreach($cats as $value)
{
	$class = ($value['id_parent']) ? '' : ' class="info"';
	echo '<tr' . $class . '>';
	echo '<td>';
	echo ($value['id_parent']) ? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' : '';
	echo "<a href='searchcat_viewcat.php?id=".$value["id"]."'>";
	echo htmlspecialchars($value['name'],ENT_QUOTES);
	echo "</a>";
	echo "</td>";

	echo '<td>' . (($v = $value['stat_msgs_wanted']) ? $v : '') . '</td>';
	echo '<td>' . (($v = $value['stat_msgs_offers']) ? $v : '') . '</td>';
	echo "</tr>";
}
echo '</tbody>';
echo "</table></div>";

if($s_accountrole != 'guest')
{
	$letsgroups = $db->Execute("SELECT * FROM letsgroups WHERE apimethod <> 'internal'");

	if (count($letsgroups))
	{
		echo '<h1>Interletsgroepen raadplegen</h1>';
		echo '<div class="table responsive">';
		echo '<table class="table talble-bordered table-striped table-hover" data-sort="false">';

		foreach($letsgroups as $key => $value)
		{
			echo "<tr><td nowrap>";
			echo '<a href="'. $rootpath . 'interlets/userview.php?letsgroup_id=' .$value['id'] . '&location=searchcat.php">' .$value['groupname'] . '</a>';
			echo "</td></tr>";
		}
		echo '</table>';
		echo '</div>';
	}
}

include $rootpath . 'includes/inc_footer.php';
