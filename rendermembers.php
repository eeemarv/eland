<?php
ob_start();
$rootpath = '';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';

$query = "SELECT * FROM users ";
$query .= "WHERE status IN (1, 2, 3)  ";
$query .= "AND users.accountrole <> 'guest' ";

//echo $query;
$userrows = $db->fetchAll($query);

show_all_users($userrows);

///////////////////

function check_timestamp($cdate,$agelimit){
        // agelimit is the time after which it expired
        $now = time();
	// age should be converted to seconds
        $limit = $now - ($agelimit * 60 * 60 * 24);
        $timestamp = strtotime($cdate);

        if($limit < $timestamp) {
                return 1;
        } else {
                return 0;
        }
}

function show_all_users($userrows){
	echo "<div class='border_b'><table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>\n";
	echo "<tr class='header'>\n";
	echo "<td><strong>";
	echo "Code";
	echo "</strong></td>\n";
	echo "<td><strong>";
	echo "Naam";
	echo "</strong></td>\n";
	echo "<td><strong>xyz</strong></td>\n";
	echo "<td><strong>xyz</strong></td>\n";
	echo "<td><strong>";
	echo "Postc";
	echo "</strong></td>\n";
	echo "<td><strong>xyz</strong></td>\n";
	echo "<td><strong>Stand</strong></td>\n";
	echo "</tr>\n\n";
	$newuserdays = readconfigfromdb("newuserdays");
	$rownumb=0;
	foreach($userrows as $key => $value){
	 	$rownumb=$rownumb+1;
		if($rownumb % 2 == 1){
			echo "<tr class='uneven_row'>\n";
		}else{
	        	echo "<tr class='even_row'>\n";
		}

		if($value["status"] == 2){
			echo "<td nowrap valign='top' bgcolor='#f475b6'><font color='white' ><strong>";
			echo $value["letscode"];
			echo "</strong></font>";
		}elseif(check_timestamp($value["cdate"],$newuserdays) == 1){
			echo "<td nowrap valign='top' bgcolor='#B9DC2E'><font color='white'><strong>";
			echo $value["letscode"];
                        echo "</strong></font>";
		}else{
			echo "<td nowrap valign='top'>";
			echo $value["letscode"];
		}

		echo"</td>\n";
		echo "<td valign='top'>";
		echo $value['name'];
		echo '</td>';
		echo "<td nowrap  valign='top'>";

		echo "</td>\n";
		echo "<td nowrap valign='top'>";

		echo "</td>\n";
		echo "<td nowrap valign='top'>".$value["postcode"]."</td>\n";
		echo "<td nowrap valign='top'>";

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
}
