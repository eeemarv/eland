<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");   
session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

if(isset($s_id)){
	$msgid = $_GET["id"];
	if(isset($msgid)){
		$message = get_msg($msgid);
		$title = $message["content"];

		$contact = get_contact($message["id_user"]);
		$mailuser = get_user_maildetails($message["id_user"]);
		$usermail = $mailuser["emailaddress"];
		$user = get_user($message["id_user"]);
		$balance = $user["saldo"];

		echo "<table class='data' border='1' width='100%'>";
		echo "<tr>";

		// The picture table is nested
		echo "<td valign='top'>";

		echo "<table class='data' border='1' width='250'>";
		echo "<tr><td colspan='3'><img src='../msgpictures/nomsg.png'></img></td></tr>";
		echo "<tr><td><img src='../msgpictures/nomsg.png' width='83'></td><td><img src='../msgpictures/nomsg.png' width='83'></td><td><img src='../msgpictures/nomsg.png' width='83'></td></tr>";
		echo "</td>";
		echo "</table>";

		echo "</td>";
		// end picture table
	
		// Show message
		echo "<td valign='top'>";
		show_msg($message,$balance);
		echo "</td>";
		// End message

		echo "</tr>";

		echo "<tr>";

		//Contact info goes here
                echo "<td width='254' valign='top'>";
		show_contact($contact);	
		echo "</td>";
		//End contact info

		//Response form
		echo "<td>";
		show_response_form($msgid, $usermail);	
		echo "</td>";
		//End response form

                echo "</tr>";
		echo "</table>";

	}else{
		redirect_searchcat_viewcat();
	}
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_response_form($msgid,$usermail){
	echo "<form action='respond.php' method='post'>";
	echo "<table border='0'>";
	echo "<tr><td colspan='2'>";
	echo "<INPUT TYPE=hidden NAME=msgid VALUE='" .$msgid ."'>";
	echo "<TEXTAREA NAME='reactie' COLS=60 ROWS=6 ";
	if(empty($usermail)){
		echo "DISABLED";
	}
	echo ">Je reactie naar de aanbieder";
	echo "</TEXTAREA>";
	echo "</td></tr><tr><td>";
	echo "<input type='checkbox' name='cc' CHECKED value='1' >Stuur een kopie naar mijzelf";
	echo "</td><td>";
	echo "<input type='submit' name='zend' value='Versturen'";
	if(empty($usermail)){
                echo "DISABLED";
        }
	echo ">";
	echo "</td></tr>";
	echo "</table>";
	echo "</form>";
}

function get_msg($msgid){
	global $db;
	$query = "SELECT * , ";
	$query .= " messages.cdate AS date, ";
	$query .= " messages.validity AS valdate";
	$query .= " FROM messages, users ";
	$query .= " WHERE messages.id = ". $msgid;
	$query .= " AND messages.id_user = users.id ";
	$message = $db->GetRow($query);
	return $message;
}

function show_balance($balance,$currency){
	echo "<table cellpadding='0' cellspacing='0' border='0' width='99%'>";
	echo "<tr class='even_row'><td>";
	echo "<strong>{$currency}stand</strong></td></tr>";
	echo "<tr ><td>";
	echo $balance;
	echo "<br><br>";
	echo "</td></tr></table>";
}

function show_contact($contact){
	echo "<table cellpadding='0' cellspacing='0' border='0' width='100%'>";
	echo "<tr class='even_row'><td colspan='3'><p><strong>Contactinfo</strong></p></td></tr>";
	foreach($contact as $key => $value){
		echo "<tr><td>".$value["abbrev"].": </td>";
                if($value["abbrev"] == "mail"){
                        echo "<td><a href='mailto:".$value["value"]."'>".$value["value"]."</a></td>";
                }elseif($value["abbrev"] == "adr"){
                        echo "<td><a href='http://maps.google.be/maps?f=q&source=s_q&hl=nl&geocode=&q=".$value["value"]."' target='new'>".$value["value"]."</a></td>";
                } else {
                        echo "<td>".$value["value"]."</td>";
                }
		echo "<td></td>";
		echo "</tr>";
	}
	echo "</table>";
}
	
function show_msg($message,$balance){
	$currency = readconfigfromdb("currency");
	echo "<table cellspacing='0' cellpadding='0' border='0' width='100%'>";
	echo "<tr class='even_row'><td>";
	echo "<p><strong><font size='+1'><i>";
	if($message["msg_type"]==0){
		echo "Vraag:  ";
	}elseif($message["msg_type"]==1){
		echo "Aanbod: ";
	}
	echo "</i>";
	//echo htmlspecialchars($message["name"],ENT_QUOTES)." (" .trim($message["letscode"])."): ".$message["content"];
	echo htmlspecialchars($message["content"]);
	echo "</font></strong><br>";
	echo htmlspecialchars($message["fullname"],ENT_QUOTES) ." - " .trim($message["letscode"]);
	echo "<i> (stand: " .$balance ." " .$currency .")</i>";
	echo "</td></tr>";
	echo "<tr><td>";
	if (!empty($message["Description"])){
		echo nl2br(htmlspecialchars($message["Description"],ENT_QUOTES));
	} else {
		echo "<i>Er werd geen omschrijving ingegeven</i>";
	}
	echo "</td></tr>";

	//empty row
        echo "<tr><td>&nbsp</td></tr>";

	echo "<tr><td>Geldig tot: " .$message["valdate"]."<tr><td>";

        echo "<tr class='even_row'><td>";
	echo "Tags: ";
	echo "</td></tr>";

	//empty row
	echo "<tr><td>&nbsp</td></tr>";

	echo "<tr class='even_row'><td>";
	if (!empty($message["amount"])){
		echo "De (vraag)prijs is " .$message["amount"] ." " .$currency ." per " .$message["units"];
	} else { 
		echo "Er werd geen (vraag)prijs ingegeven";
	}
	echo "</td></tr>";

	echo "</table>";
} 

function show_user($user){
	echo "<table cellspacing='0' cellpadding='0' border='0'>";
	echo "<tr><td>Postcode: </td>";
	echo "<td>".$user["postcode"]."</td></tr>";
	echo "<tr><td colspan='2'><p>&#160;</p></td></tr>";
	echo "</table>";
}



function show_title($title){
	echo "<h1>$title</h1>";
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function redirect_searchcat_viewcat(){
	header("Location: searchcat_viewcat.php");
}
?>
