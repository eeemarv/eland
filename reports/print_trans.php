<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

include("inc_transperuser.php");

$user_userid = $_GET["userid"];
$user_datefrom = $_GET["datefrom"];
$user_dateto = $_GET["dateto"];
$user_prefix = $_GET["prefix"];

echo "<h1>Transactierapport</h1>";
if($user_userid == 'ALL') {
	$user['name'] = "Alle gebruikers";
	$user['letscode'] = "Alle";
} else {
	$user = get_user($user_userid);
}
echo "<p>Gebruiker: ";
echo $user['name'];
echo " (";
echo $user['letscode'];
echo ")";
echo "<br>";
echo "Datum van $user_datefrom tot $user_dateto</p>";
$transactions = get_all_transactions($user_userid,$user_datefrom,$user_dateto,$user_prefix);
show_all_transactions($transactions);

/////////

function show_userselect($list_users,$posted_list){
	echo "<form method='POST' action='transperuser.php'>";
	echo "<table  class='data'  cellspacing='0' cellpadding='0' border='0'>\n";

	echo "<tr>\n<td>";
	echo "Selecteer gebruiker:";
	echo "</td><td>\n";
	echo "<select name='userid'>\n";

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
        echo "<input type='submit' name='zend' value='OK'>";
        echo "</td>\n</tr>\n\n";

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

function get_user($id){
        global $db;
        $query = "SELECT *";
        $query .= " FROM users ";
        $query .= " WHERE id='".$id."'";
        $user = $db->GetRow($query);
        return $user;
}

function get_users(){
	global $db;
        $query = "SELECT * FROM users ";
        $query .= "WHERE (status = 1  ";
        $query .= "OR status =2 OR status = 3)  ";
        $query .= "AND users.accountrole <> 'guest' ";
        $query .= " order by letscode";
        $list_users = $db->GetArray($query);
        return $list_users;
}

function show_all_transactions($transactions){
	echo "<div class='border_b'>";
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td nowrap valign='top'><strong>";
	echo "Transactiedatum";
	echo "</strong></td>";
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
		echo htmlspecialchars($value["fromusername"],ENT_QUOTES). " (" .trim($value["fromletscode"]).")";
		echo "</td><td valign='top' nowrap>";
		echo htmlspecialchars($value["tousername"],ENT_QUOTES). " (" .trim($value["toletscode"]).")";
		echo "</td><td valign='top' nowrap>";
		echo $value["amount"];
		echo "</td><td valign='top'>";
		//echo "<a href='../transactions/view.php?id=".$value["transid"]."'>";
		echo htmlspecialchars($value["description"],ENT_QUOTES);
		//echo "</a> ";
		echo "</td></tr>";
	}
	echo "</table></div>";
}

#include($rootpath."includes/inc_sidebar.php");
#include($rootpath."includes/inc_footer.php");
?>
