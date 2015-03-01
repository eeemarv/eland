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

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

if(isset($s_id) && ($s_accountrole == "admin")){
	show_ptitle();

	$id = $_GET["id"];
	if(isset($id)){
		if(isset($_POST["zend"])){
			$cat = get_cat($id);
			$cat["name"] = $_POST["name"];
			$error_list = validate_input($cat);
				if (!empty($error_list)){
					show_form($cat, $error_list);
				}else{
					update_cat($cat);
					redirect_overview();
				}
		}else{
			$cat = get_cat($id);
			show_form($cat, $error_list);
		}
	}else{ 
		redirect_overview();
	}
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
	echo "<h1>Categorie aanpassen</h1>";
}

function validate_input($posted_list){
	$error_list = array();
	if (!isset($posted_list["name"])|| (trim($posted_list["name"] )=="")){
		$error_list["name"]="<font color='#F56DB5'>Vul <strong>naam</strong> in!</font>";
	}
	return $error_list;
}

function update_cat($cat){
	global $db;
	$id = $cat["id"];
	if ($cat["id_parent"]==0){
		$cat["fullname"] = $cat["name"];
	}else{
		//$result = mysql_query($q) or die("geen q1");
		//$nam = mysql_fetch_array($result, MYSQL_ASSOC);
		$q="SELECT * FROM categories WHERE id=".$cat["id_parent"] ;
		$parentcat = $db->GetRow($q);
	 	$cat["fullname"] = $parentcat["name"]. " - ". $cat["name"];
    }
	$result = $db->AutoExecute("categories", $cat, 'UPDATE', "id=$id");
}

function show_form($cat, $error_list){
	echo "<div class='border_b'><p>";
	echo "<form action='edit.php?id=".$cat["id"]."' method='POST'>";
	echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>";
	echo "<tr><td valign='top' align='right'>Naam </td><td>";
	echo "<input type='text' name='name' size='60' ";
	echo "value='". $cat["name"]. "'>";  
	echo "</td><td>";
	if (isset($error_list["name"])){
		echo $error_list["name"];
	}
	echo "</td></tr>";
	echo "<tr><td valign='top' align='right'>Volledige naam </td><td>";
	echo "<i>";
        echo $cat["fullname"];
	echo "</i>";
        echo "</td><td>";
	echo "<tr><td colspan='2' align='right'>";
	echo "<input type='submit' value='Opslaan' name='zend'>";
	echo "</td><td>&nbsp;</td></tr></table>";
	echo "</form>";
	echo "</p></div>";
}

function get_cat($id){
    global $db;
	$query = "SELECT * FROM categories WHERE id=".$id;
	$cat = $db->GetRow($query);
	return $cat;
}

function redirect_overview(){
	header("Location: overview.php");
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>

