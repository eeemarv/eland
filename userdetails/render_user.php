<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if (isset($s_id)){
	$user = readuser($s_id);
	show_user($user);
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////

function show_user($user){
	global $rootpath;
	global $baseurl;
	global $dirbase;
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
        
	if($user["PictureFile"] == NULL) {
		echo "<img src='" .$rootpath ."gfx/nouser.png' width='250'></img>";
	} else {
		echo '<img src="https://.s3.eu-central-1.amazonaws.com/' . getenv('S3_BUCKET') . '/'. $user['Picturefile'] .'" width="250"></img>';
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
	echo "<tr><td width='50%' valign='top'>ELAS World Wide ID (EWWID):  </td>";
	echo "<td width='50%' valign='top'>".$user["login"]. "@" .$baseurl ."</td></tr>";

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
