<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");

$includejs = '
	<script src="' . $cdn_jquery . '"></script>
	<script src="' . $cdn_datepicker . '"></script>
	<script src="' . $cdn_datepicker_nl . '"></script>
	<script src="' . $cdn_typeahead . '"></script>';
	
$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_datepicker_css . '" />';

include($rootpath."includes/inc_header.php");

include("inc_balance.php");

echo "<h1>Saldo op datum</h1>";

$posted_list = array();

if (isset($_GET["zend"]))
{
	$posted_list["date"] = $_GET["date"];
	$posted_list["prefix"] = $_GET["prefix"];
}
else
{
	$localdate = date("Y-m-d");
	$posted_list["date"] = $localdate;
}

$user_date = $posted_list["date"];
$users = get_filtered_users($posted_list["prefix"]);

echo "<table border=0 width='100%'>";
echo "<tr>";
echo "<td valign='top' align='left'>";
show_userselect($list_users,$posted_list);
echo "</td>";
echo "<td valign='top' align='right'>";
show_printversion($rootpath,$user_date,$posted_list["prefix"]);
echo "<br>";
show_csvversion($rootpath,$user_date,$posted_list["prefix"]);
echo "</td>";
echo "</tr>";
echo "</table>";

show_user_balance($users,$user_date,$user_prefix);

/////////////

function show_printversion($rootpath,$user_date,$user_prefix)
{
	echo "<a href='print_balance.php?date=";
	echo $user_date;
	echo "&prefix=" .$user_prefix;
	echo "'>";
	echo "<img src='".$rootpath."gfx/print.gif' border='0'> ";
	echo "Printversie</a>";
}

function show_csvversion($rootpath,$user_date,$user_prefix)
{
	echo "<a href='csv_balance.php?date=";
	echo $user_date;
	echo "&prefix=" .$user_prefix;
	echo "'>";
	echo "<img src='".$rootpath."gfx/csv.jpg' border='0'> ";
	echo "CSV Export</a>";
}

function show_userselect($list_users,$posted_list){
	echo '<div class="panel panel-info">';
	echo '<div class="panel-heading">';
	echo "<form method='GET'>";
	echo "<table  class='data'  cellspacing='0' cellpadding='0' border='0'>\n";

	echo "<tr><td>Datum afsluiting (yyyy-mm-dd):   </td>\n";
	echo "<td>";
	echo "<input type='text' name='date' size='10' ";
	if (isset($posted_list["date"]))
	{
		echo " value ='".$posted_list["date"]."' ";
	}
	echo 'data-provide="datepicker" data-date-format="yyyy-mm-dd" ';
	echo 'data-date-language="nl" ';
	echo 'data-date-today-highlight="true" ';
	echo 'data-date-autoclose="true" ';
	echo 'data-date-enable-on-readonly="false" ';        
    echo ">";
	echo "</td>";

	echo "<td>";
        echo "<input type='submit' name='zend' value='Filter'>";
        echo "</td>\n</tr>\n\n";
	echo "<tr>";
	echo "<td>";
	echo "Filter subgroep:";
        echo "</td><td>";
	echo "<select name='prefix'>\n";

        echo "<option value='ALL'>ALLE</option>";
        $list_prefixes = get_prefixes();
        foreach ($list_prefixes as $key => $value){
                echo "<option value='" .$value["prefix"] ."'>" .$value["shortname"] ."</option>";
        }
        echo "</select>\n";
	echo "</td></tr>\n\n";

	echo "</table>\n";
        echo "</form>";

	echo '</div>';
	echo '</div>';
}

include($rootpath."includes/inc_footer.php");
