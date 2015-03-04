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

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

if (isset($s_id)){
	if($s_accountrole == "user" || $s_accountrole == "admin"){
                show_addlink($rootpath);
                show_ptitle();
                show_msgdiv();
        } else {
                redirect_login($rootpath);
        }

}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_addlink($rootpath){
        global $s_id;
        echo "<table width='100%' border=0><tr><td>";
        echo "<div id='navcontainer'>";
        echo "<ul class='hormenu'>";
        $myurl="$rootpath/messages/edit.php?mode=new";
        echo "<li><a href='#' onclick=window.open('$myurl','mymsg_add','width=640,height=640,scrollbars=yes,toolbar=no,location=no,menubar=no')>Vraag/Aanbod toevoegen</a></li>";
        echo "</ul>";
        echo "</div>";
        echo "</td></tr></table>";

	//echo "<div class='border_b'>| ";
	//echo "<a href='mymsg_add.php'>Mijn Vraag & Aanbod toevoegen</a> |</div>";
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle(){
	echo "<h1>Mijn Vraag & Aanbod</h1>";
}

function show_msgdiv(){
	echo "<div id='msgdiv'></div>";
	$url="mymsg_render.php";
	echo "<script type='text/javascript'>showloader('msgdiv');loadurlto('$url','msgdiv')</script>";
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
