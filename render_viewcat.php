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
	

if(isset($s_id)){
	$id = $_GET["id"];
	$msgs = get_msgs($id);
	show_msgs($msgs, $id);
}else{
	redirect_login($rootpath);
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

function show_msgs($msgs, $id){
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
		//echo chop_string($content, 50);
		//if(strlen($content)>=50){
		//	echo "...";
		//}
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

//	$result = mysql_query($query)or die ("select lukt niet");
	$msgs = $db->GetArray($query);
	echo getadoerror();
	return $msgs;
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
//	$result = mysql_query($query) or die("sel cat lukt niet");
//	$row = mysql_fetch_array($result, MYSQL_ASSOC);
	$row = $db->GetRow($query);
	echo "<h1>". $row["fullname"]."</h1>";
	
}
?>

