<?php
ob_start();
$rootpath = "";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

if(isset($s_id)){
	$id = $_GET["id"];
	if(isset($id)){
		show_ptitle($id);
		$msgs = get_msgs($id);
		show_outputdiv($msgs, $id);
	}else{
		redirect_searchcat();
	}
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_outputdiv($msgs, $catid){
        echo "<div id='output'>";
	echo "<div class='border_b'>";
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td><strong nowrap>V/A</strong></td>";

	echo "<td><strong nowrap>Wie</strong></td>";
	echo "<td><strong nowrap>Wat</strong></td>";
	echo "<td><strong nowrap>Geldig tot</strong></td>";
	echo "</tr>";
	$rownumb=0;
	foreach ($msgs as $key => $value){
	$rownumb=$rownumb+1;
		if($rownumb % 2 == 1){
			echo "<tr class='uneven_row'>";
		}else{
	        	echo "<tr class='even_row'>";
		}

		if ($value["msg_type"] == 0){
			echo "<td nowrap valign='top'>V</td>";
		}elseif ($value["msg_type"] == 1){
			echo "<td nowrap valign='top'>A</td>";
		}

		echo "<td valign='top' nowrap>";
		echo htmlspecialchars($value["username"],ENT_QUOTES)." (".trim($value["letscode"]).")";
		echo "</td>";

		echo "<td valign='top'>";
		echo "<a href='messages/view.php?id=".$value["mid"]."&cat=".$id."'>";
                if(strtotime($value["valdate"]) < time()) {
                        echo "<del>";
                }
		$content = nl2br(htmlspecialchars($value["content"],ENT_QUOTES));
		echo $content;
		if(strtotime($value["valdate"]) < time()) {
                        echo "</del>";
                }

                echo "</a> </td>";

		echo "<td>";
                if(strtotime($value["valdate"]) < time()) {
                        echo "<font color='red'><b>";
                }
                echo $value["valdate"];
                if(strtotime($value["valdate"]) < time()) {
                        echo "</b></font>";
                }
                echo "</td>";
		echo "</tr>";
	}
	echo "</table></div>";
        echo "</div>";
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function redirect_searchcat(){
	header("Location: searchcat.php");
}
function show_ptitle($id){
	global $db;
	$query = "SELECT fullname FROM categories WHERE id=". $id;
	$row = $db->GetRow($query);
	echo "<h1>". $row["fullname"]."</h1>";

}

function get_msgs($id){
	global $db;
	$query = "SELECT *, ";
	$query .= " messages.id AS mid , ";
	$query .= " messages.validity AS valdate, ";
	$query .= " categories.fullname AS catname, ";
	$query .= " users.name AS username, ";
	$query .= " categories.id_parent AS parent_id ";
	$query .= " FROM messages, users, categories ";
	$query .= " WHERE ";
	$query .= " messages.id_category = categories.id";
	$query .= " AND messages.id_user = users.id ";
	$query .= " AND (users.status = 1 OR users.status = 2 OR users.status = 3) ";
	$query .= " AND (messages.id_category = ".$id ;
	$query .= " OR categories.id_parent = ".$id .")";
	$query .= " ORDER BY messages.msg_type DESC,users.letscode";
	$msgs = $db->GetArray($query);
	// echo getadoerror();
	return $msgs;
}

include($rootpath."includes/inc_footer.php");
