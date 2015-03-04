<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

include($rootpath."includes/inc_smallheader.php");
include($rootpath."includes/inc_content.php");

$id = $_GET["id"];
$mode = $_GET["mode"];
$accountrole = $s_accountrole;

if(isset($s_id) && $accountrole != "guest" && $accountrole != "interlets"){
	show_ptitle($mode);
	show_form();
	if($mode == "edit"){
		//Load the current values
		loadvalues($id);
	} else {
		writecontrol("mode", "new");
		writecontrol("id_user", $s_id);
	}

/**
 		$user_list = get_user()

		$cat_list = get_cat();
		if(isset($_POST["zend"])){
	                $validity = $_POST["validity"];
                        $vtime = count_validity($validity);
                        $posted_list = array();

                        $posted_list["vtime"] = $vtime;
			$posted_list["content"] = $_POST["content"];
			$posted_list["msg_type"] = $_POST["msg_type"];
			$posted_list["id_user"] = $_POST["id_user"];
			$posted_list["id_category"] = $_POST["id_category"];
			$posted_list["id"] = $_GET["id"];
			$error_list = validate_input($posted_list);
				if (!empty($error_list)){
					$msg = get_msg($id);
					show_form();
				}else{
					update_msg($id, $posted_list);
				}
		}else{
			$msg = get_msg($id);
			show_form($msg, $posted_list, $error_list, $user_list, $cat_list, $accountrole);
		}
**/
        show_serveroutputdiv();
        show_closebutton();
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function show_ptitle($mode){
	if($mode == "new"){
		echo "<h1>Nieuw Vraag & Aanbod toevoegen</h1>";
	} else {
		echo "<h1>Vraag & Aanbod aanpassen</h1>";
	}
}

// FIXME: Geldigheid wordt niet geladen....
function loadvalues($msgid){
	$msg = get_msg($msgid);
	writecontrol("mode", "edit");
	writecontrol("id" , $msg["id"]);
	writecontrol("msg_type", $msg["msg_type"]);
	writecontrol("id_user", $msg["id_user"]);
	$category = $msg["id_category"];
	writecontrol("id_category", $category);
	writecontrol("content", $msg["content"]);
	writecontrol("description", $msg["Description"]);
	writecontrol("amount", $msg["amount"]);
	writecontrol("units", $msg["units"]);
}

function writecontrol($key,$value){
	$value = str_replace("\n", '\n', $value);
	$value = str_replace('"',"'",$value);
	echo "<script type=\"text/javascript\">document.getElementById('" .$key ."').value = \"" .$value ."\";</script>";
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

function validate_input($posted_list){
	global $db;
	$error_list = array();
	if (empty($posted_list["content"]) || (trim($posted_list["content"]) == ""))
		$error_list["content"] = "<font color='#F56DB5'>Vul <strong>inhoud</strong> in!</font>";
		$query =" SELECT * FROM categories ";
		$query .=" WHERE  id = '".$posted_list["id_category"]."' ";
		$rs = $db->Execute($query);
    	$number = $rs->recordcount();
		if( $number == 0 ){
			$error_list["id_category"]="<font color='#F56DB5'>Categorie <strong>bestaat niet!</strong></font>";
	}

	$query = "SELECT * FROM users ";
	$query .= " WHERE id = '".$_POST["id_user"]."'" ;
	$query .= " AND status <> '0'" ;
	$rs = $db->Execute($query);
    $number2 = $rs->recordcount();

	if( $number2 == 0 ){
		$error_list["id_user"]="<font color='#F56DB5'>Gebruiker <strong>bestaat niet!</strong></font>";
	}
	return $error_list;

}

function count_validity($validity){
        $valtime = time() + ($validity*30*24*60*60);

        $vtime =  date("Y-m-d H:i:s",$valtime);
        return $vtime;
}

function update_msg($id, $posted_list){
    global $db;
    $posted_list["validity"] = $posted_list["vtime"];
    $posted_list["mdate"] = date("Y-m-d H:i:s");
    $result = $db->AutoExecute("messages", $posted_list, 'UPDATE', "id=$id");
}

function show_form(){
	global $s_accountrole;
	$accountrole = $s_accountrole;
	echo "<script type='text/javascript' src='/js/postmessage.js'></script>";

	$user_list = get_user();
	$cat_list = get_cat();

	if($accountrole == 'admin'){
		echo "<strong>[admin mode]</strong><br>";
	}
	//echo "<strong>Geldig tot ".$msg["valdate"]."</strong>";

	echo "<div class='border_b'><p>";
	//echo "<form action= method='POST'>";
	echo "<form action=\"javascript:showloader('serveroutput'); get(document.getElementById('msgform'));\" name='msgform' id='msgform'>";
	echo "<input type='hidden' name='mode' id='mode' size='4' value='new'>";
	echo "<input type='hidden' name='id' id='id' size='4'>";
	echo "<table  class='data'  cellspacing='0' cellpadding='0' border='0'>\n";
	echo "<tr>\n<td align='right'>";
	echo "V/A ";
	echo "</td>\n<td>";
	echo "<select name='msg_type' id='msg_type'>\n";
	echo "<option value='1'>Aanbod</option>";
	echo "<option value='0' >Vraag</option>";
	echo "</select>\n";
	echo "</td>\n</tr>\n\n<tr><td></td>\n<td></td>\n</tr>\n\n";

	echo "<tr><td valign='top' align='right'>Wat </td>\n<td>";
	echo "<textarea name='content' id='content' rows='2' cols='50'>";
	echo "</textarea>";
	echo "</td>\n</tr>\n\n<tr>\n<td></td>\n<td>";
	echo "</td>\n</tr>\n\n";

        echo "<tr><td valign='top' align='right'>Omschrijving </td>\n<td>";
        echo "<textarea name='description' id='description' rows='4' cols='50'>";
        echo "</textarea>";
        echo "</td>\n</tr>\n\n<tr>\n<td></td>\n<td>";
        echo "</td>\n</tr>\n\n";

	// Who selection is only for admins
	if($accountrole == "admin"){
		echo "<tr>\n<td align='right'>";
		echo "Wie";
		echo "</td>\n<td>";
		echo "<select name='id_user' id='id_user'>\n";
		foreach($user_list as $value){
			echo "<option value='".$value["id"]."'>";
			echo htmlspecialchars($value["name"],ENT_QUOTES)." (".trim($value["letscode"]).")";
			echo "</option>\n";
		}
		echo "</select>\n";
		echo "</td>\n</tr>\n\n<tr><td></td>\n<td>";
		echo "</td>\n</tr>\n\n";
	} else {
		echo "<input type='hidden' name='id_user' id='id_user' size='8'>";
	}

	echo "<tr>\n<td align='right'>";
	echo "Categorie ";
	echo "</td>\n<td>";
	echo "<select name='id_category' id='id_category'>\n";
	foreach($cat_list as $value3){
		echo "<option value='".$value3["id"]."' >";
		 echo htmlspecialchars($value3["fullname"],ENT_QUOTES);
		 echo "</option>\n";
	}

	echo "</select>\n";
	echo "</td>\n</tr>\n\n<tr>\n<td></td>\n<td>";

	echo "</td>\n\n</tr>\n\n";

	echo "<tr>\n<td valign='top' align='right'>Geldigheid </td>\n";

        echo "<td>";
        echo "<input type='text' name='validity' id='validity' size='4'> maanden\n";

        echo "</td>\n</tr>\n\n<tr>\n<td></td>\n<td>";
        echo "</td>\n</tr>\n";

	$currency = readconfigfromdb("currency");
	echo "<tr>\n<td valign='top' align='right'>Prijs </td>\n";
	echo "<td>";
        echo "<input type='text' name='amount' id='amount' size='8'> $currency\n";
	echo "</td>\n</tr>\n\n<tr>\n<td></td>\n<td>";
        echo "</td>\n</tr>\n";

	echo "<tr>\n<td valign='top' align='right'>Per </td>\n";
    echo "<td>";
    echo "<input type='text' name='units' id='units'> (uur, stuk, ...)\n";
    echo "</td>\n</tr>\n\n<tr>\n<td></td>\n<td>";
    echo "</td>\n</tr>\n";

    //echo "<tr><td valign='top' align='right'>Versturen naar mailinglists</td><td><input type=checkbox name='announce' id='announce' CHECKED></td>";

	echo "<tr>\n<td colspan='2' align='right'>";
	echo "<input type='submit' value='Opslaan' name='zend' id='zend'>";
	echo "</td>\n</tr>\n\n</table>\n\n";
	echo "</form>";
	echo "</p></div>";
}

function get_user(){
    global $db;
	$query = "SELECT * FROM users ";
	$query .= " WHERE status <> 0 order by letscode";
 	$user_list = $db->GetArray($query);
	return $user_list;
}

function get_cat(){
	global $db;
	$query = "SELECT * FROM categories WHERE leafnote=1 order by fullname";
	$cat_list = $db->GetArray($query);
	return $cat_list;
}

function get_msg($id){
	global $db;
	$query = "SELECT * , messages.validity AS valdate ";
	$query .= " FROM messages WHERE id=" .$id ;
	$msg = $db->GetRow($query);
	return $msg;
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_smallfooter.php");
?>
