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

if(isset($s_id)){

	if (isset($_GET["id"])){
		$id = $_GET["id"];
		$newsitem = get_newsitem($id);
		show_ptitle($newsitem["headline"]);
		show_newsitem($newsitem, $s_accountrole);
		//show_serveroutputdiv();
	}else{
		redirect_overview();
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

function show_ptitle($headline){
	echo "<h1>Nieuwsbericht: $headline</h1>";
}

function show_serveroutputdiv(){
        echo "<div id='serveroutput' class='serveroutput'>";
        echo "</div>";
}

function show_newsitem($newsitem, $s_accountrole){
	global $rootpath;
	echo "<div >";
	echo "<small>";

	echo "<strong>Agendadatum: ";
	if(trim($newsitem["idate"]) != "00/00/00"){
                        echo $newsitem["idate"];
        }
	echo "<br>Locatie: " .$newsitem["location"];
	echo "</strong>";
	echo "<br><i>Ingegeven door :" .htmlspecialchars($newsitem["name"],ENT_QUOTES)." (".trim($newsitem["letscode"]).")" ."</i>";
	echo "</small>";

	echo "<p>";
	echo nl2br(htmlspecialchars($newsitem["newsitem"],ENT_QUOTES));
	echo "</p>";

	echo "<p>";
	//echo "<div class='border_b'>";
	//echo "| <a href='edit.php?mode=edit&id=".$newsitem["nid"]."'>Aanpassen</a> | ";
	//echo "<a href='delete.php?id=".$newsitem["nid"]."'>Verwijderen</a> |";
	//echo "</div>";
	echo "<table width='100%' border=0><tr><td>";
	echo "<div id='navcontainer'>";
	echo "<ul class='hormenu'>";
	if($s_accountrole == 'admin'){
		$myurl= $rootpath ."news/edit.php?mode=edit&id=" .$newsitem["nid"];
		echo "<li><a href='#' onclick=window.open('$myurl','news_edit','width=640,height=480,scrollbars=yes,toolbar=no,location=no,menubar=no')>Aanpassen</a></li>";
	}
	if($s_accountrole == 'admin'){
		echo "<script type='text/javascript' src='/js/approvenews.js'></script>";
		$nid = $newsitem["nid"];
        	echo "<li><a href='#' onclick='approve($nid)'>Goedkeuren</a></li>";
        }
        if($s_accountrole == 'admin'){
                $myurl= $rootpath ."news/delete.php?id=" .$newsitem["nid"];
        echo "<li><a href='#' onclick=window.open('$myurl','news_delete','width=640,height=480,scrollbars=yes,toolbar=no,location=no,menubar=no')>Verwijderen</a></li>";
        }

	echo "</ul>";
	echo "</div>";
	echo "</td></tr></table>";

	echo "</p>";
	echo "</div>";
}

function get_newsitem($id){
	global $db;
	$query = "SELECT *, ";
	$query .= "news.id AS nid, ";
	$query .= " news.cdate AS date, ";
	$query .= " news.itemdate AS idate ";
	$query .= " FROM news, users  ";
	$query .= " WHERE news.id=".$id;
	$query .= " AND news.id_user = users.id ";
	$newsitem = $db->GetRow($query);
	return $newsitem;
}

function redirect_overview(){
	header("Location: overview.php");
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
