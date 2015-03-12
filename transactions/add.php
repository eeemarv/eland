<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_transactions.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_mailfunctions.php");

if (!$s_id || !($s_accountrole == 'user' || $s_accountrole == 'admin'))
{
	header("Location: ".$rootpath."login.php");
	exit;
}

$posted_list = array();

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
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
		$alert->add_error( "Transactie niet toegestaan");
		header('Location: ' . $rootpath . 'transactions/alltrans.php');
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
	if (!isset($letsgroup)){
		$alert->add_error('Letsgroep niet gevonden.');
	}
	$apimethod = $letsgroup["apimethod"];
	switch ($apimethod){
		case "internal":
			$errors = validate_transaction_input($posted_list);
			if(!empty($errors)){

				foreach($errors as $value){
					$alert->add_error($value);
				}

			} else {
				$settransid = generate_transid();
				$mytransid = insert_transaction($posted_list, $settransid);
				if($mytransid == $settransid){
					//echo "<font color='green'><strong>OK</font> - Transactie opgeslagen</strong></font>";
					mail_transaction($posted_list, $mytransid);
					$alert->add_success("Transactie opgeslagen");
				} else {
					//echo "<font color='red'><strong>Er was een fout bij het invoeren van de transactie</strong></font>";
					$alert->add_error("Gefaalde transactie");
				}
				header('Location: ' . $rootpath . 'transactions/alltrans.php');
				exit;
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
						
						foreach($errors as $value){
							$alert->add_error($value);
						}

					} else {
				$mytransid = insert_transaction($posted_list, $settransid);
				if($mytransid == $settransid){
					//echo "<font color='green'><strong>OK</font> - Transactie opgeslagen</strong></font>";
					mail_interlets_transaction($posted_list, $mytransid);
					$alert->add_success("Transactie opgeslagen");
				} else {
					// echo "<font color='red'><strong>Er was een fout bij het invoeren van de transactie</strong></font>";
					$alert->add_error("Gefaalde transactie");
				}
				header('Location: ' . $rootpath . 'transactions/alltrans.php');
				exit;
			}
			break;
		case "elassoap":
			$errors = validate_inteletstransaction_input($posted_list);
			if(!empty($errors)){

					foreach($errors as $value){
						$alert->add_error($value);
					}

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
					//echo "<font color='green'><strong>OK</font> - Interletstransactie wacht op verwerking</strong>";
					$alert->add_success("Interlets transactie in verwerking");
					if (!$redis->get($session_name . '_interletsq'))
					{
						$redis->set($session_name . '_interletsq', time());
					}
				} else {
					//echo "<font color='red'><strong>Er was een fout bij het invoeren van de transactie</strong></font>";
					$alert->add_error("Gefaalde transactie", 1);
				}
				header('Location: ' . $rootpath . 'transactions/alltrans.php');
				exit;
			}
			break;
	}
}


include $rootpath . 'includes/inc_header.php';

$user = get_user($s_id);
$balance = $user["saldo"];

//$list_users = get_users($s_id);

$currency = readconfigfromdb('currency');

echo "<h1>{$currency} uitschrijven</h1>";

$minlimit = $user["minlimit"];

echo "<div id='baldiv'>";
echo '<p><strong>' . $user["name"].' '.$user["letscode"] . ' huidige ' . $currency . ' stand: '.$balance.'</strong> || ';
echo "<strong>Limiet minstand: ".$minlimit."</strong></p>";
echo "</div>";

$date = date("Y-m-d");

//echo "<script type='text/javascript' src='/js/posttransaction.js'></script>";
echo "<script type='text/javascript' src='/js/userinfo.js'></script>";
echo "<div id='transformdiv'>";
echo "<form  method='post'>";
echo "<input name='balance' id='balance' type='hidden' value='".$balance."' >";
echo "<input name='minlimit' type='hidden' id='minlimit' value='".$user["minlimit"]."' >";
echo "<table cellspacing='0' cellpadding='0' border='0'>";
echo "<tr><td align='right'>";
echo "Van";
echo "</td><td>";

echo "<select name='letscode_from' accesskey='2' id='letscode_from' \n";
if($s_accountrole != "admin") {
	echo " DISABLED";
}
echo " onchange=\"javascript:document.getElementById('baldiv').innerHTML = ''\">";

$list_users = $db->GetAssoc('SELECT letscode, fullname
	FROM users
	WHERE status IN (1, 2)
		AND accountrole NOT IN (\'guest\', \'interlets\')
	ORDER BY letscode');
render_selector_options($list_users, $posted_list['letscode']);
echo "</select>\n";

echo "</td><td width='150'><div id='fromoutputdiv'></div>";
echo "</td></tr>";

echo "<tr><td valign='top' align='right'>Datum</td><td>";
	echo "<input type='text' name='date' id='date' size='18' value='" .$date ."'";
if($s_accountrole != "admin") {
			echo " DISABLED";
	}
	echo ">";
	echo "</td><td>";
	echo "</td></tr><tr><td></td><td>";
	echo "</td></tr>";

echo "<tr><td align='right'>";
echo "Aan LETS groep";
echo "</td><td>";
echo "<select name='letsgroup' id='letsgroup' onchange=\"document.getElementById('letscode_to').value='';\">\n";

$letsgroups = $db->getAssoc('SELECT id, groupname FROM letsgroups');
render_selector_options($letsgroups, $posted_list['letsgroup']);

echo "</select>";
echo "</td><td>";
echo "</td></tr><tr><td></td><td>";
echo "<tr><td align='right'>";
echo "Aan LETSCode";
echo "</td><td>";
echo "<input type='text' name='letscode_to' id='letscode_to' size='10' onchange=\"javascript:showsmallloader('tooutputdiv');loaduser('letscode_to','tooutputdiv')\">";
echo "</td><td><div id='tooutputdiv'></div>";
echo "</td></tr><tr><td></td><td>";
echo "</td></tr>";

echo "<tr><td valign='top' align='right'>Aantal {$currency}</td><td>";
echo "<input type='text' id='amount' name='amount' size='10' ";
echo ">";
echo "</td><td>";
echo "</td></tr>";
echo "<tr><td></td><td>";
echo "</td></tr>";

echo "<tr><td valign='top' align='right'>Dienst</td><td>";
echo "<input type='text' name='description' id='description' size='40' MAXLENGTH='60' ";
echo ">";
echo "</td><td>";
echo "</td></tr><tr><td></td><td>";
echo "</td></tr>";
echo "<tr><tr><td colspan='3'>&nbsp;</td></tr><td></td><td colspan='2'>";
echo "<input type='submit' name='zend' id='zend' value='Overschrijven'>";
echo "</td></tr></table>";
echo "</form>";
echo "<script type='text/javascript'>loaduser('letscode_from','fromoutputdiv')</script>";
echo "</div>";

echo "<script type='text/javascript'>document.getElementById('letscode_from').value = '$s_letscode';</script>";

////////// output div
// echo "<div id='serveroutput' class='serveroutput'>";
// echo "</div>";


echo "<table border=0 width='100%'><tr><td align='left'>";
$myurl="userlookup.php";
echo "<form id='lookupform'><input type='button' id='lookup' value='LETSCode opzoeken' onclick=\"javascript:newwindow=window.open('$myurl','Lookup','width=600,height=500,scrollbars=yes,toolbar=no,location=no,menubar=no');\"></form>";

echo "</td><td align='right'>";
echo "</td></tr></table>";

include($rootpath."includes/inc_footer.php");

///////////////////////////////////////////////////////

function show_notify(){
	echo "<p><small><i>LETS Groep moet je enkel wijzigen voor Interlets transacties met andere eLAS installaties,<br>de standaard selectie is je eigen groep (of groepen op dezelfde installatie). </i></small></p>";
}

// Make timestamps for SQL statements
function make_timestamp($timestring){
        $month = substr($timestring, 3, 2);
        $day = substr($timestring, 0, 2);
        $year = substr($timestring, 6, 4);
        $timestamp = mktime(0, 0, 0, $month, $day, $year);
        return $timestamp;
}

function render_selector_options($option_ary, $selected)
{
	foreach ($option_ary as $key => $value)
	{
		echo '<option value="' . $key . '"';
		echo ($key == $selected) ? ' selected="selected"' : '';
		echo '>' . htmlspecialchars($value, ENT_QUOTES) . '</option>';
	}
}




