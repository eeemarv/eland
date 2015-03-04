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

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

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

		echo "<script type='text/javascript' src='". $rootpath ."js/msgpicture.js'></script>";
		echo "<table class='data' border='1' width='95%'>";
		echo "<tr>";

		// The picture table is nested
		echo "<td valign='top'>";

		$msgpictures = get_msgpictures($msgid);
		echo "<table class='data' border='1'>";
		echo "<tr><td colspan='4' align='center'><img id='mainimg' src='" .$rootpath ."gfx/nomsg.png' width='200'></img></td></tr>";
		echo "<tr>";
		$picturecounter = 1;
		foreach($msgpictures as $key => $value){
			$file = $value["PictureFile"];
			$url = $rootpath ."/sites/" .$dirbase ."/msgpictures/" .$file;
			echo "<td>";
			if($picturecounter == 1) {
				 echo "<script type='text/javascript'>loadpic('$url')</script>";
			}
			if ($picturecounter <= 4) {
				$picurl="showpicture.php?id=" .$value["id"];
				echo "<img src='/sites/" .$dirbase ."/msgpictures/$file' width='50' onmouseover=loadpic('$url') onclick=window.open('$picurl','Foto','width=800,height=600,scrollbars=yes,toolbar=no,location=no')></td>";
			}
			$picturecounter += 1;
		}
		echo "</tr>";
		//echo "<tr><td><img src='../msgpictures/nomsg.png' width='83'></td><td><img src='../msgpictures/nomsg.png' width='83'></td><td><img src='../msgpictures/nomsg.png' width='83'></td><td><img src='../msgpictures/nomsg.png' width='83'></td></tr>";
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
		show_contact($message["id_user"]);
		echo "</td>";
		//End contact info

		//Response form
		echo "<td>";
		show_response_form($msgid, $usermail,$s_accountrole);
		echo "</td>";
		//End response form

                echo "</tr>";
		echo "</table>";

		if($s_accountrole == "admin" || $s_id == $message["id_user"]){
			show_editlinks($msgid);
		}
	}else{
		redirect_searchcat_viewcat();
	}
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_editlinks($msgid){
        echo "<table width='100%' border=0><tr><td>";
        echo "<div id='navcontainer'>";
        echo "<ul class='hormenu'>";
        $myurl="edit.php?mode=edit&id=$msgid";
        echo "<li><a href='#' onclick=window.open('$myurl','message_edit','width=640,height=800,scrollbars=yes,toolbar=no,location=no,menubar=no')>Aanpassen</a></li>";
	$myurl="upload_picture.php?msgid=$msgid";
        echo "<li><a href='#' onclick=window.open('$myurl','upload_picture','width=640,height=480,scrollbars=yes,toolbar=no,location=no,menubar=no')>Foto toevoegen</a></li>";
	$myurl="delete.php?id=$msgid";
	echo "<li><a href='#' onclick=window.open('$myurl','message_delete','width=640,height=480,scrollbars=yes,toolbar=no,location=no,menubar=no')>Verwijderen</a></li>";
	//if(readconfigfromdb("share_enabled") == 1){
	//	$myurl="share.php?id=$msgid";
    //    	echo "<li><a href='#' onclick=window.open('$myurl','message_send','width=640,height=480,scrollbars=yes,toolbar=no,location=no,menubar=no')>Delen</a></li>";
	//}
	echo "</ul>";
        echo "</div>";
        echo "</td></tr></table>";
}

function show_response_form($msgid,$usermail,$s_accountrole){
	echo "<div id='responseformdiv'>";
	echo "<script type='text/javascript' src='/js/postresponse.js'></script>";
	echo "<table border='0'>";
	echo "<tr><td colspan='2'>";
	echo "<form action=\"javascript:getresponse(document.getElementById('response'))\" id='response'>";
	echo "<INPUT TYPE='hidden' id='myid' VALUE='" .$msgid ."'>";
	//echo "<input type='text' id='myid' value='1'>";
	echo "<TEXTAREA NAME='reactie' id='reactie' COLS=60 ROWS=6 ";
	if(empty($usermail) || $s_accountrole == 'guest'){
		echo "DISABLED";
	}
	echo ">Je reactie naar de aanbieder";
	echo "</TEXTAREA>";
	echo "</td></tr><tr><td>";
	echo "<input type='checkbox' name='cc' id='cc' CHECKED value='1' >Stuur een kopie naar mijzelf";
	echo "</td><td>";
	echo "<input type='submit' name='zend' id='zend' value='Versturen'";
	if(empty($usermail) || $s_accountrole == 'guest'){
                echo "DISABLED";
        }
	echo ">";
	echo "</form>";
	echo "</td></tr>";
	echo "</table>";
	//echo "</form>";
	echo "</div>";

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

function get_msgpictures($id){
	global $db;
	$query = "SELECT * FROM msgpictures WHERE msgid = " .$id;
	$msgpictures = $db->GetArray($query);
        return $msgpictures;
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

function show_contact($id){
	echo "<div id='contactinfo'></div>";
	echo "<script type='text/javascript'>showsmallloader('contactinfo');loadurlto('rendercontact.php?id=$id', 'contactinfo')</script>";
}

function show_msg($message,$balance){
	global $baseurl;
	global $msgid;
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

	//DISABLE TAGS UNTIL WE HAVE SUPPORT FOR THEM
        //echo "<tr class='even_row'><td>";
	//echo "Tags: ";
	//echo "</td></tr>";

	//empty row
	echo "<tr><td>&nbsp</td></tr>";

	echo "<tr class='even_row'><td valign='bottom'>";
	if (!empty($message["amount"])){
		echo "De (vraag)prijs is " .$message["amount"] ." " .$currency ." per " .$message["units"];
	} else {
		echo "Er werd geen (vraag)prijs ingegeven";
	}
	echo "</td></tr>";

	//Direct URL
	echo "<tr class='even_row'><td>";
	$directurl="http://" .$baseurl ."/login.php?redirectmsg=" .$msgid;
	echo "Directe link: <a href='" .$directurl ."'>" .$directurl ."</a>";
	echo "<br><i><small>Deze link brengt leden van je groep rechtstreeks bij dit V/A</small></i>";
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

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
