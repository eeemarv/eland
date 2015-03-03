<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_passwords.php");
require_once($rootpath."includes/inc_mailfunctions.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

// Array ( [letsgroup] => LETS Test [letscode_to] => 1 [letscode_from] => 1 [amount] => 2 [minlimit] => -500 [balance] => -540 [description] => 3 ) 

//debug
//print_r($_POST);
if(!isset($s_id)&&$s_accountrole != "admin"){
        exit;
}

$mode = $_POST["mode"];
//$posted_list["id"] = $_POST["id"];
$id = $_POST["id"];
$posted_list["name"] = pg_escape_string($_POST["name"]);
$posted_list["fullname"] = pg_escape_string($_POST["fullname"]);
$posted_list["letscode"] = pg_escape_string($_POST["letscode"]);
$posted_list["postcode"] = pg_escape_string($_POST["postcode"]);
$posted_list["birthday"] = pg_escape_string($_POST["birthday"]);
$posted_list["hobbies"] = pg_escape_string($_POST["hobbies"]);
$posted_list["comments"] = pg_escape_string($_POST["comments"]);
$posted_list["login"] = pg_escape_string($_POST["login"]);
$posted_list["accountrole"] = $_POST["accountrole"];
$posted_list["status"] = $_POST["status"];
$posted_list["admincomment"] = pg_escape_string($_POST["admincomment"]);
$posted_list["minlimit"] = $_POST["minlimit"];
$posted_list["lang"] =  "nl";
if($_POST["maxlimit"] == 0) {
	$posted_list["maxlimit"] = NULL;
} else {
	$posted_list["maxlimit"] = $_POST["maxlimit"];
}
$posted_list["presharedkey"] = $_POST["presharedkey"];

$email = pg_escape_string($_POST["email"]);
$address = pg_escape_string($_POST["address"]);
$telephone = $_POST["telephone"];
$gsm = $_POST["gsm"];

$activate = $_POST["activate"];

if($mode == "new"){
	//print "DEBUG: Creating a new user";
	$error_list = validate_input($posted_list,$email);
}
if($mode == "edit"){
	//print "DEBUG: Updating an existing user";
	$error_list = validate_input_onedit($id, $posted_list);
}
if (empty($error_list)){
	switch ($mode){
	        case "new":
			$result = insert_user($posted_list);
                        if($result == TRUE) {
                                echo "<font color='green'><strong>OK</font> - Gebruiker is opgeslagen";
				// After save, create the contact records
				// $abbrev, $value, $id
				$myuser = get_user_by_letscode($posted_list["letscode"]);
				//$myuser = get_user_maildetails($tmpuser["id"]);
				if(!empty($email)){	
					$result1 = create_contact("mail", $email, $myuser["id"]);
				} else {	
					$result1 = TRUE;
				}
				if(!empty($address)){
					$result2 = create_contact("adr", $address, $myuser["id"]);
				} else {
                                        $result2 = TRUE;
                                }
				if(!empty($telephone)){
                                	$result3 = create_contact("tel", $telephone, $myuser["id"]);
				} else {
                                        $result3 = TRUE;
                                }
                                if(!empty($gsm)){
                                	$result4 = create_contact("gsm", $gsm, $myuser["id"]);
				} else {
                                        $result4 = TRUE;
                                }
				if($result1 == TRUE && $result2 == TRUE && $result3 == TRUE && $result4 == TRUE) {
					echo "<br><font color='green'><strong>OK</font> - Contactgegevens opgeslagen";
				} else {
					echo "<br><font color='red'><strong>Fout bij het opslaan van de contactgegevens";
				}	
				// Activate the user if activate is set
				if($activate == "true"){
					$mailuser = get_user_maildetails($myuser["id"]);
					$pw = generatePassword();
					$posted_list["password"]= hash('sha512',$pw);
					$activateresult = set_pw($mailuser["id"], $posted_list);
					if($activateresult == TRUE) {
		                                echo "<br><font color='green'><strong>OK</font> - Gebruiker is geactiveerd met password $pw";
						// Now send a mail
						if(!empty($email)){
							sendactivationmail($pw, $mailuser, $s_id);
							sendadminmail($posted_list, $mailuser);
						}
					} else {
						echo "<br><font color='red'><strong>Fout bij het activeren van gebruiker";
					}
				}
                        } else {
                                echo "<font color='red'><strong>Fout bij het opslaan van de gebruiker";
                        }
                        break;
		case "edit":
			$result = update_user($id, $posted_list);
			if($result == TRUE) {
				echo "<font color='green'><strong>OK</font> - Gebruiker $id aangepast";
			} else {
				echo "<font color='red'><strong>Fout bij de update van gebruiker $id";
			}
			break;
	}
} else {
	echo "<font color='red'><strong>Fout: ";
        foreach($error_list as $key => $value){
		echo $value;
		echo " | ";
	}
	echo "</strong></font>";
}

///////////// FUNCTIONS //////////////////
function update_user($id, $posted_list){
    global $db;
    $posted_list["mdate"] = date("Y-m-d H:i:s");
    $result = $db->AutoExecute("users", $posted_list, 'UPDATE', "id=$id");
    return $result;
}

function set_pw($id, $posted_list){
        global $db;
        //$posted_list["password"]= 
	$posted_list["adate"] = date("Y-m-d H:i:s");
	$result = $db->AutoExecute("users", $posted_list, 'UPDATE', "id=$id");
        return $result;
}

function insert_user($posted_list){
        global $db;
	global $s_id;
	$posted_list["cdate"] = date("Y-m-d H:i:s");
	$posted_list["adate"] = date("Y-m-d H:i:s");
	$posted_list["creator"] = $s_id;
        $result = $db->AutoExecute("users", $posted_list, 'INSERT');
	return $result;
}

function create_contact($abbrev, $value, $id){
	global $db;
	$contacttype = get_contacttype($abbrev);
	$posted_list["id_type_contact"] = $contacttype["id"];
	$posted_list["value"] = $value;
	$posted_list["id_user"] = $id;
	$posted_list["flag_public"] = 1;
	$result = $db->AutoExecute("contact", $posted_list, 'INSERT');
        return $result;
}

function validate_username($name, &$error_list){
        if (!isset($name)|| $name==""){
		$error_list["name"]="Naam is niet ingevuld";
	}
}

function validate_letscode($letscode, &$error_list){
	global $db;
	$query = "SELECT * FROM users ";
	$query .= "WHERE TRIM(letscode)  <> '' ";
	$query .= "AND TRIM(letscode) = '".$letscode."'";
	$query .= " AND status <> 0 ";
	$rs=$db->Execute($query);
	$number2 = $rs->recordcount();

	if ($number2 !== 0){
		$error_list["letscode"]="Letscode $letscode bestaat al";
	}
}

function validate_login($login, &$error_list){
	global $db;
	$query = "SELECT * FROM users WHERE login = '".$login."'"; 
	$rs=$db->Execute($query);
	$number = $rs->recordcount();

	if ($number !== 0){
		$error_list["login"]="Login ".$login." bestaat al!";
		//$user = get_user_maildetails($id);
		//$email = $user["emailaddress"];	
		//$error_list["login"].=" <br>Suggestie: Het e-mail adres van deze gebruiker ";
		//$error_list["login"].=" (".$email.")";
		//$error_list["login"].=" is een geschikte kandidaat om als unieke login naam te dienen.";
	}
}

function validate_input_onedit($id, $posted_list){
	$error_list = array();
	//validate_username($posted_list["name"], $error_list);
	//validate_letscode($posted_list["letscode"], $error_list);
	//if (!empty($posted_list["login"])){
		//validate_login($posted_list["login"], $id, $error_list);
	//}
	return $error_list;
}

function validate_input($posted_list,$email){
        $error_list = array();

	validate_username($posted_list["name"], $error_list);
	validate_letscode($posted_list["letscode"], $error_list);
        if (!empty($posted_list["login"])){
		validate_login($posted_list["login"], $error_list);
        }

        //amount may not be empty
        $var = trim($posted_list["minlimit"]);
        if (empty($posted_list["minlimit"])|| (trim($posted_list["minlimit"] )=="")){
                $error_list["minlimit"]="Minlimiet is niet ingevuld";
        //amount amy only contain  numbers between 0 en 9
        }elseif(eregi('^-[0-9]+$', $var) == FALSE){
                $error_list["minlimit"]="Minlimiet moet een negatief getal zijn";
        }
        
        if(!empty($posted_list['birthday'])) {
			if (!preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $posted_list['birthday'])) {
                $error_list["birthday"]="Geef de geboortedatum in jjjj-mm-dd formaat op";
			}
		}

	//if (empty($posted_list["maxlimit"])){
	//	$error_list["maxlimit"]="Maxlimiet is niet ingevuld";
	//}

	if(filter_var($email, FILTER_VALIDATE_EMAIL) == false) {
		//var_dump(filter_var($email, FILTER_VALIDATE_EMAIL));
		$error_list["email"]="E-mail adres is niet geldig";
	}

	return $error_list;
}

function sendadminmail($posted_list, $user){
        global $configuration;

	$mailfrom = trim(readconfigfromdb("from_address"));	
	$mailto = trim(readconfigfromdb("admin"));
	$systemtag = readconfigfromdb("systemtag");

        $mailsubject = "[";
        $mailsubject .= readconfigfromdb("systemtag");
        $mailsubject .= "] eLAS account activatie";

        $mailcontent  = "*** Dit is een automatische mail van het eLAS systeem van ";
        $mailcontent .= $systemtag;
        $mailcontent .= " ***\r\n\n";
        $mailcontent .= "De account ";
        $mailcontent .= $user["login"];
        $mailcontent .= " werd geactiveerd met een nieuw passwoord.\n";
        if (!empty($user["emailaddress"])){
                $mailcontent .= "Er werd een mail verstuurd naar de gebruiker op ";
                $mailcontent .= $user["emailaddress"];
                $mailcontent .= ".\n\n";
        } else {
                $mailcontent .= "Er werd GEEN mail verstuurd omdat er geen E-mail adres bekend is voor de gebruiker.\n\n";
        }

        $mailcontent .= "OPMERKING: Vergeet niet om de gebruiker eventueel toe te voegen aan andere LETS programma's zoals mailing lists.\n\n";
        $mailcontent .= "Met vriendelijke groeten\n\nDe eLAS account robot\n";

        sendemail($mailfrom,$mailto,$mailsubject,$mailcontent);
        
}


?>

