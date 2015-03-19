<?php
ob_start();
$rootpath = "";
$role = 'guest';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_form.php");

include($rootpath."includes/inc_header.php");

//echo "<script type='text/javascript' src='$rootpath/js/moomemberlist.js'></script>";

$prefix = ($_POST["prefix"]) ?: 'ALL';
$posted_list["prefix"] = $prefix;
$searchname = $_POST["searchname"];
$sort = $_POST["sort"];

$sort = ($sort) ? $sort : 'letscode';

echo "<h1>Contactlijst</h1>";

echo "<table width='100%' border=0><tr><td>";
echo "<form method='POST' id='memberselect'>";

echo "<table  class='selectbox'>\n";

echo "<tr>\n<td>";
echo "Groep:";
echo "</td><td>\n";
echo "<select name='prefix'>\n";

$query = "SELECT prefix, shortname FROM letsgroups WHERE apimethod ='internal' AND prefix IS NOT NULL";
$prefixes = $db->GetAssoc($query);
$prefixes['ALL'] = 'ALLE';
render_select_options($prefixes, $prefix);

echo "</select>\n";
echo "</td>\n";
echo "</tr>";

echo "<tr><td>Naam:</td><td>\n";
echo "<input type='text' name='searchname' value='" . $searchname . "' size='25'>";
echo "</td>";
echo "</tr>";

echo "<tr>\n<td>Sorteer:</td><td>\n";
echo "<select name='sort'>\n";
echo $sort_options = array(
	'letscode' => 'letscode',
	'fullname' => 'naam',
	'postcode' => 'postcode',
	'saldo' => 'saldo');
render_select_options($sort_options, $sort);
echo "</select>\n";
echo "</td>\n";
echo "</tr>";

echo "<tr><td align='right' colspan=2>";
echo "<input type='submit' name='zend' value='Weergeven'>";
echo "</td>";
echo "</tr>";
echo "</table>";
echo "</form>";

//rendermembers
echo "<td align='right'>";

echo "<a href='print_memberlist.php?prefix_filterby=" .$prefix_filterby . "'>";
echo "<img src='".$rootpath."gfx/print.gif' border='0'> ";
echo "Printversie</a>";

echo "<a href='csv_memberlist.php?prefix_filterby=" .$prefix_filterby;
echo "' target='new'>";
echo "<img src='".$rootpath."gfx/csv.jpg' border='0'> ";
echo "CSV Export</a>";
        
echo "</td></tr></table>";

$query = 'SELECT * FROM users u
		WHERE status IN (1, 2, 3) 
		AND u.accountrole <> \'guest\' ';
if ($prefix_filterby <> 'ALL'){
	 $query .= 'AND u.letscode like \'' . $prefix_filterby .'%\' ';
}
if(!empty($searchname)){
	$query .= 'AND (LOWER(u.fullname) like \'%' .strtolower($searchname) . '%\'
		OR LOWER(u.name) like \'%' .strtolower($searchname) . '%\') ';
}
if(!empty($sort)){
	$query .= ' ORDER BY u.' . $sort;
}

$userrows = $db->GetArray($query);

$query = 'SELECT tc.abbrev, c.id_user, c.value
	FROM contact c, type_contact tc, users u
	WHERE tc.id = c.id_type_contact
		AND tc.abbrev IN (\'mail\', \'tel\', \'gsm\')
		AND u.id = c.id_user
		AND u.status IN (1, 2, 3)';
$c_ary = $db->GetArray($query);

$contacts = array();

foreach ($c_ary as $c)
{
	$contacts[$c['id_user']][$c['abbrev']] = $c['value'];
}

//show table
echo "<div class='border_b'><table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>\n";
echo "<tr class='header'>\n";
echo "<td><strong>";
echo "Code";
echo "</strong></td>\n";
echo "<td><strong>";
echo "Naam";
echo "</strong></td>\n";
echo "<td><strong>Tel</strong></td>\n";
echo "<td><strong>gsm</strong></td>\n";
echo "<td><strong>";
echo "Postc";
echo "</strong></td>\n";
echo "<td><strong>Mail</strong></td>\n";
echo "<td><strong>Saldo</strong></td>\n";
echo "</tr>\n\n";
$newuserdays = readconfigfromdb("newuserdays");
$rownumb=0;
foreach($userrows as $key => $value){
	$rownumb=$rownumb+1;
	if($rownumb % 2 == 1){
		echo "<tr class='uneven_row'>\n";
	}
	else
	{
			echo "<tr class='even_row'>\n";
	}

	if($value["status"] == 2){
		echo "<td nowrap valign='top' bgcolor='#f475b6'><font color='white' ><strong>";
		echo $value["letscode"];
		echo "</strong></font>";
	}
	else if((time() - ($newuserdays * 60 * 60 * 24)) < strtotime($value['cdate']))
	{
		echo "<td nowrap valign='top' bgcolor='#B9DC2E'><font color='white'><strong>";
		echo $value["letscode"];
					echo "</strong></font>";
	}
	else
	{
		echo "<td nowrap valign='top'>";
		echo $value["letscode"];
	}

	echo"</td>\n";
	echo "<td valign='top'>";
	echo "<a href='memberlist_view.php?id=".$value["id"]."'>".htmlspecialchars($value["fullname"],ENT_QUOTES)."</a></td>\n";
	echo "<td nowrap  valign='top'>";
	echo $contacts[$value['id']]['tel'];
	echo "</td>\n";
	echo "<td nowrap valign='top'>";
	echo $contacts[$value['id']]['gsm'];
	echo "</td>\n";
	echo "<td nowrap valign='top'>".$value["postcode"]."</td>\n";
	echo "<td nowrap valign='top'>";
	echo $contacts[$value['id']]['mail'];
	echo "</td>\n";

	echo "<td nowrap valign='top' align='right'>";
	$balance = $value["saldo"];
			if($balance < $value["minlimit"] || ($value["maxlimit"] != NULL && $balance > $value["maxlimit"])){
		echo "<font color='red'> $balance </font>";
	}else{
		echo $balance;
	}

	echo "</td>\n";
	echo "</tr>\n\n";

}
echo "</table></div>";

include($rootpath."includes/inc_footer.php");
