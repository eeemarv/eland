<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_mailinglists.php");

//echo "<script type='text/javascript' src='$rootpath/js/moomydetails.js'></script>";
// echo "<script type='text/javascript' src='$rootpath/contrib/ckeditor/ckeditor.js'></script>";

if (!isset($s_id))
{
	header("Location: " . $rootpath . "login.php");
	exit;
}

if (!($s_accountrole == 'user' || $s_accountrole == 'admin'))
{
	exit;
}

include($rootpath."includes/inc_header.php");

echo "<h1>Mijn gegevens</h1>";
echo "<script type='text/javascript' src='" .$rootpath ."js/mydetails.js'></script>";

$user = readuser($s_id);
show_user($user);
show_editlink();
//show_sendform();
show_pwform();
show_contact();
show_contactadd();

//	show_subs();
show_subform();
show_unsubform();

show_oids();
show_oidform();
$balance = $user["saldo"];
show_balance($balance, $user, readconfigfromdb("currency"));


////////////////////////////////////////////////////////////////////////////

function show_changepwlink($s_id){
	echo "<p>| <a href='mydetails_pw.php?id=" .$s_id. "'>Paswoord veranderen</a> |</p>";
}

function show_sendform() {
	global $rootpath;
	
	if(readconfigfromdb("mailinglists_enabled") == 1) {
		global $s_id;
		$lists = get_my_open_mailinglists($s_id);

		echo "<div id='mlformdiv' class='hidden'>";
		echo "<form action='". $rootpath ."/resources/mailmessage/new' id='msgform' method='post'>";
		echo "<table class='selectbox' cellspacing='0' cellpadding='0' border='0'>";

		// Topic
		echo "<tr><td valign='top' align='right'>Aan lijst</td>";
		echo "<td valign='top'>";
		echo "<select name='list'>\n";
		foreach ($lists as $value){
			echo "<option value='".$value["listname"]."' >" .$value["listname"] ."</option>\n";
		}
		echo "</select>";
		echo "</td>";
		echo "</tr>";

		// Moderatormail
		echo "<tr><td valign='top' align='right'>Onderwerp</td>";
		echo "<td valign='top'>";
		echo "<input  type='text' id='msgsubject' name='msgsubject' size='80'>";
		echo "</td>";
		echo "</tr>";

		echo "<tr><td valign='top' align='right'>Body</td>";
		echo "<td valign='top'>";
		echo "<textarea class='ckeditor' id='msgbody' name='msgbody' cols='80' rows='15'></textarea>";
		echo "</td>";
		echo "</tr>";

		echo "<tr><td colspan='2' align='right'>";
		echo "<input type='submit' id='zend' value='Verzenden' name='zend'>";
		echo "</td><td>&nbsp;</td></tr>";
		echo "</table>";
		echo "</form>";
		echo "</div>";
	} else {
		echo "<div id='mlformdiv' class='hidden'>";
		echo "Mails verzenden is uitgeschakeld in de instellingen van deze installatie";
		echo "<form action='". $rootpath ."/resources/mailmessage/new' id='msgform' method='post'>";
		echo "</form></div>";
	}
}

function show_oidform() {
	global $s_id, $rootpath;
	echo "<div id='oidformdiv' class='hidden'>";
	echo "<form action='". $rootpath ."/resources/user/$s_id/openid' id='oidform' method='post'>";
	echo "<table class='selectbox' cellspacing='0' cellpadding='0' border='0'>";
	echo "<tr><td valign='top' align='right'>OpenID</td>";
	echo "<td valign='top'>";
	echo "<input  type='text' id='openid' name='openid' size='30'>";
	echo "</td>";
	echo "</tr>";
	echo "<tr><td colspan='2' align='right'>";
	echo "<input type='submit' id='zend' value='OpenID toevoegen' name='zend'>";
	echo "</td><td>&nbsp;</td></tr>";
	echo "</table>";
	echo "</form>";
	echo "</div>";
}

function show_subform(){
	global $s_id, $rootpath;

	if(readconfigfromdb("mailinglists_enabled") == 1) {
		$lists = get_availablelists($s_id);

		echo "<div id='subformdiv' class='hidden'>";
		echo "<table class='selectbox' cellspacing='0' cellpadding='0' border='0'>";
		echo "<tr><td>";
		echo "<form action='". $rootpath ."/resources/user/subscription/new' id='subform' method='post'>";
		echo "<select>";
		foreach($lists as $key => $value){
			echo "<option value='" .$value['listname'] ."'>" .$value['listname'] ."</option>";
		}
		echo "</select>";
		echo "<input type='submit' value='Abonneren'>";
		echo "</form>";
		echo "</td></tr>";
		echo "</table>";
		echo "</div>";
	} else {
		echo "<div id='subformdiv' class='hidden'>";
		echo "<form action='". $rootpath ."/resources/user/subscription/new' id='subform' method='post'>";
		echo "Mailinglists zijn uitgeschakeld in de instellingen van deze installatie";
		echo "</form></div>";
	}
}

function show_unsubform(){
	global $s_id, $rootpath;
	
	$lists = get_my_open_mailinglists($s_id);

	if(readconfigfromdb("mailinglists_enabled") == 1) {
		echo "<div id='unsubformdiv' class='hidden'>";
		echo "<table class='selectbox' cellspacing='0' cellpadding='0' border='0'>";
		echo "<tr><td>";
		echo "<form action='". $rootpath ."/resources/user/subscription/' id='unsubform' method='post'>";
		echo "<select>";
		foreach($lists as $key => $value){
			echo "<option value='" .$value['listname'] ."'>" .$value['listname'] ."</option>";
		}
		echo "</select>";
		echo "<input type='submit' value='Uitschrijven'>";
		echo "</form>";
		echo "</td></tr>";
		echo "</table>";
		echo "</div>";
	} else {
		echo "<div id='unsubformdiv' class='hidden'>";
		echo "<form action='". $rootpath ."/resources/user/subscription/' id='unsubform' method='post'>";
		echo "Mailinglists zijn uitgeschakeld in de instellingen van deze installatie";
		echo "</form></div>";
	}
}

function show_subs(){
	global $rootpath;
	
	$url = "rendersubscriptions.php";
	echo "<div id='subsdiv'></div>";
	echo "<script type='text/javascript'>showsmallloader('subsdiv');loadsubs('$url');</script>";
	echo "<table width='100%' border=0><tr><td>";
	echo "<ul class='hormenu'>";

	echo "<li><a id='showsubform' href='#'>Abonnement toevoegen</a></li>";
	echo "<li><a id='showunsubform' href='#'>Abonnement opzeggen</a></li>";
	echo "</ul>";
	echo "</td></tr></table>";
}

function show_oids(){
	global $rootpath;
	
	$url = "renderoid.php";
	echo "<div id='oiddiv'></div>";
	echo "<script type='text/javascript'>showsmallloader('oiddiv');loadoid('$url');</script>";
	echo "<table width='100%' border=0><tr><td>";
	echo "<ul class='hormenu'>";
	echo "<li><a id='showoidform' href='#'>OpenID toevoegen</a></li>";
	echo "</ul>";
	echo "</td></tr></table>";
}

function get_type_contacts(){
	global $db;
	$query = "SELECT * FROM type_contact";
	return $db->GetArray($query);
}

function show_contactadd(){
	global $rootpath;
	global $s_id;
	echo "<div id='contactformdiv' class='hidden'>";
	echo "<form action='". $rootpath ."/userdetails/postcontact.php' id='contactform' method='post'>";
	echo "<input type='hidden' name='contactmode' value='new'>";
	echo "<input type='hidden' name='id_user' value='" .$s_id ."'>";
	echo "<input type='hidden' name='contactid' value=''>";
	echo "<table class='selectbox' cellspacing='0' cellpadding='0' border='0'>\n\n";
	echo "<tr>\n";
	echo "<td valign='top' align='right'>Type</td>\n";
	echo "<td>";
	echo "<select name='id_type_contact'>\n";
	$typecontactrow = get_type_contacts();
	foreach($typecontactrow as $key => $value){
			echo "<option value='".$value["id"]."'>".$value["name"]."</option>\n";
	}
	echo "</select>\n</td>\n";

	echo "</tr>\n\n<tr>\n<td></td>\n<td>";
	echo "</td>\n";
	echo "</tr>\n\n";

	echo "<tr>\n";
	echo "<td valign='top' align='right'>Waarde</td>\n";
	echo "<td>";
	echo "<input type='text' name='value' size='80'>";
	echo "</td>\n";
	echo "</tr>\n\n<tr>\n<td></td>\n<td>";
	echo "</td>\n";
	echo "</tr>\n\n";

	echo "<tr>\n";
	echo "<td valign='top' align='right'>Commentaar</td>\n";
	echo "<td>";
	echo "<input type='text' name='comments' size='50' ";
	echo "</td>\n";
	echo "</tr>\n\n<tr>\n<td></td>\n<td>";
	echo "</td>\n";
	echo "</tr>\n\n";

	echo "<tr>\n";
	echo "<td valign='top' align='right'></td>\n";
	echo "<td>";
	echo "<input type='checkbox' name='flag_public' CHECKED";
	echo " value='1' >Ja, dit contact mag zichtbaar zijn voor iedereen";

	echo "</td>\n";
	echo "</tr>\n\n<tr>\n<td></td>\n<td>";
	echo "</td>\n";
	echo "</tr>\n\n";

	echo "<tr>\n<td colspan='2' align='right'><input type='submit' name='zend' value='Opslaan'>";
	echo "</td>\n</tr>\n\n";
	echo "</table></form></div>";
}

function show_pwform(){
	global $s_id, $rootpath;
	
	echo "<div id='pwformdiv' class='hidden'>";
	echo "<form action='". $rootpath ."/userdetails/postpassword.php' id='pwform' method='post'>";
	echo "<table class='selectbox' cellspacing='0' cellpadding='0' border='0'>";
	echo "<tr><td valign='top' align='right'>Paswoord</td>";
	echo "<td valign='top'>";
	echo "<input  type='text' id='pw1' name='pw1' size='30'>";
	echo "</td>";
	echo "</tr>";
	echo "<tr><td valign='top' align='right'>Herhaal paswoord</td>";
	echo "<td valign='top'>";
	echo "<input  type='test' id='pw2' name='pw2' size='30'>";
	echo "</td>";
	echo "</tr>";
	echo "<tr><td colspan='2' align='right'>";
	echo "<input type='submit' id='zend' value='Passwoord wijzigen' name='zend'>";
	echo "</td><td>&nbsp;</td></tr>";
	echo "</table>";
	echo "</form>";
	echo "</div>";

}

function show_editlink(){
	global $s_id;
	echo "<table width='100%' border=0><tr><td>";
	echo "<div id='navcontainer'>";
	echo "<ul class='hormenu'>";
	//$myurl="mydetails_edit.php?id=" .$s_id;
	$myurl="mydetails_edit.php";
	echo "<li><a href='#' onclick=window.open('$myurl','details_edit','width=640,height=480,scrollbars=yes,toolbar=no,location=no,menubar=no')>Gegevens aanpassen</a></li>";
	echo "<li><a href='mydetails_pw.php' id='showpwform'>Passwoord wijzigen</a></li>";

	//$myurl="upload_picture.php";
	//echo "<li><a href='#' onclick=window.open('$myurl','details_edit','width=640,height=480,scrollbars=yes,toolbar=no,location=no,menubar=no')>Foto toevoegen</a></li>";a
	echo "<script type='text/javascript'>function AddPic () { OpenTBox('" ."/userdetails/upload_picture.php" ."'); } </script>";
    echo "<li><a href='javascript: AddPic()'>Foto toevoegen</a></li>";

	//$myurl="remove_picture.php?id=" .$s_id;
	//echo "<li><a href='#' onclick=window.open('$myurl','details_edit','width=640,height=480,scrollbars=yes,toolbar=no,location=no,menubar=no')>Foto verwijderen</a></li>";
	echo "<script type='text/javascript'>function RemovePic() {  OpenTBox('" ."/userdetails/remove_picture.php?id=" .$s_id ."'); } </script>";
	echo "<li><a href='javascript: RemovePic();'>Foto verwijderen</a></li>";
	//echo "<li><a id='showmsgform' href='#'>Mail versturen</a></li>";
	echo "</ul>";
	echo "</div>";
	echo "</td></tr></table>";
}

function show_user($user){
	global $rootpath;

	echo "<table class='memberview' cellpadding='0' cellspacing='0' border='0' width='99%'>";
	echo "<tr class='memberheader'>";

	// Show header block
	echo "<td colspan='2' valign='top'><strong>".htmlspecialchars($user["name"],ENT_QUOTES)." (";
	echo trim($user["letscode"])." )";
	if($user["status"] == 2){
		echo " <font color='#F56DB5'>Uitstapper </font>";
	}
	echo "</strong></td></tr>";
	// End header

	// Wrap arround another table to show user picture
	echo "<td width='170' align='left'>";
	if(!isset($user["PictureFile"])) {
		echo "<img src='" .$rootpath ."gfx/nouser.png' width='250'></img>";
	} else {
		echo '<img src="https://s3.eu-central-1.amazonaws.com/' . getenv('S3_BUCKET') . '/'. $user['PictureFile'] .'" width="250"></img>';
	}
	echo "</td>";

	// inline table
	echo "<td>";
	echo "<table cellpadding='0' cellspacing='0' border='0' width='100%'>";
	echo "<tr><td width='50%' valign='top'>Naam: </td>";
	echo "<td width='50%' valign='top'>".$user["fullname"]."</td></tr>";
	echo "<tr><td width='50%' valign='top'>Postcode: </td>";
	echo "<td width='50%' valign='top'>".$user["postcode"]."</td></tr>";
	echo "<tr><td width='50%' valign='top'>Geboortedatum:  </td>";
	echo "<td width='50%' valign='top'>".$user["birthday"]."</td></tr>";

	echo "<tr><td valign='top'>Hobbies/interesses: </td>";
	echo "<td valign='top'>".htmlspecialchars($user["hobbies"],ENT_QUOTES)."</td></tr>";
	echo "<tr><td valign='top'>Commentaar: </td>";
	echo "<td valign='top'>".htmlspecialchars($user["comments"],ENT_QUOTES)."</td></tr>";
	echo "<tr><td valign='top'>Saldo Mail: </td>";
	if($user["cron_saldo"] == 1){
		echo "<td valign='top'>Aan</td>";
	} else {
		echo "<td valign='top'>Uit</td>";
	}
	echo "</table>";
	echo "</td>";
	echo "</table>";
}

function get_contact($s_id){
	global $db;
	$query = "SELECT *, ";
	$query .= " contact.id AS cid, users.id AS uid, type_contact.id AS tcid, ";
	$query .= " type_contact.name AS tcname, users.name AS uname ";
	$query .= " FROM users, type_contact, contact ";
	$query .= " WHERE users.id=".$s_id;
	$query .= " AND contact.id_type_contact = type_contact.id ";
	$query .= " AND users.id = contact.id_user ";
	$contact = $db->GetArray($query);
	return $contact;
}

function show_balance($balance, $user, $currency){
	echo "<div class='border_b'>";
	echo "<table class='memberview' cellpadding='0' cellspacing='0' border='0' width='99%'>";
	echo "<tr class='memberheader'><td colspan='2'>";
	echo "<strong>{$currency}stand</strong></td></tr>";
	echo "<tr>";
	echo "<td width='50%'>Huidige {$currency}stand: </td>";
	echo "<td width='50%'>";
	echo $balance;
	echo "</td></tr>";
	echo "<tr>";
	echo "<td width='50%'>Limiet minstand: </td>";
	echo "<td width='50%'>";
	echo $user["minlimit"];
	echo "</td></tr>";
	echo "<td width='50%'>Limiet maxstand: </td>";
	echo "<td width='50%'>";
	echo $user["maxlimit"];
	echo "</td></tr>";

	echo "</table>";
}

function show_contact(){
	global $rootpath;
	$url = "rendercontact.php";
	echo "<div id='contactdiv'></div>";
	echo "<script type='text/javascript'>showsmallloader('contactdiv');loadcontact('$url');</script>";
	echo "<table width='100%' border=0><tr><td>";
	echo "<ul class='hormenu'>";
	$myurl="mydetails_cont_add.php";
        echo "<li><a id='showcontactform' href='#'>Contact toevoegen</a></li>";
	echo "</ul>";
	echo "</td></tr></table>";
}



function get_lists(){
	global $db;
	$query = "SELECT * FROM lists WHERE auth = 'open'";
	$lists = $db->Execute($query);
	//var_dump($lists);
	return $lists;
}

include($rootpath."includes/inc_footer.php");
