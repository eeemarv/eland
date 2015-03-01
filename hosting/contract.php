<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_hosting.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

if(isset($s_id) && ($s_accountrole == "admin")){
	show_ptitle();
	echo "<h2>Provider details</h2>";
	//$provider = get_provider();
	//print_r($provider);
	echo "<table width='90%' border=0>";
	echo "<tr><td>Naam:</td><td>". $provider->providername ."</td></tr>";
	echo "<tr><td>Website: </td><td>". $provider->providerurl ."</td></tr>";
	echo "<tr><td>Contactpersoon:</td><td>". $provider->providercontact ."</td></tr>";
	echo "<tr><td>Contract E-mail:</td><td><a href='mailto:". $provider->billingemail ."'>" .$provider->billingemail ."</a></td></tr>";
	echo "</table>";

	echo "<h2>Contract informatie</h2>";
	$contract = get_contract();
	//var_dump($contract);
	echo "<table width='90%' border=0>";
	echo "<tr><td>Startdatum:</td><td>". $contract["start"] ."</td></tr>";
	echo "<tr><td>Volgende vervaldag:</td><td>". $contract["end"] ."</td></tr>";
	echo "<tr><td>Type betaling:</td><td>". $contract["paymenttype"] ."</td></tr>";
	echo "<tr><td>Contractperiode:</td><td>". $contract["period"] ."</td></tr>";
	if($contract["cost"] > 0){
		echo "<tr><td>Kostprijs:</td><td>". $contract["cost"] ."</td></tr>";
	}
	echo "<tr><td>Grace-periode:</td><td>". $contract["graceperiod"] ." dagen</td></tr>";
	echo "<tr><td>Groep contactpersoon:</td><td>". $contract["onsitecontact"] ."</td></tr>";
	echo "<tr><td>Groep E-mail adres:</td><td>". $contract["onsiteemail"] ."</td></tr>";
	echo "<tr><td>Saldo support credits:</td><td>". $contract["supportcredits"] ."</td></tr>";
	echo "</table>";
	echo "<p><small>Het support credit saldo wordt periodiek doorgestuurd en is dus niet volledig actueel</small></p>";
	
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
////////////////////////////////F U N C T I E S ////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_login($rootpath){
 	header("Location: ".$rootpath."login.php");
}

function show_ptitle(){
	echo "<h1>Hosting contract</h1>";
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
