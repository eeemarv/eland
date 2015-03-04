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
$s_login = $_SESSION["login"];

include($rootpath."includes/inc_smallheader.php");
#include($rootpath."includes/inc_nav.php");
include($rootpath."includes/inc_content.php");

if(isset($s_id) && ($s_accountrole == "user" || $s_accountrole == "admin")){

	echo "<h1>LETS Chat</h1>";

	$tag = readconfigfromdb("systemtag");
	$name = strtolower(preg_replace('/\s+/', '', $s_login));
	$name = preg_replace('/[^A-Za-z0-9\-]/', '', $name);
	$nick = $name ."_" . $tag;
	//echo "DEBUG: connecting as $nick";
	$url = "<iframe src=\"http://webchat.freenode.net?nick=" .$nick . "&channels=letsbe\" width=\"700\" height=\"400\"></iframe>";
	echo $url;

	echo "<p><small><i>";
	echo "Deze chat laat je toe om met LETSers over heel Vlaanderen te chatten via het Freenode IRC netwerk<br>Je kan hier ook via een client op inloggen met de server irc.freenode.net, kanaal #letsbe<br>";
	echo "Om misbruik op het netwerk te voorkomen vraagt het Freenode netwerk je om de CAPTCHA over te typen om spammers buiten te houden";
	echo "<br>Sluit gewoon dit venster om uit te loggen op de chatroom.";
	echo "</i></small></p>";

}else{
	redirect_login($rootpath);
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_smallfooter.php");
?>
