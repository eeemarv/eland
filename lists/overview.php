<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_mailinglists.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

echo "<script type='text/javascript' src='$rootpath/js/moomloverview.js'></script>";

if(isset($s_id) && ($s_accountrole == "admin")){
	showlinks($rootpath);
	show_ptitle1();
	show_mlform();
	$lists = get_mailinglists();
	show_lists($lists);
	//show_comment();
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_mlform() {
	global $s_id;
    echo "<div id='mlformdiv' class='hidden'>";
    echo "<form action='". $rootpath ."/resources/mailinglist/newlistname' id='mlform' method='post'>";
    echo "<table class='selectbox' cellspacing='0' cellpadding='0' border='0'>";

    echo "<tr><td valign='top' align='right'>Lijst naam (kort, zonder spaties)</td>";
    echo "<td valign='top'>";
    echo "<input  type='text' id='listname' name='listname' size='25'>";
    echo "</td>";
    echo "</tr>";

    echo "<tr><td valign='top' align='right'>Omschrijving</td>";
    echo "<td valign='top'>";
    echo "<input  type='text' id='description' name='description' size='120'>";
    echo "</td>";
    echo "</tr>";

    // Type
    # All types should be internal from now on

    // Topic
    echo "<tr><td valign='top' align='right'>Onderwerp</td>";
    echo "<td valign='top'>";
    echo "<select name='topic'>\n";
	echo "<option value='news'>Nieuws</option>\n";
	echo "<option value='messages'>Vraag/Aanbod</option>\n";
	echo "<option value='other'>Andere</option>\n";
    echo "</select>";
    echo "</td>";
    echo "</tr>";

    // Auth
    echo "<tr><td valign='top' align='right'>Authorisatie</td>";
    echo "<td valign='top'>";
    echo "<select name='auth'>\n";
	echo "<option value='open'>Open</option>\n";
	echo "<option value='closed'>Gesloten</option>\n";
    echo "</select>";
    echo "</td>";
    echo "</tr>";

    // Moderation
    echo "<tr><td valign='top' align='right'>Moderatie</td>";
    echo "<td valign='top'>";
    echo "<select name='moderation'>\n";
	echo "<option value=1>Aan</option>\n";
	echo "<option value=0'>Uit</option>\n";
    echo "</select>";
    echo "</td>";
    echo "</tr>";

    // Moderatormail
    echo "<tr><td valign='top' align='right'>Mailadres moderator</td>";
    echo "<td valign='top'>";
    echo "<input  type='text' id='moderatormail' name='moderatormail' size='40'>";
    echo "</td>";
    echo "</tr>";

    // Subscribers

    echo "<tr><td colspan='2' align='right'>";
    echo "<input type='submit' id='zend' value='Lijst toevoegen' name='zend'>";
    echo "</td><td>&nbsp;</td></tr>";
    echo "</table>";
    echo "</form>";
    echo "</div>";
}

function showlinks($rootpath){
	global $s_id;
        echo "<table width='100%' border=0><tr><td>";
        echo "<div id='navcontainer'>";
        echo "<ul class='hormenu'>";
        #echo "<li><a href='#' onclick=window.open('$myurl','addgroup','width=640,height=480,scrollbars=yes,toolbar=no,location=no,menubar=no')>Lijst toevoegen</a></li>";
        echo "<li><a id='showmoderation' href='moderation.php'>Moderatie</a></li>";
	echo "<li><a id='showsend' href='sendmessage.php'>Bericht verzenden</a></li>";
        echo "<li><a id='showmlform' href='#'>Lijst toevoegen</a></li>";
        echo "</ul>";
        echo "</div>";
        echo "</td></tr></table>";
}

function show_outputdiv(){
	echo "<div id='output'><img src='/gfx/ajax-loader.gif' ALT='loading'>";
	echo "<script type=\"text/javascript\">loadurl('rendergroups.php');</script>";
	echo "</div>";
}

function show_comment(){
	echo "<p><small><i>";
	echo "Belangrijk: er moet zeker een interletsrekening bestaan van het type internal om eLAS toe te laten met zichzelf te communiceren.  Deze moet een geldige SOAP URL en Apikey hebben.";
	echo "</i></small></p>";
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle1(){
	echo "<h1>Overzicht Mailing lists</h1>";
}

function show_lists($lists){
	echo "<div class='border_b'><table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
	echo "<tr class='header'>";
	echo "<td valign='top'><strong>";
	echo "Lijstnaam";
	echo "</strong></td>";
	echo "<td valign='top'><strong>";
	echo "Omschrijving";
	echo "</strong></td>";
	echo "<td valign='top'><strong>";
	echo "Onderwerp";
	echo "</strong></td>";
	echo "</tr>\n\n";
	$rownumb=0;

	foreach($lists as $key => $value){
		$rownumb=$rownumb+1;
		echo "<tr";
		if($rownumb % 2 == 1){
			echo " class='uneven_row'";
		}else{
	        	echo " class='even_row'";
		}
		echo ">";

		echo "<td><a href='view.php?list=" .$value['listname'] ."'>" .$value['listname'] ."</td>";
		echo "<td>" .$value['description'] ."</td>";
		echo "<td>" .$value['topic'] ."</td>";
	}
	echo "</tr>";
	echo "</table>";

}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
