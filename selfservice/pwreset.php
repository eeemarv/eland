<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_header.php");
require_once($rootpath."includes/inc_nav.php");
require_once($rootpath."includes/inc_tokens.php");
require_once($rootpath."includes/inc_hosting.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_passwords.php");
require_once($rootpath."includes/inc_mailfunctions.php");

if(!isset($_POST["email"])){
	show_form($error_list);
} else {
	if(empty($_POST["email"])){
		echo "Geef een mailadres op!";
		log_event($s_id,"System","Empty password reset request");
		show_form($error_list);
		//return;
	} else {
		reset_password($_POST["email"]);
		echo "<P><a href='http://$baseurl/login.php'>Terug naar de loginpagina</a></P>";
		// Display a box, wait and than redirect to login screen! FIXME
		//echo "<script language='text/javascript'>jsnotify('Passwoord verzonden',true)</script>";
		//header("Location: ".$rootpath."login.php");
	}
}

////////////////////////////////////////////////////////////////////////////
////////////////////////////////F U N C T I E S ////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_ptitle(){
	echo "<h1>Passwoord reset</h1>";
}

function show_form($error_list){
	echo "<form id='pwresetform' name='pwresetform' action='$rootpath/selfservice/pwreset.php' method='post'>";
	echo "<table class='selectbox' border='0'><tr>";
	echo "<td>E-mail adres</td>";
	echo "<td><input type='text' name='email' size='30'></td>";
	echo "</tr>";
	echo "<tr><td colspan='2' align='right'>";
	echo "<input type='submit' id='resetter' value='Reset'>";
	echo "</td></tr>";
	echo "</table>";
	echo "<p><small>Let op, geef hier het e-mailadres in waarmee je in eLAS bent geregistreerd, anders kan het systeem je account niet terugvinden.</small></p>";
	echo "</form>";
	//echo "Indien je je emailadres niet meer weet klik dan <a href='#' onclick=\"javascript:window.open('$myurl','help','width=640,height=580,scrollbars=no,toolbar=no,location=no,menubar=no')\">hier</a>";	
}

function reset_password($email) {
	$contact = get_contact_by_email($email);

	if(empty($contact["value"])){
		log_event($s_id,"System","Password reset request for unkown mail " .$_POST["email"]);
		echo "E-mail adress " .$_POST["email"] ." niet gevonden";
		show_form($error_list);
	}

	$user = get_user_maildetails($contact["id_user"]);
	$posted_list["pw1"] = generatePassword();
	if(update_password($contact["id_user"], $posted_list) == TRUE){
		$output = sendpasswordresetemail($posted_list["pw1"], $user,0);
		echo $output;
		log_event($s_id,"System","Account " .$user["login"] ." password reset");
	} else {
		echo "Password reset mislukt, contacteer de beheerder";
		log_event($s_id,"System","Account " .$user["login"] ." password reset failed");
		show_form($error_list);
	}
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>

