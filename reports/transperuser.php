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

include("inc_transperuser.php");

$trans_orderby = $_GET["trans_orderby"];
#$user_userid = $_GET["user_userid"];

if(isset($s_id) && ($s_accountrole == "admin")){
	show_ptitle();
	$posted_list = array();
        if (isset($_POST["zend"])){
                $posted_list["userid"] = $_POST["userid"];
		$posted_list["datefrom"] = $_POST["datefrom"];
		$posted_list["dateto"] = $_POST["dateto"];
		$posted_list["prefix"] = $_POST["prefix"];
	} else {
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
	echo "<h1>Transactierapport per gebruiker</h1>";
}

function show_printversion($rootpath,$user_userid,$user_datefrom,$user_dateto,$user_prefix){
        echo "<a href='print_trans.php?userid=";
	echo "$user_userid";
	echo "&datefrom=";
	echo $user_datefrom;
	echo "&dateto=";
	echo $user_dateto;
	echo "&prefix=" .$user_prefix;
	echo "' target='new'>";
        echo "<img src='".$rootpath."gfx/print.gif' border='0'> ";
        echo "Printversie</a>";
}

function show_csvversion($rootpath,$user_userid,$user_datefrom,$user_dateto,$user_prefix){
        echo "<a href='csv_trans.php?userid=";
        echo "$user_userid";
        echo "&datefrom=";
        echo $user_datefrom;
        echo "&dateto=";
        echo $user_dateto;
	echo "&prefix=" .$user_prefix;
        echo "' target='new'>";
        echo "<img src='".$rootpath."gfx/csv.jpg' border='0'> ";
        echo "CSV Export</a>";
}


function show_userselect($list_users,$posted_list){
        echo "<form method='POST' action='transperuser.php'>";
        echo "<table  class='data'  cellspacing='0' cellpadding='0' border='0'>\n";

        echo "<tr>\n<td>";
	echo "Selecteer subgroep:";
        echo "</td><td>\n";
	echo "<select name='prefix'>\n";

        echo "<option value='ALL'>ALLE</option>";
        $list_prefixes = get_prefixes();
        foreach ($list_prefixes as $key => $value){
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
        foreach ($list_users as $value){
                if ($posted_list["userid"] == $value["id"]){
                        echo "<option value='".$value["id"]."' SELECTED>";
                }else{
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
	echo "<input type='text' name='datefrom' size='10'";
	if (isset($posted_list["datefrom"])){
                echo " value ='".$posted_list["datefrom"]."' ";
        }
        echo ">";
	echo "</td></tr>\n";
	echo "<tr><td>Datum tot (yyyy-mm-dd):</td>\n";
	echo "<td>";
        echo "<input type='text' name='dateto' size='10'";
	if (isset($posted_list["dateto"])){
		echo " value ='".$posted_list["dateto"]."' ";
	}
	echo ">";
	echo "</td></tr>\n";
	echo "</table>\n";
        echo "</form>";
}

function show_all_transactions($transactions){
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

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
