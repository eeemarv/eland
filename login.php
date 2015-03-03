<?php
ob_start();
$ptitle="login";
$rootpath = "./";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_header.php");
require_once($rootpath."includes/inc_nav.php");
require_once($rootpath."includes/inc_tokens.php");

//require_once($rootpath."contrib/includes/lightopenid/openid.php");

$locked = 0;

// Include the moologin javascript code
echo "<script type='text/javascript' src='$rootpath/js/moologin.js'></script>";

$token = $_GET["token"];
$redirectmsg = $_GET["redirectmsg"];
$openid = $_GET['openid_identity'];

// Intercept old direct links and rewrite them
if(!empty($redirectmsg)){
	$_GET['url'] = "http://$baseurl/messages/view.php?id=" .$redirectmsg;
}

// Verify the token first and redirect to index if it is valid
if(!empty($token)){
	if(verify_token($token,"guestlogin") == 0){
        session_start();
        $_SESSION["id"] = 0;
        $_SESSION["name"] = "letsguest";
        $_SESSION["letscode"] = "X000";
        $_SESSION["accountrole"] = "guest";
		$_SESSION["type"] = "interlets";
		$_SESSION["status"] = array();
		log_event($_SESSION["id"],"Login","Guest login using token succeeded");
		setstatus($_SESSION["name"] ." ingelogd");
		echo "<script type='text/javascript'>self.location='index.php';</script>";
	} else {
		echo "<b><font color='red'>Interlets login is mislukt</font></b>";
		log_event("","LogFail", "Token login failed ($token)");

	}
}

// OPENID login code goes here

//echo "<h1>" . $tr->get('login', 'login') ."</h1>";

if(!empty($_GET['url'])){
	echo "<script type='text/javascript'>var redirecturl='" .$_GET['url'] ."';</script>";
} else {
	echo "<script type='text/javascript'>var redirecturl='index.php';</script>";
}


// Check if we are in maintenance mode
if(readconfigfromdb("maintenance") == 1){
	echo "<p><font color='red'><p><strong>eLAS is niet beschikbaar wegens onderhoudswerken.  Enkel admin gebruikers kunnen inloggen</strong></font></p>";
}

// Check if we are locked
if($locked == 1){
	echo "<p><font color='red'><p><strong>Het hostingcontract van deze installatie is verlopen, de site is inactief</strong></font></p>";
}

// Draw the login form division
echo "<div id='formdiv'>";
if(empty($token)){        
	echo "<form id='loginform' name='loginform' action='$rootpath/postlogin.php' method='post'>";
	echo "<table class='selectbox'><tr>";
	echo "<td>Login</td>";
	echo "<td><input type='text' name='login' size='30'></td>";
	echo "</tr><tr>";
	echo "<td>Paswoord</td>";
	echo "<td><input type='password' name='password' size='30'></td>";
	echo "</tr>";
	echo "<tr><td colspan='2' align='right'>";
	echo "<input type='submit' id='submitter' value='Inloggen'>";
	echo "</td></tr>";
	echo "</table>";
	echo "</form>";
	echo "</div>";
	
	// Show the OpenID login box
	echo "<div id='openiddiv'>";
	echo "<form id='openidbox' name='openidbox' action='$rootpath/postopenid.php' method='post'>";
    echo "<table class='selectbox'><tr>";
    echo "<td><img src='$rootpath/gfx/openid.png'>OpenID</td>";
	echo "<td><input type='text' name='openid' size='50'></td>";
	echo "<input type='hidden' name='targeturl' value='" .$_GET['url'] ."'>";
	echo "<td><input type='submit' id='openidsubmitter' value=' OpenID inloggen'></td>";
    echo "</tr>";
    echo "<tr><td></td><td align='right'><small><i><a href='http://www.letsplaza.net/content/openid'>Wat is OpenID?</a></i></small></td></tr>";
	echo "</table>";
    echo "</form>";
    echo "</div>";

	// Focus the login field
	echo "<script type='text/javascript'>document.loginform.login.focus();</script>";

	// Draw the hidden password reset form
	echo "<div id='pwresetdiv' class='hidden'>";
	echo "<form id='pwresetform' name='pwresetform' action='$rootpath/pwreset.php' method='post'>";
        echo "<table class='selectbox' border='0'><tr>";
        echo "<td>E-mal adres</td>";
        echo "<td><input type='text' name='email' size='30'></td>";
        echo "</tr>";
	echo "<tr><td colspan='2' align='right'>";
        echo "<input type='submit' id='resetter' value='Reset paswoord'>";
        echo "</td></tr>";
	echo "</table>";
        echo "</form>";
        echo "</div>";

	// Draw the hidden guest login form
	echo "<div id='guestlogindiv' class='hidden'>";
        echo "<form id='guestloginform' name='guestloginform' action='' method='post'>";
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
        log_event($user["id"],"Login","User " .$user["login"] ." logged in");
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

