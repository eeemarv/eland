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
	//geef alle hoofdcats
	$maincats = select_maincats();
	
	$id_parent = show_maincats($maincats);
	//geef alle subcats van deze hoofdcats
	//select_subcats();
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function select_maincats(){
	global $db;
	$query = "SELECT * FROM categories WHERE leafnote = 0 ORDER BY name ";
	$maincats = $db->GetArray($query);
	return $maincats;
}

function show_maincats($maincats){
// Two column layout
	echo "<div class='outerfloat border_b'>";
	$rownumber=1; $columnnumber = 1;

	echo "<div width='50%'>";
	echo "<table class='data' cellpadding='0' cellspacing='0' border='1'>";
	foreach($maincats as $key => $value){
		echo "<tr class='even_row'><td><strong>";
		echo "<a href='searchcat_viewcat.php?id=".$value["id"]."'>"; 
		echo htmlspecialchars($value["name"],ENT_QUOTES);
		echo "</a></strong></td></tr>\n ";
		$id_parent = $value["id"];
		$subcats = select_subcats($id_parent);
		echo "<tr><td>";
		show_subcats($subcats);
		echo "</td></tr>";
		$rownumber = $rownumber + 1;
		if (($columnnumber !== 2) && ($rownumber > count($maincats)/2) ) {
		// wrap to a new column
		    $rownumber = 1;
		    $columnnumber = 2;
		    echo "</table></div>\n<div width='50%'>";
				echo "<table class='data' cellpadding='0' cellspacing='0' border='1'>";
		}     	
	}
	echo "</table></div>";
	echo "<div class='clearer'></div>";
	echo "</div>";
	
}


function show_subcats($subcats){
	foreach($subcats as $key => $value){
		echo "<a href='searchcat_viewcat.php?id=".$value["id"]."'>";
		echo htmlspecialchars($value["name"],ENT_QUOTES)."</a>&nbsp;";
		echo "(". $value["stat_msgs_wanted"]. " V, ". $value["stat_msgs_offers"]." A) \n ";
	}
}


function select_subcats($id_parent){
	global $db;
	$query = "SELECT * FROM categories WHERE id_parent = ".$id_parent ;
	$query .= " ORDER BY name ";
	$subcats = $db->GetArray($query);
	return $subcats;
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

?>


