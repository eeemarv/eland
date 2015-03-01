<?php
ob_start();
$rootpath = "";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

if(isset($s_id)){
	if (isset($_GET["id"])){
		$id = $_GET["id"];
		show_ptitle();
		$user = get_user($id);
		show_user($user);
		$contact = get_contact($id);
		show_contact($contact);
		$balance = $user["saldo"];
		$currency = readconfigfromdb("currency");
		show_balance($balance,$currency);
		$msg = get_messages($id);
		show_msg($msg);
	}else{
		redirect_memberlist();
	}
}else{
	redirect_login($rootpath);
}


echo <<<EOF
	<link rel="stylesheet" type="text/css" href="graphs/jqplot/jquery.jqplot.min.css" />
	<link rel="stylesheet" type="text/css" href="graphs/css/tooltip.css" />
	<script type="text/javascript">var user_id = {$id};</script>	
	<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.10.1/jquery.min.js"></script>
	<script src="graphs/jqplot/jquery.jqplot.min.js"></script>
	<script src="graphs/jqplot/plugins/jqplot.donutRenderer.min.js"></script>
	<script src="graphs/jqplot/plugins/jqplot.cursor.min.js"></script>
	<script src="graphs/jqplot/plugins/jqplot.dateAxisRenderer.min.js"></script>
	<script src="graphs/jqplot/plugins/jqplot.canvasTextRenderer.min.js"></script>
	<script src="graphs/jqplot/plugins/jqplot.canvasAxisTickRenderer.min.js"></script>
	<script src="graphs/jqplot/plugins/jqplot.highlighter.min.js"></script>	
	<script src="graphs/js/user_transactions.js"></script>
EOF;

//	<script src="graphs/jqplot/plugins/jqplot.pointLabels.min.js"></script>
////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_balance($balance,$currency){
	echo "<table  cellpadding='0' cellspacing='0' border='0'  width='99%'>";
	echo "<tr class='even_row'>";
	echo "<td><strong>{$currency}stand</strong></td><td></td><td><strong>Transactie-Interacties</strong></td></tr>";
	echo "<tr><td>";
	echo "<strong>".$balance."</strong>";
	echo "</td><td><div id='chartdiv1' style='height:200px;width:300px;'></div></td>";
	echo "<td><div id='chartdiv2' style='height:200px;width:200px;'></div></td></tr></table>";
}

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle(){
	echo "<h1>Contactlijst</h1>";
}

function show_user($user){
	global $baseurl;
	global $dirbase;
	global $rootpath;
	echo "<table cellpadding='0' cellspacing='0' border='0' width='99%'>";
	echo "<tr class='even_row'>";

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
		echo "<img src='" .$rootpath ."gfx/nouser.png' width='150'></img>";
	} else {
        	echo "<img src='" .$rootpath ."sites/$dirbase/userpictures/" .$user["PictureFile"] ."' width='150'></img>";
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
	    
	    echo "<tr><td width='50%' valign='top'>LETS Chatter ID:  </td>";
	    $chatter_url = readconfigfromdb("ostatus_url");
        echo "<td width='50%' valign='top'>";
        echo "<a href='" . $chatter_url ."/" .$user["ostatus_id"] ."'>";
        echo $user["ostatus_id"];
        echo "</a></td></tr>";
                
		echo "<tr><td valign='top'>Hobbies/interesses: </td>";
		echo "<td valign='top'>".htmlspecialchars($user["hobbies"],ENT_QUOTES)."</td></tr>";
		echo "<tr><td valign='top'>Commentaar: </td>";
		echo "<td valign='top'>".htmlspecialchars($user["comments"],ENT_QUOTES)."</td></tr>";
		echo "</table>";
	echo "</td>";
	echo "</table>";
}

function get_user($id){
	global $db;
	$query = "SELECT * FROM users ";
	$query .= "WHERE id=".$id;
	$user = $db->GetRow($query);
	return $user;
}

function get_messages($id){
	global $db;
	$query = "SELECT *, ";
	$query .= " messages.validity AS valdate ";
	$query .= " FROM messages ";
	$query .= " WHERE id_user=".$id ;
	$query .= " order by msg_type" ;
	$msg = $db->GetArray($query);
	return $msg;
}

function show_msg($msg){
	echo "<div class='border_b'><p>";
	echo "<table cellpadding='0' cellspacing='0' border='0' width='99%'>";
	echo "<tr><td colspan='3'><p>&#160;</p></td></tr>";
	echo "<tr class='even_row'><td colspan='3'><p><strong>Vraag & Aanbod</strong></p></td></tr>";
	foreach($msg as $key => $value){
		echo "<tr><td valign='top'>";
		if ($value["msg_type"] == 0){
 			echo "V: ";
		}elseif($value["msg_type"] == 1){
			echo "A: ";
		} 
		echo "</td>";
		echo "<td valign='top'><a href='messages/view.php?id=".$value["id"]."'>".nl2br(htmlspecialchars($value["content"],ENT_QUOTES))."</a></td>";
		echo "</tr>";
	}
	//echo "<tr><td colspan='3'><p>&#160;</p></td></tr>";
	echo "</table></div>";
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
$query .= " AND contact.flag_public = 1";
	$contact = $db->GetArray($query);
	return $contact;
}

function show_contact($contact){
	echo "<table cellpadding='0' cellspacing='0' border='0' width='99%'>";
	echo "<tr ><td colspan='3'><p>&#160;</p></td></tr>";
	echo "<tr class='even_row'><td colspan='3'><p><strong>Contactinfo</strong></p></td></tr>";
	foreach($contact as $key => $value){
		echo "<tr><td>".$value["name"].": </td>";
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
	echo "<tr><td colspan='3'><p>&#160;</p></td></tr>";
	echo "</table>";
	
}



function redirect_memberlist(){
	header("Location: memberlist.php");
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>

