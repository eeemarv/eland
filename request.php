<?php
ob_start();
$ptitle="registreren";
$rootpath = "./";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_header.php");
require_once($rootpath."includes/inc_nav.php");
require_once($rootpath."includes/inc_tokens.php");
require_once($rootpath."includes/inc_hosting.php");

echo "<h1>Lidmaatschap aanvragen</h1>";

// Check if we are in maintenance mode
if(readconfigfromdb("maintenance") == 1){
	echo "<p><font color='red'><p><strong>eLAS is niet beschikbaar wegens onderhoudswerken.  Enkel admin gebruikers kunnen inloggen</strong></font></p>";
	die;
}

// Draw the login form division
echo "<div id='formdiv'>";
if(empty($token)){        
	echo "<form id='loginform' name='requestform' action='$rootpath/postrequest.php' method='post'>";
	echo "<table class='selectbox'><tr>";
	
	echo "<td>Volledige naam (voornaam eerst)</td>";
	echo "<td><input type='text' name='name' size='60'></td>";
	echo "</tr><tr>";
	
	echo "<td>Adres:</td>";
	echo "<td><input type='text' name='address' size='60'></td>";
	echo "</tr><tr>";
	
	echo "<td>Postcode:</td>";
	echo "<td><input type='text' name='zip' size='10'></td>";
	echo "</tr><tr>";
	
	echo "<td>Geboortedatum (jjjj-mm-dd):</td>";
	echo "<td><input type='text' name='birthdate' size='20'></td>";
	echo "</tr><tr>";
	
	echo "<td>Email:</td>";
	echo "<td><input type='text' name='email' size='35'></td>";
	echo "</tr><tr>";
	
	echo "<td>Telefoonnummer:</td>";
	echo "<td><input type='text' name='telephone' size='18'></td>";
	echo "</tr><tr>";
	
	echo "<td>GSM nummer:</td>";
	echo "<td><input type='text' name='cell' size='18'></td>";
	echo "</tr><tr>";
	
	echo "<tr><td colspan='2' align='right'>";
	echo "<input type='submit' id='submitter' value='Aanvragen'>";
	echo "</td></tr>";
	echo "</table>";
	echo "</form>";
	echo "</div>";
}

/////////////////////////// FUNCTIONS ///////////////////////////////

function show_buttons(){
        global $s_id;
	global $tr;

        echo "<table border='0' width='100%'><tr><td width='100%'></td><td nowrap>";
        echo "<div id='navcontainer'>";
        echo "<ul class='hormenu'>";
        $myurl="mydetails_edit.php?id=" .$s_id;
        echo "<li><a href='#' onclick=window.open('$myurl','details_edit','width=640,height=480,scrollbars=yes,toolbar=no,location=no,menubar=no')>" .$tr->get('login_problems','login') ."</a></li>";
        echo "</ul>";
        echo "</div>";
        echo "</td></tr></table>";
}

function startsession($user){
        session_start();
        $_SESSION["id"] = $user["id"];
        $_SESSION["name"] = $user["name"];
        $_SESSION["fullname"] = $user["fullname"];
        $_SESSION["login"] = $user["login"];
        $_SESSION["letscode"] = $user["letscode"];
        $_SESSION["accountrole"] = $user["accountrole"];
        $_SESSION["userstatus"] = $user["status"];
        $_SESSION["email"] = $user["emailaddress"];
		$_SESSION["lang"] = $user["lang"];
		$_SESSION["user_postcode"] = $user["postcode"];
        $_SESSION["type"] = "local";
        $_SESSION["status"] = array();

        $browser = $_SERVER['HTTP_USER_AGENT'];
        log_event($user["id"],"Login","User logged in");
        log_event($user["id"],"Agent","$browser");
        insert_date_into_lastlogin($user["id"]);
        //setstatus($_SESSION["login"] ." " .$tr->get('logged_in','login'), 0);
        
        // Debug notification queue
        //for ($i = 1; $i <= 10; $i++) {
		//	setstatus("Notification $i");
		//}
}


function insert_date_into_lastlogin($s_id){
        global $db;
        $posted_list["lastlogin"] = date("Y-m-d H:i:s");
        $result = $db->AutoExecute("users", $posted_list, 'UPDATE', "id=$s_id");

}

//include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>

