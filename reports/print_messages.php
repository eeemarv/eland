<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");

$msg_type = $_GET["msg_type"];
$id_category = $_GET["id_category"];

$catname = $db->fetchColumn('select fullname from categories where id = ?', array($id_category));

show_ptitle($catname, $msg_type);
$messagerows = get_all_msgs($msg_type, $id_category);
show_all_msgs($messagerows, $s_accountrole);

///////////////

function show_ptitle($catname, $type)
{
	if($type == 1){
			$htype = "Aanbod";
	} else {
			$htype = "Vraag";
	}
	echo "<h1>$htype voor $catname</h1>";
}

function get_cats(){
        global $db;
        $query = "SELECT * FROM categories WHERE leafnote = 1 ORDER BY fullname";
        $list_cats = $db->fetchAll($query);
        return $list_cats;
}

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

function show_all_msgs($messagerows, $s_accountrole){
	echo "<div class='border_b'>";
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td ><strong>";
	echo "Wat";
	echo "</strong></td>";
        echo "<td ><strong>";
        echo "Wie";
        echo "</strong></td>";
	echo "<td ><strong>Geldig tot";
	echo "</strong></td>";
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
		if(strtotime($value["valdate"]) < time()) {
                        echo "<del>";
                }
		$content = htmlspecialchars($value["content"],ENT_QUOTES);
		echo chop_string($content, 50);
		if(strlen($content)>50){
			echo "...";
		}
                 if(strtotime($value["valdate"]) < time()) {
                        echo "</del>";
                }

		echo "</a> ";
		echo "</td>";

                echo "<td valign='top' nowrap>";
                echo  htmlspecialchars($value["username"],ENT_QUOTES)." (".trim($value["letscode"]).")";
                echo "</td>";

		echo "<td>";
                echo $value["valdate"];
                echo "</td>";

		echo "</tr>";
	}
	echo "</table></div>";
}

function get_all_msgs($msg_type, $id_category){
	$date = date('Y-m-d');
	global $db;
	$query = "SELECT *, ";
	$query .= " messages.id AS msgid, ";
	$query .= " users.id AS userid, ";
	$query .= " categories.id AS catid, ";
	$query .= " categories.fullname AS catname, ";
	$query .= " users.name AS username, ";
	$query .= " users.letscode AS letscode, ";
	$query .= " messages.validity AS valdate, ";
	$query .= " messages.cdate AS date ";
	$query .= " FROM messages, users, categories ";
	$query .= "  WHERE messages.id_user = users.id ";
	$query .= " AND messages.id_category = categories.id";

	$query .= " AND msg_type = ?";
	$query .= " AND messages.id_category = ?";

	$messagerows = $db->fetchAll($query, array($msg_type, $id_category));
	return $messagerows;
}
