<?php
ob_start();
$rootpath = "../";
$role = 'guest';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$messagerows = get_all_msgs();
show_all_msgs($messagerows, $s_accountrole);


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
	echo "<td nowrap><strong>";
	echo "<a href='overview.php?msg_orderby=msg_type'>V/A</a>";
	echo "</strong></td>";
	echo "<td ><strong>";
	echo "<a href='overview.php?msg_orderby=content'>Wat</a>";
	echo "</strong></td>";
	echo "<td ><strong>Geldig tot";
	echo "</strong></td>";
	echo "<td ><strong>";
	echo "<a href='overview.php?msg_orderby=letscode'>Wie</a>";
	echo "</strong></td>";
	echo "<td ><strong>";
	echo "<a href='overview.php?msg_orderby=catname'>Categorie</a>";
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
		
		echo "<td valign='top' nowrap>";
		if($value["msg_type"]==0){
			echo "V";
		}elseif ($value["msg_type"]==1){
			echo "A";
		}
		echo "</td>";
		echo "<td valign='top'>";
		if(strtotime($value["valdate"]) < time()) {
                        echo "<del>";
                }
		echo "<a href='view.php?id=".$value["msgid"]."'>";
		$content = htmlspecialchars($value["content"],ENT_QUOTES);
		echo chop_string($content, 50);
		if(strlen($content)>50){
			echo "...";
		}
		echo "</a>";
                 if(strtotime($value["valdate"]) < time()) {
                        echo "</del>";
                }
		
		echo "</a> ";
		echo "</td>";

		echo "<td>";
                if(strtotime($value["valdate"]) < time()) {
                        echo "<font color='red'><b>";
                }
                echo $value["valdate"];
                if(strtotime($value["valdate"]) < time()) {
                        echo "</b></font>";
                }
                echo "</td>";


		echo "<td valign='top' nowrap>";
		echo  htmlspecialchars($value["username"],ENT_QUOTES)." (".trim($value["letscode"]).")";
		echo "</td>";
		echo "<td valign='top'>";
		echo htmlspecialchars($value["fullname"],ENT_QUOTES);
		echo "</td>";
		echo "</tr>";
	}
	echo "</table></div>";
}


function get_all_msgs($msg_orderby,$user_filterby){
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

	if (isset($user_filterby)){
		switch ($user_filterby) {
			case "expired":
				$query .= " AND messages.validity < " ."'" .$date ."'";
				break;
			case "valid":
				$query .= " AND messages.validity >= " ."'" .$date ."'";
				break;
		}
	}

	if (isset($msg_orderby)){
			$query .= " ORDER BY ".$msg_orderby. " ";
	}else{
			$query .= " ORDER BY messages.msg_type,letscode ";
	}
	//echo $query;
	$messagerows = $db->GetArray($query);
	return $messagerows;
}

?>
