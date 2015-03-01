<?php
ob_start();
$rootpath = "../";

// set the config array otherwise we'll get a loop
$nocheckconfig=TRUE;
require_once($rootpath."includes/inc_config.php");

// get the initial includes
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

session_start(); 
global $_SESSION;
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];
	
include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

show_ptitle();
if (isset($_POST["zend"])){
		$posted_list = array();
		$posted_list["name"] = $_POST["name"];
		$posted_list["tag"] = $_POST["tag"];
		$posted_list["currency"] = $_POST["currency"];
		$posted_list["sessionname"] = $_POST["sessionname"];
		$posted_list["dsn"] = $_POST["dsn"];
		$error_list = validate_input($posted_list);

		if (!empty($error_list)){
			$configuration["db"]["dsn"] = $posted_list["dsn"];
			$configuration["system"]["currency"] = $posted_list["currency"];
			$configuration["system"]["systemtag"] = $posted_list["tag"];
			$configuration["system"]["systemname"] = $posted_list["name"];
			$configuration["system"]["sessionname"] = $posted_list["sessionname"];
//			configuration_save($configuration);
			show_form($posted_list, $error_list);	
		}else{
			$configuration["db"]["dsn"] = $posted_list["dsn"];
			$configuration["system"]["currency"] = $posted_list["currency"];
			$configuration["system"]["systemtag"] = $posted_list["tag"];
			$configuration["system"]["systemname"] = $posted_list["name"];
			$configuration["system"]["sessionname"] = $posted_list["sessionname"];
			$configuration["db"]["schema_version"] = "1.0";
			configuration_save($configuration);
			db_create();
//			show_form($posted_list, $error_list);	
			redirect_index($rootpath);			
			 
		}
	}else{
		$default_list["dsn"] = $configuration["db"]["dsn"];
		$default_list["currency"] = $configuration["system"]["currency"] ;
		$default_list["tag"] = $configuration["system"]["systemtag"] ;
		$default_list["name"] = $configuration["system"]["systemname"] ;
		$default_list["sessionname"] = $configuration["system"]["sessionname"] ;
		show_form($default_list,$error_list );
	}




////////////////////////////////////////////////////////////////////////////
////////////////////////////////F U N C T I E S ////////////////////////////
////////////////////////////////////////////////////////////////////////////

function validate_input($posted_list){
	$error_list = array();
	if (!isset($posted_list["dsn"])|| $posted_list["dsn"]==""){
		$error_list["dsn"]="<font color='#F56DB5'>Databasenaam is verplicht!</font>";
	}else {
	        $db_dsn=$posted_list["dsn"];
	        $db =ADONewConnection($db_dsn);

		$error_list["dsn"]=getadoerror();
		if ($error_list["dsn"] == ""){
//		    $error_list["dsn"] = "Database connection successful. ";
		}
		if (is_object($db)) {
//		    $error_list["dsn"] .= "Adodb Version " .$db->Version();
		}
	}
	return $error_list;

}

function show_ptitle(){
	echo "<h1>Set-up</h1>";
}
function show_form($value_list, $error_list){
	echo "<div class='border_b'>";
	echo "<form method='POST' action='index.php'>\n";
	echo "<table class='data' cellspacing='0' cellpadding='0' border='0'>\n\n";

	echo "<tr>\n<td valign='top' align='right'>Database Connection string</td>\n";
	echo "<td valign='top'><input type='text' name='dsn' size='20' ";
	if (isset($value_list["dsn"])){
		echo " value='" .$value_list["dsn"]."' ";
	}
	echo "></td>\n</tr>\n\n";
	echo "<tr>\n<td></td>\n<td valign='top'>";
	if(isset($error_list["dsn"])){
		echo $error_list["dsn"];
	}
	echo "</td>\n</tr>\n\n";



	echo "<tr>\n<td valign='top' align='right'>Session Name</td>\n";
	echo "<td valign='top'><input type='text' name='sessionname' size='20' ";
	if (isset($value_list["sessionname"])){
		echo " value='" .$value_list["sessionname"]."' ";
	}
	echo "></td>\n</tr>\n\n";
	echo "<tr>\n<td></td>\n<td valign='top'>";
	if(isset($error_list["sessionname"])){
		echo $error_list["sessionname"];
	}
	echo "</td>\n</tr>\n\n";



	echo "<tr>\n<td valign='top' align='right'>System Currency</td>\n";
	echo "<td valign='top'><input type='text' name='currency' size='20' ";
	if (isset($value_list["currency"])){
		echo " value='" .$value_list["currency"]."' ";
	}
	echo "></td>\n</tr>\n\n";
	echo "<tr>\n<td></td>\n<td valign='top'>";
	if(isset($error_list["currency"])){
		echo $error_list["currency"];
	}
	echo "</td>\n</tr>\n\n";

	echo "<tr>\n<td valign='top' align='right'>System Tag</td>\n";
	echo "<td valign='top'><input type='text' name='tag' size='20' ";
	if (isset($value_list["tag"])){
		echo " value='" .$value_list["tag"]."' ";
	}
	echo "></td>\n</tr>\n\n";
	echo "<tr>\n<td></td>\n<td valign='top'>";
	if(isset($error_list["tag"])){
		echo $error_list["tag"];
	}
	echo "</td>\n</tr>\n\n";


	echo "<tr>\n<td valign='top' align='right'>System Name</td>\n";
	echo "<td valign='top'><input type='text' name='name' size='20' ";
	if (isset($value_list["name"])){
		echo " value='" .$value_list["name"]."' ";
	}
	echo "></td>\n</tr>\n\n";
	echo "<tr>\n<td></td>\n<td valign='top'>";
	if(isset($error_list["name"])){
		echo $error_list["name"];
	}
	echo "</td>\n</tr>\n\n";

	echo "<tr>\n<td colspan='2'>";
	echo "<input type='submit' name='zend' value='OK'>";
	echo "</td>\n</tr>\n</table>\n";
	echo "</form>";
	echo "</p></div>";

}

function redirect_index($rootpath){
	header("Location: ".$rootpath."index.php");
}

function db_create(){
}
include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
