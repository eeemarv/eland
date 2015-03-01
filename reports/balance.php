<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];
	
include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

include("inc_balance.php");

#$trans_orderby = $_GET["trans_orderby"];
#$user_userid = $_GET["user_userid"];

if(isset($s_id) && ($s_accountrole == "admin")){
	show_ptitle();
	$posted_list = array();
        if (isset($_POST["zend"])){
		$posted_list["date"] = $_POST["date"];
		$posted_list["prefix"] = $_POST["prefix"];
	} else {
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

}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle(){
	echo "<h1>Saldo op datum</h1>";
}

function show_printversion($rootpath,$user_date,$user_prefix){
        echo "<a href='print_balance.php?date=";
	echo $user_date;
	echo "&prefix=" .$user_prefix;
	echo "' target='new'>";
        echo "<img src='".$rootpath."gfx/print.gif' border='0'> ";
        echo "Printversie</a>";
}

function show_csvversion($rootpath,$user_date,$user_prefix){
        echo "<a href='csv_balance.php?date=";
        echo $user_date;
	echo "&prefix=" .$user_prefix;
        echo "' target='new'>";
        echo "<img src='".$rootpath."gfx/csv.jpg' border='0'> ";
        echo "CSV Export</a>";
}


function show_userselect($list_users,$posted_list){
        echo "<form method='POST' action='balance.php'>";
        echo "<table  class='data'  cellspacing='0' cellpadding='0' border='0'>\n";

	echo "<tr><td>Datum afsluiting (yyyy-mm-dd):   </td>\n";
	echo "<td>";
	echo "<input type='text' name='date' size='10'";
	if (isset($posted_list["date"])){
                echo " value ='".$posted_list["date"]."' ";
        }
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
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
