<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

if (isset($s_id)){
	if($s_accountrole == "user" || $s_accountrole == "admin"){
	        $messagerows = get_all_msgs($s_id);
                show_all_msgs($messagerows);
        }
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

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

function show_all_msgs($messagerows){
	global $rootpath;
	echo "<div class='border_b'>";
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td><strong>V/A</strong></td>";
	echo "<td><strong>Wat</strong></td>";
	echo "<td><strong>Geldig tot</strong></td>";
	echo "<td valign='top' nowrap><strong>Categorie</strong></td>";
	echo "<td><strong>Verlengen</strong></td>";
	echo "</tr>";
	$rownumb=0;
	foreach($messagerows as $key => $value){
		$rownumb=$rownumb+1;
		if($rownumb % 2 == 1){
			echo "<tr class='uneven_row'>";
		}else{
	        	echo "<tr class='even_row'>";
		}
		echo "<td valign='top'>";
		if($value["msg_type"]==0){
			echo "V";
		}elseif ($value["msg_type"]==1){
			echo "A";
		}
		echo "</td><td valign='top'>";
		 if(strtotime($value["valdate"]) < time()) {
                        echo "<del>";
                }
		echo "<a href='" .$rootpath ."messages/view.php?id=".$value["msgid"]."'>";
		$content = htmlspecialchars($value["content"],ENT_QUOTES);

		echo chop_string($content, 50);
		if(strlen($content)>50){
			echo "...";
		}
		echo "</a>";
		 if(strtotime($value["valdate"]) < time()) {
                        echo "</del>";
                }
		echo "</td><td>";
		if(strtotime($value["valdate"]) < time()) {
			echo "<font color='red'><b>";
		}
		echo $value["valdate"];
                if(strtotime($value["valdate"]) < time()) {
                        echo "</b></font>";
                }

		echo "</td>";

		echo "<td valign='top' >";
		echo htmlspecialchars($value["fullname"],ENT_QUOTES);
		echo "</td>";

		echo "<td valign='top'><a href='mymsg_extend.php?id=".$value["msgid"]."&validity=12'>1 jaar</a> | <a href='mymsg_extend.php?id=".$value["msgid"]."&validity=60'>5 jaar</a>";

		echo "</tr>";
	}
	echo "</table>";
	echo "</div>";
}

function get_all_msgs($s_id){
	global $db;
	$query = "SELECT *, ";
	$query .= " messages.id AS msgid, ";
	$query .= " categories.id AS catid, ";
	$query .= " messages.cdate AS date, ";
	$query .= " messages.validity AS valdate, ";
	$query .= "categories.name AS catname ";
	$query .= " FROM messages, categories ";
	$query .= " WHERE messages.id_user = $s_id";
	$query .= " AND messages.id_category = categories.id";
	$query .= " ORDER BY msg_type ";
	$messagerows = $db->GetArray($query);
	return $messagerows;
}

?>
