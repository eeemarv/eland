<?php
ob_start();
$rootpath = "";
$role = 'guest';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

include($rootpath."includes/inc_header.php");

if (!isset($s_id)){
	header("Location: ".$rootpath."login.php");
}

if (!isset($_GET["id"])){
	header("Location: searchcat.php");
}

$id = $_GET["id"];

show_ptitle($id);
$msgs = get_msgs($id);
show_outputdiv($msgs, $id);


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
		$rownumb++;
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
		echo '<a href="' . $rootpath . 'memberlist_view.php?id=' . $value['userid'] . '">';
		echo htmlspecialchars($value["username"],ENT_QUOTES)." (".trim($value["letscode"]).")";
		echo "</a></td>";

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

function show_ptitle($id){
	global $db;
	$query = "SELECT fullname FROM categories WHERE id=". $id;
	$row = $db->GetRow($query);
	echo "<h1>". $row["fullname"]."</h1>";

}

function get_msgs($id){
	global $db;
	$query = 'SELECT *, 
					m.id AS mid , 
					m.validity AS valdate, 
					c.fullname AS catname,
					u.name AS username,
					u.id AS userid,
					c.id_parent AS parent_id
				FROM messages m, users u, categories c
				WHERE m.id_category = c.id
					AND m.id_user = u.id
					AND (u.status = 1 OR u.status = 2 OR u.status = 3) 
					AND (m.id_category = ' . $id . '
						OR c.id_parent = ' .$id . ')
				ORDER BY m.msg_type DESC, u.letscode';
	return $db->GetArray($query);
}

include($rootpath."includes/inc_footer.php");
