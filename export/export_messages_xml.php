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
	
#include($rootpath."includes/inc_header.php");
#include($rootpath."includes/inc_nav.php");

$user_userid = $_GET["userid"];
$user_datefrom = $_GET["datefrom"];
$user_dateto = $_GET["dateto"];

if(isset($s_id) && ($s_accountrole == "admin")){
	show_ptitle();
	$messages=get_messages();
	show_all_messages_xml($messages);
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle(){
        header("Content-disposition: attachment; filename=marva_export-messages-".date("Y-m-d").".xml");
        header("Content-Type: application/force-download");
        header("Content-Transfer-Encoding: binary");
        header("Pragma: no-cache");
        header("Expires: 0");
}

function get_user($id){
        global $db;
        $query = "SELECT *";
        $query .= " FROM users ";
        $query .= " WHERE id='".$id."'";
        $user = $db->GetRow($query);
        return $user;
}


function get_category($id){
        global $db;
        $query = "SELECT *";
        $query .= " FROM categories ";
        $query .= " WHERE id='".$id."'";
        $category = $db->GetRow($query);
        return $category;
}


function get_messages(){
	global $db;

        $query = "SELECT msg.*, cat.id_parent FROM messages msg, categories cat WHERE msg.id_category = cat.id ORDER BY msg.id_category, msg.msg_type, msg.content";
        //$query = "SELECT * FROM messages ORDER BY id_category, msg_type, content";
	
	$list_messages = $db->GetArray($query);
	return $list_messages;
}

function getXmlNode ($parentNode, $elementName, $AttributeName, $AttributeValue)
{

	$node_array = $parentNode->getElementsByTagName($elementName);

	foreach ($node_array as $node) {
	    if ($AttributeName != "") { // check attribute present and has correct value
		if ($node->hasAttribute($AttributeName) &&
		    $node->getAttribute($AttributeName) == $AttributeValue)
			return $node;
	    }
	    else // just return first element
		return $node;
	}
	return "";
}

function show_all_messages_xml($messages)
{
 //Creates XML string and XML document using the DOM 
 $dom = new DomDocument('1.0'); 

 //add root - <marva> 
 $marva = $dom->appendChild($dom->createElement('marva')); 

 foreach($messages as $key => $value)
 {
    $user = get_user($value["id_user"]);
    $category = get_category($value["id_category"]);
    $categoryGroupNode = getXmlNode($marva, "categoryGroup", "id", $value['id_parent']);
    if ($categoryGroupNode == "") { // Create category
	//add <categoryGroup> element to <marva> 
 	$categoryGroupNode = $marva->appendChild($dom->createElement('categoryGroup')); 

 	//add <id> attribute to <category> 
 	$catGroupIdAttr = $categoryGroupNode->appendChild($dom->createAttribute('id')); 
 	$catGroupIdAttr->appendChild( $dom->createTextNode($value["id_parent"]));

        $categoryGroup = get_category($value["id_parent"]);
 	//add <name> attribute to <category> 
 	$catGroupNameAttr = $categoryGroupNode->appendChild($dom->createAttribute('name')); 
 	$catGroupNameAttr->appendChild( $dom->createTextNode($categoryGroup['name']));
    }
    $categoryNode = getXmlNode($marva, "category", "id", $value['id_category']);
    $vraag = "";
    $aanbod = "";
    if ($categoryNode == "") { // Create category
 	//echo "Create category for id " . $value["id_category"];
	//add <category> element to <categoryGroup> 
 	$categoryNode = $categoryGroupNode->appendChild($dom->createElement('category')); 

 	//add <id> attribute to <category> 
 	$catIdAttr = $categoryNode->appendChild($dom->createAttribute('id')); 
 	$catIdAttr->appendChild( $dom->createTextNode($value["id_category"]));

 	//add <name> attribute to <category> 
 	$catNameAttr = $categoryNode->appendChild($dom->createAttribute('name')); 
 	$catNameAttr->appendChild( $dom->createTextNode($category['name']));

 	//add <vraag> element to <category> 
	$vraag = $categoryNode->appendChild($dom->createElement('vraag')); 
	//add <aanbod> element to <category> 
	$aanbod = $categoryNode->appendChild($dom->createElement('aanbod')); 
    }
    else {
	$vraag = $categoryNode->getElementsByTagName('vraag')->item(0);
	$aanbod = $categoryNode->getElementsByTagName('aanbod')->item(0);
    }


    $message = "";
    if ($value["msg_type"] == "0") {
       //add <message> element to <vraag> 
       $message = $vraag->appendChild($dom->createElement('message')); 
    }
    else {
       //add <message> element to <aanbod> 
       $message = $aanbod->appendChild($dom->createElement('message')); 
    }
    //add <content> element to <message> 
    $content = $message->appendChild($dom->createElement('content')); 
    //add <content> text node element to <content> 
    $content->appendChild( 
                 $dom->createTextNode($value["content"])); 

    //add <letscode> element to <message> 
    $letscode = $message->appendChild($dom->createElement('letscode')); 
    //add <letscode> text node element to <letscode> 
    $letscode->appendChild( 
                 $dom->createTextNode($user["letscode"])); 
 
    //add <validity> text node element to <message> 
    $validity = $message->appendChild($dom->createElement('validity')); 
    //add <validity> text node element to <validity> 
    $validity->appendChild( 
                 $dom->createTextNode($value["validity"])); 
  
  }

 //generate xml 
 $dom->formatOutput = true; // set the formatOutput attribute of 
                            // domDocument to true 
 // save XML as string or file 
 $test1 = $dom->saveXML(); // put string in test1 
 echo $test1; 
//$dom->save('test1.xml'); // save as file 
}


#include($rootpath."includes/inc_sidebar.php");
#include($rootpath."includes/inc_footer.php");
?>
