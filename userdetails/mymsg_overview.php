<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$query = "SELECT *, ";
$query .= " messages.id AS msgid, ";
$query .= " categories.id AS catid, ";
$query .= " messages.cdate AS date, ";
$query .= " messages.validity AS valdate, ";
$query .= "categories.name AS catname ";
$query .= " FROM messages, categories ";
$query .= " WHERE messages.id_user = $s_id";
$query .= " AND messages.id_category = categories.id";
$query .= " ORDER BY msg_type, content";
$messagerows = $db->GetArray($query);

include($rootpath."includes/inc_header.php");

echo "<ul class='hormenu'>";
echo '<li><a href="'. $rootpath . 'messages/edit.php?mode=new">Vraag/Aanbod toevoegen</a></li>';
echo "</ul>";


echo "<h1>Mijn Vraag & Aanbod</h1>";

echo '<div class="table-responsive">';
echo '<table class="table table-hover table-striped table-bordered footable">';
echo '<thead>';
echo '<tr>';
echo "<th>V/A</th>";
echo "<th>Wat</th>";
echo '<th data-hide="phone, tablet">Geldig tot</th>';
echo '<th data-hide="phone, tablet">Categorie</th>';
echo '<th data-hide="phone, tablet">Verlengen</th>';
echo "</tr>";
echo '</thead>';

echo '<tbody>';

foreach($messagerows as $key => $value)
{
	$del = (strtotime($value["valdate"]) < time()) ? true : false;

	echo '<tr';
	echo ($del) ? ' class="danger"' : '';
	echo '>';
	echo '<td>';

	echo ($value["msg_type"]) ? 'A' : 'V';
	echo '</td>';

	echo '<td>';
	echo ($del) ? '<del>' : '';
	echo "<a href='" .$rootpath ."messages/view.php?id=".$value["msgid"]."'>";

	echo htmlspecialchars($value["content"],ENT_QUOTES);
	echo ($del) ? '</del>' : '';

	echo "</td><td>";

	echo $value["valdate"];

	echo "</td>";

	echo "<td valign='top' >";
	echo htmlspecialchars($value["fullname"],ENT_QUOTES);
	echo "</td>";

	echo "<td valign='top'><a href='mymsg_extend.php?id=".$value["msgid"]."&validity=12'>1 jaar</a> | <a href='mymsg_extend.php?id=".$value["msgid"]."&validity=60'>5 jaar</a>";

	echo "</tr>";
}

echo '</tbody>';
echo "</table>";
echo "</div>";

function chop_string($content, $maxsize){
$strlength = strlen($content);
	if ($strlength >= $maxsize){
		$spacechar = strpos($content," ", 50);
		if($spacechar == 0){
			return $content;
		}else{
			return substr($content,0,$spacechar);
		}
	}else{
		return $content;
	}
}

include($rootpath."includes/inc_footer.php");
