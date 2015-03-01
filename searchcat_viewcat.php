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
		show_outputdiv($id);
	}else{
		redirect_searchcat();
	}
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_outputdiv($catid){
        echo "<div id='output'><img src='/gfx/ajax-loader.gif' ALT='loading'>";
        echo "<script type=\"text/javascript\">loadurl('render_viewcat.php?id=";
	echo $catid;
	echo "')</script>";
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
//	$result = mysql_query($query) or die("sel cat lukt niet");
//	$row = mysql_fetch_array($result, MYSQL_ASSOC);
	$row = $db->GetRow($query);
	echo "<h1>". $row["fullname"]."</h1>";
	
}
include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>

