<?php
ob_start();
$rootpath = "../";
$ptitle="export";
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
	show_ptitle();
	$cats = get_all_cats();	
	reset($cats);
	foreach($cats as $key => $value){
		$catid=$value["id"];
		echo "<hr>";		
		if ($value["id_parent"] == 0){
			echo "<br>" . htmlspecialchars($value["fullname"],ENT_QUOTES)."<br>\n";
		}else{
			echo "<br>" . htmlspecialchars($value["fullname"],ENT_QUOTES)."<br>\n";
			echo "<br>Aanbod:<br>";
			$message_rows=array();
			$message_rows = get_all_msgs_in_cat($catid,1);
			show_all_msgs_in_cat($message_rows);
			echo "<br>Vraag:<br>";
			$message_rows=array();
			$message_rows = get_all_msgs_in_cat($catid,0);
			show_all_msgs_in_cat($message_rows);
		}
	}
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_addlink(){
	echo "<div class='border_b'>| <a href='add.php'>Vraag & Aanbod toevoegen</a> | </div>";
}

function show_ptitle(){
	echo "<h1>Export -- Vraag & Aanbod</h1>";
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








function show_all_msgs_in_cat($messagerows){
	$prev_content = "";
	foreach($messagerows as $msgkey => $msgvalue){
		$content = htmlspecialchars($msgvalue["content"],ENT_QUOTES);
		if ($content == $prev_content){
			// Same as previous - Just print out the letscode
			echo ",(".trim($msgvalue["letscode"]).")";
		} else {
			echo "<br>- \n";
			echo $content;
			echo " (".trim($msgvalue["letscode"]).")";
			$prev_content = $content;
		}		
	}
}




function get_all_msgs_in_cat($cat_id,$msg_type){
	$messagerows = array();
	global $db;
	$query = "SELECT * ";
	$query .= " FROM messages, users, categories ";
	$query .= " WHERE messages.id_user = users.id";
	$query .= " AND messages.id_category = $cat_id";

	$query .= " AND categories.id = $cat_id";
	$query .= " AND messages.msg_type = $msg_type ";
	$messagerows = $db->GetArray($query);
	return $messagerows;
}

function get_all_cats(){
	global $db;
	$query = "SELECT * FROM categories ";
	$query .= " ORDER BY fullname ";
	$cats = $db->GetArray($query);
	return $cats;
}



include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
