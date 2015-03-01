<?php 
ob_start();
$rootpath = "./";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
//session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

global $db;
//Example code to insert image
$png = file_get_contents('1.png');
$query = "DELETE FROM userpictures WHERE user_id = 100";
$db->execute($query);
$query = "INSERT INTO userpictures(user_id, picture) VALUES (?, ?)";
$db->execute($query, Array(100, $png));

$query = "SELECT * FROM userpictures WHERE user_id = 100"; 
$userpicture = $db->GetRow($query);
//$image = trim($userpicture['picture']);

// after connecting to and reading the row from the table 
//header("Content-type: image/png"); // or whatever 
//header("Content-Transfer-Encoding: base64");
//header("Pragma: no-cache");
//header("Expires: 0");

if(empty($image)){
	print "No user image found";
} else {
	//print "Image found";
	header("Content-type: image/png");
	print $image;
}
?>
