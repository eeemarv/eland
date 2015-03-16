<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_passwords.php");

include($rootpath."includes/inc_smallheader.php");

//status 0: inactief
//status 1: letser
//status 2: uitstapper
//status 3: instapper
//status 4: secretariaat
//status 5: infopakket
//status 6: infoavond
//status 7: extern

$mode = $_GET["mode"];
$id = $_GET["id"];

if(isset($s_id) && ($s_accountrole == "admin")){
	show_ptitle();
	show_form();
	if($mode == "edit"){
		//Load the current values
		loadvalues($id);
		writecontrol("mode", "edit");
	} else {
		writecontrol("mode", "new");
		writecontrol("status", 1);
		writecontrol("accountrole", "user");
		writecontrol("minlimit", readconfigfromdb("minlimit"));
		writecontrol("maxlimit", readconfigfromdb("maxlimit"));
	}
	show_serveroutputdiv();
        show_closebutton();
}else{
	echo "<script type=\"text/javascript\">self.close();</script>";
}

include($rootpath."includes/inc_smallfooter.php");

//////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function loadvalues($userid){
	$user = get_user($userid);
	writecontrol("id", $user["id"]);
	writecontrol("name", $user["name"]);
	writecontrol("fullname", $user["fullname"]);
	writecontrol("letscode", $user["letscode"]);
	writecontrol("postcode", $user["postcode"]);
	writecontrol("birthday", $user["birthday"]);
	writecontrol("hobbies", $user["hobbies"]);
	writecontrol("comments", $user["comments"]);
	writecontrol("login", $user["login"]);
	writecontrol("accountrole", $user["accountrole"]);
	writecontrol("status", $user["status"]);
	writecontrol("admincomment", $user["admincomment"]);
	writecontrol("minlimit", $user["minlimit"]);
	writecontrol("maxlimit", $user["maxlimit"]);
	writecontrol("presharedkey", $user["presharedkey"]);
}

function writecontrol($key,$value){
	echo "<script type=\"text/javascript\">document.getElementById('" .$key ."').value = '" .$value ."';</script>";
}

function show_closebutton(){
        echo "<table border=0 width='100%'><tr><td align='right'><form id='closeform'>";
        echo "<input type='button' id='close' value='Sluiten' onclick='self.close(); window.opener.location.reload();'>";
        echo "<form></td></tr></table>";
}

function show_serveroutputdiv(){
        echo "<div id='serveroutput' class='serveroutput'>";
        echo "</div>";
}

function show_ptitle(){
	global $mode;
	echo "<h1>Gebruiker ";
	if($mode == "new") {
		echo "toevoegen";
	} else {
		echo "wijzigen";
	}
	echo "</h1>";
}

function redirect_overview(){
	header("Location: overview.php");
}

function insert_user($posted_list){
	global $db;
	$posted_list["cdate"] = date("Y-m-d H:i:s");
	$result = $db->AutoExecute("users", $posted_list, 'INSERT');

}

function validate_input($posted_list){
	$error_list = array();
	if (!isset($posted_list["name"])|| $posted_list["name"]==""){
		$error_list["name"]="<font color='#F56DB5'>Vul <strong>naam</strong> in!</font>";
	}
	global $db;
	$query = "SELECT * FROM users ";
	$query .= "WHERE TRIM(letscode)  <> '' ";
	$query .= "AND TRIM(letscode) = '".$posted_list["letscode"]."'";
	$query .= " AND status <> 0 ";
	$rs=$db->Execute($query);
	$number2 = $rs->recordcount();

	if ($number2 !== 0){
		$error_list["letscode"]="<font color='#F56DB5'>Letscode <strong>bestaat al</strong>!</font>";
	}

	if (!empty($posted_list["login"])){
	    $query = "SELECT * FROM users WHERE login = '".$posted_list["login"]."'";
    	    $rs=$db->Execute($query);
	    $number = $rs->recordcount();

	    if ($number !== 0){
		$error_list["login"]="<font color='#F56DB5'>Login bestaat al!</font>";
	    }
	}

	//amount may not be empty
	$var = trim($posted_list["minlimit"]);
	if (empty($posted_list["minlimit"])|| (trim($posted_list["minlimit"] )=="")){
		$error_list["minlimit"]="<font color='#F56DB5'>Vul <strong>bedrag</strong> in!</font>";
	//amount amy only contain  numbers between 0 en 9
	}elseif(eregi('^-[0-9]+$', $var) == FALSE){
		$error_list["minlimit"]="<font color='#F56DB5'>Bedrag moet een <strong>negatief getal</strong> zijn!</font>";
	}
return $error_list;
}

function show_form(){
	global $mode;
	echo "<script type='text/javascript' src='/js/postuser.js'></script>";
	echo "<div class='border_b'><p>";
	//echo "<form method='POST' action='add.php'>";
	echo "<form action=\"javascript:showloader('serveroutput'); get(document.getElementById('userform'));\" name='userform' id='userform'>";
	echo "<input type='hidden' name='mode' id='mode' size='4' value='new'>";
	echo "<input type='hidden' name='id' id='id' size='4'>";
	echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>\n";
	echo "<tr><td align='right' valign='top'>";
	echo "Naam";
	echo "</td>\n<td valign='top'>";
	echo "<input type='text' name='name' id='name' size='30'>";
	echo "</td>\n</tr>\n\n<tr>\n<td valign='top'></td></tr>";

	echo "<tr><td align='right' valign='top'>";
	echo "Volledige Naam (Voornaam en Achternaam)";
	echo "</td>\n<td valign='top'>";
	echo "<input type='text' name='fullname' id='fullname' size='30'>";
	echo "</td>\n</tr>\n\n<tr>\n<td valign='top'></td></tr>";

	echo "<tr>\n<td valign='top'valign='top' align='right'>Letscode</td>\n";
	echo "<td valign='top'><input type='text' name='letscode' id='letscode' size='10'>";
	echo "</td>\n</tr>\n\n";

	echo "<tr>\n<td valign='top' align='right'>Postcode</td>\n";
	echo "<td valign='top'><input type='text' name='postcode' id='postcode' size='6'>";
	echo "</td>\n</tr>\n\n";

	echo "<tr>\n<td valign='top' align='right'>Geboortedatum (jjjj-mm-dd)</td>\n";
	echo "<td valign='top'><input type='text' name='birthday' id='birthday' size='10'></td>\n</tr>\n\n";

	echo "<tr>\n<td valign='top' align='right'>Hobbies/interesses:</td>\n<td valign='top'>";
	echo "<textarea name='hobbies' id='hobbies' cols='40' rows='2'>";
	echo "</textarea>";
	echo "</td>\n</tr>\n\n<tr>\n<td></td>\n<td valign='top'>";
	echo "</td>\n</tr>\n\n";
	echo "<tr>\n<td valign='top' align='right'>Commentaar</td>\n<td valign='top'>";
	echo "<input type='text' name='comments' id='comments' size='60'>";
	echo "</td>\n</tr>\n\n<tr>\n<td></td>\n<td valign='top'>";
	echo "</td>\n</tr>\n\n";

	echo "<tr>\n<td valign='top' align='right'>Login</td>\n<td valign='top'>";
	echo "<input type='text' name='login' id='login' size='30'>";
	echo "</td>\n</tr>\n\n";

	echo "<tr><td valign='top' align='right'>Rechten</td>\n";
	echo "<td valign='top'>\n";
	echo "<select name='accountrole' id='accountrole'>";
	echo "<option value='admin' >Admin</option>";
	echo "<option value='user' >User</option>";
        echo "<option value='guest' >Guest</option>";
	echo "<option value='interlets' >Interlets</option>";
	echo "</select>";
	echo "</td>\n</tr>\n\n<tr>\n<td></td></tr>\n\n";
	echo "<tr>\n<td valign='top' align='right'>Status</td>\n";
	echo "<td valign='top'>";
	echo "<select name='status' id='status'>";
	echo "<option value='0'>Gedesactiveerd</option>";
	echo "<option value='1'>Actief</option>";
	echo "<option value='5'>Infopakket</option>";
	echo "<option value='6'>Infoavond</option>";
	echo "<option value='2'>Uitstapper</option>";
	echo "<option value='7'>Extern</option>";
	echo "</select>";
	echo "</td>\n";
	echo "</tr>\n\n";

	echo "<tr>\n<td valign='top' align='right'>Commentaar van de admin:</td>\n<td valign='top'>";
	echo "<textarea name='admincomment' id='admincomment' cols='40' rows='2'>";
	echo "</textarea>";
	echo "</td>\n</tr>\n\n<tr><td valign='top'>";
	echo "</td>\n</tr>\n\n";

	echo "<tr>\n<td valign='top' align='right'>Limiet minstand</td>\n<td valign='top'>";
	echo "<input type='text' name='minlimit' id='minlimit' size='30'>";
	echo "</td>\n</tr>\n\n<tr>\n<td></td>";
	echo "</tr>\n\n";

	echo "<tr>\n<td valign='top' align='right'>Limiet maxstand</td>\n<td valign='top'>";
        echo "<input type='text' name='maxlimit' id='maxlimit' size='30'>";
        echo "</td>\n</tr>\n\n<tr>\n<td></td>";
        echo "</tr>\n\n";

	echo "<tr>\n<td valign='top' align='right'>Preshared key<br><small><i>Interlets veld</i></small></td>\n<td valign='top'>";
        echo "<input type='text' name='presharedkey' id='presharedkey' size='70'>";
        echo "</td>\n</tr>\n\n<tr>\n<td></td>";
        echo "</tr>\n\n";

	if($mode == "new"){
		echo "<tr>\n<td valign='top' align='right'>E-mail</td>\n<td valign='top'>";
        	echo "<input type='text' name='email' id='email' size='50'>";
        	echo "</td>\n</tr>";

		echo "<tr>\n<td valign='top' align='right'>Adres</td>\n<td valign='top'>";
        	echo "<input type='text' name='address' id='address' size='70'>";
        	echo "</td>\n</tr>";

		echo "<tr>\n<td valign='top' align='right'>Tel</td>\n<td valign='top'>";
        	echo "<input type='text' name='telephone' id='telephone' size='20'>";
        	echo "</td>\n</tr>";

		echo "<tr>\n<td valign='top' align='right'>GSM</td>\n<td valign='top'>";
        	echo "<input type='text' name='gsm' id='gsm' size='20'>";
        	echo "</td>\n</tr>";

		echo "<tr>\n<td valign='top' align='right'>Activeren?</td>\n<td valign='top'>";
		echo "<INPUT TYPE=CHECKBOX NAME='activate' id='activate' CHECKED>";
		echo "</td>\n</tr>";
	} else {
                echo "<input type='hidden' name='email' id='email' size='50'>";
                echo "<input type='hidden' name='address' id='address' size='70'>";
                echo "<input type='hidden' name='telephone' id='telephone' size='20'>";
                echo "<input type='hidden' name='gsm' id='gsm' size='20'>";
                echo "<INPUT TYPE='hidden' NAME='activate' id='activate'>";
	}

	echo "<tr>\n<td colspan='2' align='right' valign='top'>";
	//echo "<input type='submit' name='activate' id='activate' value='Activeren'>";
	echo "<input type='submit' name='zend' id='zend' value='Opslaan'>";
	echo "</td>\n</tr>\n\n</table>\n\n";
	echo "</form>";

	//echo "<form action=\"javascript:showloader('serveroutput'); get(document.getElementById('userform'));\" name='activateform' id='activateform'>";
	//echo "<input type='submit' name='activate' id='activate' value='Activeren'>";
	//echo "</form>";
	//echo "</p></div>";
}
