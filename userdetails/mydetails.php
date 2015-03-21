<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

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
// echo "<script type='text/javascript' src='" .$rootpath ."js/mydetails.js'></script>";

$user = readuser($s_id);
show_user($user);
show_editlink();

$contact = get_contact($s_id);
show_contact($contact, $s_id);

//show_oids();
//show_oidform();
$balance = $user["saldo"];
show_balance($balance, $user, readconfigfromdb("currency"));

include($rootpath."includes/inc_footer.php");

////////////////////////////////////////////////////////////////////////////

function show_changepwlink($s_id){
	echo "<p>| <a href='mydetails_pw.php?id=" .$s_id. "'>Paswoord veranderen</a> |</p>";
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

function show_editlink(){
	global $s_id;
	echo "<table width='100%' border=0><tr><td>";
	echo "<div id='navcontainer'>";
	echo "<ul class='hormenu'>";
	echo '<li><a href="mydetails_edit.php">Gegevens aanpassen</a></li>';
	echo "<li><a href='mydetails_pw.php' id='showpwform'>Passwoord wijzigen</a></li>";

	echo "<script type='text/javascript'>function AddPic () { OpenTBox('" ."/userdetails/upload_picture.php" ."'); } </script>";
    echo "<li><a href='javascript: AddPic()'>Foto toevoegen</a></li>";

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
	if($user["cron_saldo"] == 't'){
		echo "<td valign='top'>Aan</td>";
	} else {
		echo "<td valign='top'>Uit</td>";
	}
	echo "</table>";
	echo "</td>";
	echo "</table>";
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

function get_contact($id){
	global $db;
	$query = "SELECT *, ";
	$query .= " contact.id AS cid, users.id AS uid, type_contact.id AS tcid, ";
	$query .= " type_contact.name AS tcname, users.name AS uname ";
	$query .= " FROM users, type_contact, contact ";
	$query .= " WHERE users.id=".$id;
	$query .= " AND contact.id_type_contact = type_contact.id ";
	$query .= " AND users.id = contact.id_user ";

	$contact = $db->GetArray($query);
	return $contact;
}

function show_contact($contact, $user_id){
	echo "<div >";
	echo "<table cellpadding='0' cellspacing='0' border='1' width='99%' class='data'>";

	echo "<tr class='even_row'>";
	echo "<td colspan='5'><p><strong>Contactinfo</strong></p></td>";
	echo "</tr>";
echo "<tr>";
echo "<th valign='top'>Type</th>";
echo "<th valign='top'>Waarde</th>";
echo "<th valign='top'>Commentaar</th>";
echo "<th valign='top'>Publiek</th>";
echo "<th valign='top'></th>";
echo "</tr>";

	foreach($contact as $key => $value){
		echo "<tr>";
		echo "<td valign='top'>".$value["abbrev"].": </td>";
		echo "<td valign='top'>".htmlspecialchars($value["value"],ENT_QUOTES)."</td>";
		echo "<td valign='top'>".htmlspecialchars($value["comments"],ENT_QUOTES)."</td>";
		echo "<td valign='top'>";
		if (trim($value["flag_public"]) == 1){
				echo "Ja";
		}else{
				echo "Nee";
		}
		echo "</td>";
		echo "<td valign='top' nowrap>|";
		echo "<a href='mydetails_cont_edit.php?cid=".$value["id"]."&uid=".$value["id_user"]."'>";
		echo " aanpassen </a> |";
		echo "<a href='mydetails_cont_delete.php?cid=".$value["id"]."&uid=".$value["id_user"]."'>";
		echo "verwijderen </a>|";
		echo "</td>";
		echo "</tr>";
	}
	echo "<tr><td colspan='5'><p>&#160;</p></td></tr>";
	echo "<tr><td colspan='5'>| ";
	echo "<a href='mydetails_cont_add.php?uid=" . $value['id_user'] . "'>";
	echo "Contact toevoegen</a> ";
	echo "|</td></tr>";
	echo "</table></div>";
}
