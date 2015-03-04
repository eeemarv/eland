<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_transactions.php");
require_once($rootpath."includes/inc_userinfo.php");
// Pull in the NuSOAP code
require_once($rootpath."soap/lib/nusoap.php");
session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

// Array ( [letsgroup] => LETS Test [letscode_to] => 1 [letscode_from] => 1 [amount] => 2 [minlimit] => -500 [balance] => -540 [description] => 3 )

//debug
//print_r($_POST);

if(!isset($s_id)){
        exit;
}

$posted_list["description"] = $_POST["description"];
$fromuser = get_user_by_letscode($_POST["letscode_from"]);
$posted_list["id_from"] = $fromuser["id"];
$touser = get_user_by_letscode($_POST["letscode_to"]);
$posted_list["id_to"] = $touser["id"];
$posted_list["minlimit"] = $_POST["minlimit"];
$posted_list["amount"] = $_POST["amount"];
//echo $_POST["letscode_to"];
$posted_list["letscode_to"] = $_POST["letscode_to"];
$letsgroupid = $_POST["letsgroup"];

if($s_accountrole <> "admin" && $s_letscode <> $_POST["letscode_from"]) {
	# Intercept attempt to do unauthorized transaction, FIXME Cleanup handling
	echo "Transactie niet toegestaan";
	exit;
}

if(isset($_POST["date"])){
	$posted_list["date"] = trim($_POST["date"]);
} else {
	$posted_list["date"] = date("Y-m-d H:i:s");
}
$timestamp = make_timestamp($posted_list["date"]);

//This script needs to do the actual transaction routing!!!
$letsgroup = get_letsgroup($letsgroupid);
$apimethod = $letsgroup["apimethod"];
switch ($apimethod){
	case "internal":
		$errors = validate_transaction_input($posted_list);
		if(!empty($errors)){
			echo "<font color='red'><strong>Fout: ";
			foreach($errors as $key => $value){
				echo $value;
				echo " | ";
			}
			echo "</strong></font>";
		} else {
			$settransid = generate_transid();
			$mytransid = insert_transaction($posted_list, $settransid);
			if($mytransid == $settransid){
				echo "<font color='green'><strong>OK</font> - Transactie opgeslagen</strong></font>";
				mail_transaction($posted_list, $mytransid);
				setstatus("Transactie opgeslagen", 0);
			} else {
				echo "<font color='red'><strong>Er was een fout bij het invoeren van de transactie</strong></font>";
				setstatus("Gefaalde transactie", 1);
			}
		}
		break;
	case "mail":
		//echo "<font color='red'><strong>Methode mail nog niet beschikbaar</strong></font>";
		// Book transaction locally and send the mail
		$groupuser = get_user_by_letscode($letsgroup["localletscode"]);
		//print_r($groupuser);
		$posted_list["id_to"] = $groupuser["id"];
		$settransid = generate_transid();
		//print_r($posted_list);
		$errors = validate_inteletstransaction_input($posted_list);
                if(!empty($errors)){
                        echo "<font color='red'><strong>Fout: ";
                        foreach($errors as $key => $value){
                                echo $value;
                                echo " | ";
                        }
                        echo "</strong></font>";
                } else {
			$mytransid = insert_transaction($posted_list, $settransid);
			if($mytransid == $settransid){
                                echo "<font color='green'><strong>OK</font> - Transactie opgeslagen</strong></font>";
				mail_interlets_transaction($posted_list, $mytransid);
				setstatus("Transactie opgeslagen", 0);
                        } else {
                                echo "<font color='red'><strong>Er was een fout bij het invoeren van de transactie</strong></font>";
				setstatus("Gefaalde transactie", 1);
                        }
		}
		break;
	case "elassoap":
		$errors = validate_inteletstransaction_input($posted_list);
		if(!empty($errors)){
                        echo "<font color='red'><strong>Fout: ";
                        foreach($errors as $key => $value){
                                echo $value;
                                echo " | ";
                        }
                        echo "</strong></font>";
                } else {
			// Generate a transactionID
			$settransid = generate_transid();
			$posted_list["transid"] = $settransid;
			$posted_list["letscode_to"] = $_POST["letscode_to"];
			$posted_list["letsgroup_id"] = $letsgroupid;
			$currencyratio = readconfigfromdb("currencyratio");
			$posted_list["amount"] = $posted_list["amount"] / $currencyratio;
			$posted_list["amount"] = (float) $posted_list["amount"];
			$posted_list["amount"] = round($posted_list["amount"],5);
			$posted_list["signature"] = sign_transaction($posted_list, $letsgroup["presharedkey"]);
			$posted_list["retry_until"] = time() + (60*60*24*4);
			// Queue the transaction for later handling
			$mytransid = queuetransaction($posted_list,$fromuser,$touser);
			if($mytransid == $settransid){
                                echo "<font color='green'><strong>OK</font> - Interletstransactie wacht op verwerking</strong>";
				setstatus("Transactie in verwerking", 0);
                        } else {
                                echo "<font color='red'><strong>Er was een fout bij het invoeren van de transactie</strong></font>";
				setstatus("Gefaalde transactie", 1);
                        }
		}
                break;
}
?>
