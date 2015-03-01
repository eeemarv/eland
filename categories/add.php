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
	$list_cats = get_cats();
	if (isset($_POST["zend"])){
		$posted_list = array();
		$posted_list["name"] = $_POST["name"];
		$posted_list["id_parent"] = $_POST["id_parent"];
		$posted_list["leafnote"] = $_POST["leafnote"];
		$error_list = validate_input($posted_list);
				
		if (!empty($error_list)){
			//lege velden dus toon form met errorlijst en ook met reeds geposte waarden
			
			show_form($error_list, $posted_list, $list_cats);	
		}else{
			//geen lege velden dus insert msg
			insert_cat($posted_list, $s_id);
			redirect_overview();
		}
	}else{
		show_form($error_list, $posted_list, $list_cats);
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
	echo "<h1>Categorie toevoegen</h1>";
}

function redirect_overview(){
	header("Location: overview.php");
}

function insert_cat($posted_list, $s_id){
	global $db;
	//de nieuwe cat is een hoofdcategorie
	if($posted_list["leafnote"] == 0){
		$posted_list["id_parent"] = 0;
		$posted_list["id_creator"] = $s_id;
		$posted_list["fullname"] = $posted_list["name"];
		$posted_list["leafnote"] = 0;
		$posted_list["cdate"] = date("Y-m-d H:i:s");
		$result = $db->AutoExecute("categories", $posted_list, 'INSERT');
	}else{
		//de nieuwe cat is een subcategorie
		$q="SELECT * FROM categories WHERE id=".$posted_list["id_parent"] ;
		$nam = $db->GetRow($q);
		$posted_list["id_creator"] = $s_id;
		$posted_list["fullname"] = $nam["name"]." - ".$posted_list["name"];
		$posted_list["leafnote"] = 1;
		$posted_list["cdate"] = date("Y-m-d H:i:s");
		$result = $db->AutoExecute("categories", $posted_list, 'INSERT');
	} 
}

	

function validate_input($posted_list){
	$error_list = array();
	if (!isset($posted_list["name"])|| (trim($posted_list["name"])=="")){
		$error_list["name"]="<font color='#F56DB5'>Vul <strong>naam</strong> in!</font>";
	}
	if (!isset($posted_list["id_parent"])|| (trim($posted_list["id_parent"])=="")){
	$error_list["id_parent"]="<font color='#F56DB5'>Vul <strong>hoofdrubriek</strong> in!</font>";
	}
	if (!isset($posted_list["leafnote"])|| (trim($posted_list["leafnote"])=="")){
		$error_list["leafnote"]="<font color='#F56DB5'>Vul <strong>vrijbriefk</strong> in!</font>";
	}
	return $error_list;
}

function show_form($error_list, $posted_list, $list_cats){
	echo "<div class='border_b'>";
	echo "<form method='POST' action='add.php'>";
	echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>";
	echo "<tr><td valign='top' align='right'>Naam </td><td>";
	echo "<input type='text' name='name' size='30' ";
	if (isset($posted_list["name"])){
		echo  "value ='".$posted_list["name"]."'>";
	}		
	echo "</td><td>";
	if(isset($error_list["name"])){
		echo $error_list["name"];
	}
	echo "</td></tr>";
		
	echo "<tr><td valign='top' align='right'>Is dit een <br>hoofdcategorie? </td>";
	echo "<td valign='top'>";
	echo "<input type='radio' name='leafnote' value='0'> Ja ";
	echo "<input type='radio' name='leafnote' value='1' checked=''> Nee ";
	echo "</td><td>";
	echo "</td></tr>";
		
	echo "<tr><td valign='top' align='right'>Indien niet, <br>kies hoofdcategorie</td>";
	echo "<td valign='top'>";
	echo "<select name='id_parent'>";
		foreach ($list_cats as $value){
			if ($posted_list["id_parent"] == $value["id"]){
				echo "<option value='".$value["id"]."' SELECTED>\n";
			}else{
				echo "<option value='".$value["id"]."' >\n";
			}
		echo htmlspecialchars($value["name"],ENT_QUOTES)."\n";
		echo "</option>\n";
	}
	echo "</select>";
	echo "</td><td>";
	echo "</td></tr>";
	
	echo "<tr><td colspan='2' align='right'>";
	echo "<input type='submit' name='zend' value='Toevoegen'>";
	echo "</td><td>&nbsp;</td></tr></table>";
	echo "</form>";
	echo "</p></div>";
}

function get_cats(){
	global $db;
	$query = "SELECT * FROM categories WHERE leafnote = 0 ORDER BY name";
	$list_cats = $db->GetArray($query);
	return $list_cats;
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
