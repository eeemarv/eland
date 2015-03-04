<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_mailfunctions.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

$mode = $_POST["mode"];
$id = $_POST["id"];
$posted_list["headline"] = $_POST["headline"];
$posted_list["newsitem"] = $_POST["newsitem"];
$posted_list["location"] = $_POST["location"];
$posted_list["itemdate"] = $_POST["itemdate"];
$posted_list["sticky"] = $_POST["sticky"];

$errors = validate_input($posted_list);
if (!empty($errors)){
	echo "<font color='red'><strong>Fout: ";
	foreach($errors as $key => $value){
		echo $value;
		echo " | ";
	}
	echo "</strong></font>";
} else {
	switch ($mode){
	        case "new":
			$posted_list["id_user"] = $s_id;
			if(insert_newsitem($posted_list) == 1){
				echo "<font color='green'><strong>OK</font> - Nieuwsbericht opgeslagen</strong>";
				setstatus("Nieuwsbericht toegevoegd", 0);
		 		if($s_accountrole != "admin"){
		        		// Send a notice to ask for approval
                			$mailfrom = readconfigfromdb("from_address");
	                		$mailto = readconfigfromdb("newsadmin");
					$systemtag = readconfigfromdb("systemtag");
					$mailsubject = "[eLAS-".$systemtag."] Nieuwsbericht wacht op goedkeuring";
					$mailcontent .= "-- Dit is een automatische mail van het eLAS systeem, niet beantwoorden aub --\r\n";
					$mailcontent .= "\nEen lid gaf een nieuwsbericht met titel [";
					$mailcontent .= $posted_list["headline"];
					$mailcontent .= "] in, dat bericht wacht op goedkeuring.  Log in als beheerder op eLAS en ga naar nieuws om het bericht goed te keuren.\n";
					sendemail($mailfrom,$mailto,$mailsubject,$mailcontent);
					echo "<br><strong>Bericht wacht op goedkeuring van een beheerder</strong>";
					setstatus("Nieuwsbericht wacht op goedkeuring van een beheerder",0);
				}
			} else {
				echo "<font color='red'><strong>Fout bij het oplaan, probeer het opnieuw</strong></font>";
				setstatus("Nieuwsbericht niet toegevoegd", 1);
			}
			break;
		case "edit":
			if(update_newsitem($id, $posted_list) == 1){
				echo "<font color='green'><strong>OK</font> - Nieuwsbericht opgeslagen</strong>";
                                setstatus("Nieuwsbericht aangepast", 0);
			} else {
				echo "<font color='red'><strong>Fout bij het oplaan, probeer het opnieuw</strong></font>";
				setstatus("Nieuwsbericht niet aangepast", 1);
			}
			break;
	}

}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function insert_newsitem($posted_list){
	global $s_accountrole;
	if($s_accountrole == "admin"){
		$posted_list["approved"] = 1;
	}
	global $db;
	//$posted_list["cdate"] = date("Y-m-d H:i:s");

	$posted_list["id_user"] = (int)$posted_list["id_user"];
	$posted_list["published"] = False;

	if($posted_list["sticky"] == true){
		$posted_list["sticky"] = True;
	} else {
		$posted_list["sticky"] = False;
	}

	if($posted_list["approved"] == 1){
		$posted_list["approved"] = True;
	} else {
		$posted_list["approved"] = False;
	}

	var_dump($posted_list);
	$result = $db->AutoExecute("news", $posted_list, 'INSERT');
	return $result;
}

function update_newsitem($id, $posted_list){
	global $db;
	$result = $db->AutoExecute("news", $posted_list, 'UPDATE', "id=$id");
	return $result;
}

function validate_input($posted_list){
	$error_list = array();
	if (!isset($posted_list["headline"])|| (trim($posted_list["headline"])=="")){
		$error_list["headline"]="Titel is niet ingevuld";
	}
	return $error_list;
}

?>
