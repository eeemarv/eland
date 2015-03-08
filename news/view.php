<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

include($rootpath."includes/inc_header.php");

if(!isset($s_id)){
	header("Location: ".$rootpath."login.php");
}

if (!isset($_GET["id"])){
		header("Location: overview.php");
}

$id = $_GET["id"];
$newsitem = get_newsitem($id);
echo "<h1>Nieuwsbericht: $headline</h1>";
show_newsitem($newsitem, $s_accountrole);

////////////////////////////////////////////////////////////////////////////

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
	echo "<br><i>Ingegeven door : ";
	echo '<a href="' . $rootpath . 'memberlist_view.php?id=' . $newsitem['uid'] . '">';
	echo htmlspecialchars($newsitem["name"],ENT_QUOTES)." (".trim($newsitem["letscode"]).")";
	echo "</a></i></small>";

	echo "<p>";
	echo nl2br(htmlspecialchars($newsitem["newsitem"],ENT_QUOTES));
	echo "</p>";

	echo "<p>";
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
	$query = 'SELECT *, 
			n.id AS nid,
			u.id AS uid,
			n.cdate AS date,
			n.itemdate AS idate
		FROM news n, users u  
		WHERE n.id=' . $id . '
		AND n.id_user = u.id';
	return $db->GetRow($query);
}

include($rootpath."includes/inc_footer.php");
