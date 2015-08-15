<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_userinfo.php");

$includejs = '
	<script src="' . $cdn_jquery . '"></script>
	<script src="' . $cdn_datepicker . '"></script>
	<script src="' . $cdn_datepicker_nl . '"></script>
	<script src="' . $cdn_typeahead . '"></script>';
	
$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_datepicker_css . '" />';

include($rootpath."includes/inc_header.php");

include("inc_transperuser.php");

$trans_orderby = $_GET["trans_orderby"];

echo "<h1>Transactierapport per gebruiker</h1>";

$posted_list = array();
if (isset($_GET["zend"]))
{
	$posted_list["userid"] = $_GET["userid"];
	$posted_list["datefrom"] = $_GET["datefrom"];
	$posted_list["dateto"] = $_GET["dateto"];
	$posted_list["prefix"] = $_GET["prefix"];
}
else
{
	$posted_list["userid"] = "0";
	$year = date("Y");
	$posted_list["datefrom"] = $year ."-01-01";
	$posted_list["dateto"] = $year ."-12-31";
}

$list_users = get_users();
$user_userid = $posted_list["userid"];
$user_datefrom = $posted_list["datefrom"];
$user_dateto = $posted_list["dateto"];
$user_prefix = $posted_list["prefix"];

echo "<table border=0 width='100%'>";
echo "<tr>";
echo "<td valign='top' align='left'>";
show_userselect($list_users,$posted_list);
echo "</td>";
echo "<td valign='top' align='right'>";
show_printversion($rootpath,$user_userid,$user_datefrom,$user_dateto,$user_prefix);
echo "<br>";
show_csvversion($rootpath,$user_userid,$user_datefrom,$user_dateto,$user_prefix);
echo "</td>";
echo "</tr>";
echo "</table>";

$transactions = get_all_transactions($user_userid,$user_datefrom,$user_dateto,$user_prefix);
show_all_transactions($transactions);

include($rootpath."includes/inc_footer.php");

///////

function show_printversion($rootpath,$user_userid,$user_datefrom,$user_dateto,$user_prefix)
{
	echo "<a href='print_trans.php?userid=";
	echo "$user_userid";
	echo "&datefrom=";
	echo $user_datefrom;
	echo "&dateto=";
	echo $user_dateto;
	echo "&prefix=" .$user_prefix;
	echo "'>";
	echo "<img src='".$rootpath."gfx/print.gif' border='0'> ";
	echo "Printversie</a>";
}

function show_csvversion($rootpath,$user_userid,$user_datefrom,$user_dateto,$user_prefix)
{
	echo "<a href='csv_trans.php?userid=";
	echo "$user_userid";
	echo "&datefrom=";
	echo $user_datefrom;
	echo "&dateto=";
	echo $user_dateto;
	echo "&prefix=" .$user_prefix;
	echo "'>";
	echo "<img src='".$rootpath."gfx/csv.jpg' border='0'> ";
	echo "CSV Export</a>";
}

function show_userselect($list_users,$posted_list)
{
	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';

	echo "<form method='GET'>";
	echo "<table  class='data'  cellspacing='0' cellpadding='0' border='0'>\n";

	echo "<tr>\n<td>";
	echo "Selecteer subgroep:";
	echo "</td><td>\n";
	echo "<select name='prefix'>\n";

	echo "<option value='ALL'>ALLE</option>";
	$list_prefixes = get_prefixes();
	foreach ($list_prefixes as $key => $value)
	{
		echo "<option value='" .$value["prefix"] ."'>" .$value["shortname"] ."</option>";
	}
	echo "</select>\n";
	echo "</td>\n</tr>\n";

	echo "<tr>\n<td>";
	echo "Selecteer gebruiker:";
	echo "</td><td>\n";
	echo "<select name='userid'>\n";

	echo "<option value='ALL' >";
	echo "ALLE";
	echo "</option>";
	foreach ($list_users as $value)
	{
		if ($posted_list["userid"] == $value["id"])
		{
			echo "<option value='".$value["id"]."' SELECTED>";
		}
		else
		{
			echo "<option value='".$value["id"]."' >";
		}
		echo htmlspecialchars($value["name"],ENT_QUOTES)." (".trim($value["letscode"]).")";
		echo "</option>\n";
	}
	echo "</select>\n";
	echo "</td>\n";

	echo "<td>";
        echo "<input type='submit' name='zend' value='Filter'>";
        echo "</td>\n</tr>\n\n";

	echo "<tr><td colspan=2><small><i>Filteren kan enkel op subgroep OF gebruiker, de combinatie is niet mogelijk</i></small></td></tr>\n\n";

	echo "<tr><td>Datum van (yyyy-mm-dd):</td>\n";
	echo "<td>";
	echo "<input type='text' name='datefrom' size='10' ";
	if (isset($posted_list["datefrom"]))
	{
		echo " value ='".$posted_list["datefrom"]."' ";
	}
	echo 'data-provide="datepicker" data-date-format="yyyy-mm-dd" ';
	echo 'data-date-language="nl" ';
	echo 'data-date-today-highlight="true" ';
	echo 'data-date-autoclose="true" ';
	echo 'data-date-enable-on-readonly="false" '; 
	echo ">";
	echo "</td></tr>\n";
	echo "<tr><td>Datum tot (yyyy-mm-dd):</td>\n";
	echo "<td>";
	echo "<input type='text' name='dateto' size='10' ";
	if (isset($posted_list["dateto"]))
	{
		echo " value ='".$posted_list["dateto"]."' ";
	}
	echo 'data-provide="datepicker" data-date-format="yyyy-mm-dd" ';
	echo 'data-date-language="nl" ';
	echo 'data-date-today-highlight="true" ';
	echo 'data-date-autoclose="true" ';
	echo 'data-date-enable-on-readonly="false" '; 
	echo ">";
	echo "</td></tr>\n";
	echo "</table>\n";
	echo "</form>";

	echo '</div>';
	echo '</div>';
}

function show_all_transactions($transactions)
{
	echo "<div class='border_b'>";
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td nowrap valign='top'><strong>Transactiedatum</strong></td>";
	echo "<td nowrap valign='top'><strong>Creatiedatum</strong></td>";
	echo "<td valign='top'><strong>Van</strong></td>";
	echo "<td><strong>Aan</strong></td><td><strong>";
	echo "Bedrag";
	echo "</strong></td>";
	echo "<td valign='top'><strong>";
	echo "Dienst";
	echo "</strong></td></tr>";
	$rownumb=0;
	foreach($transactions as $key => $value){
		$rownumb=$rownumb+1;
		if($rownumb % 2 == 1){
			echo "<tr class='uneven_row'>";
		}else{
	        	echo "<tr class='even_row'>";
		}
		echo "<td nowrap valign='top'>";
		echo $value["datum"];
		echo "</td>";
		echo "<td nowrap valign='top'>";
		echo $value["cdatum"];
		echo "</td>";
		echo "<td nowrap valign='top'>";
		echo htmlspecialchars($value["fromusername"],ENT_QUOTES). " (" .trim($value["fromletscode"]).")";
		echo "</td><td valign='top' nowrap>";
		echo htmlspecialchars($value["tousername"],ENT_QUOTES). " (" .trim($value["toletscode"]).")";
		echo "</td><td valign='top' nowrap>";
		echo $value["amount"];
		echo "</td><td valign='top'>";
		echo "<a href='../transactions/view.php?id=".$value["transid"]."'>";
		echo htmlspecialchars($value["description"],ENT_QUOTES);
		echo "</a> ";
		echo "</td></tr>";
	}
	echo "</table></div>";
}
