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
	//show_form();
        if (isset($_POST["zend"])){
		//echo $_FILES['csvfile']['name'];
		//echo $_FILES['csvfile']['tmp_name'];
		//print_r($_FILES);
		$file = $_FILES['csvfile']['tmp_name'];
		echo "Bestand doorgestuurd als $file<br>";
		$table = $_POST["table"];
		csv2db($file,$table);

	} else {
		show_form();
	}
	//$posted_list = array();
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
	echo "<h1>CSV bestand importeren</h1>";
	echo "<p><b><font color='red'>WAARSCHUWING: Maak een kopie van de databank VOOR je dit probeert!!!</font></b></p>";
}

function show_form(){
	echo "<form action='import_csv.php' enctype='multipart/form-data' method='POST'>\n";
        echo "<input name='csvfile' type='file' />\n";
	echo "<select name='table'>";
        echo "<option value='users' SELECTED >Users</option>";
        echo "<option value='contact'>Contact</option>";
	//echo "<option value='categories'>Categories</option>";
	echo "<option value='messages'>Messages (V/A)</option>";
	echo "<option value='transactions'>Transactions</option>";
        echo "</select>";
	echo "<input type='submit' name='zend' value='Versturen' />\n";

	echo "</form>\n";
}

function csv2db($file,$table){
	echo "Processing file into table $table<br>";
	$handle = fopen($file, "r");
	while (($data = fgetcsv($handle, 1000, ","))!== FALSE) {
		$num = count($data);
	 	echo "<p> $num fields in line $row: <br /></p>\n";
		$row++;
		//Voor elke kolom
		if($row > 2) {
			processrecord($data,$num,$table);
		}
	}
	fclose($handle);

}

function processrecord($data,$num,$table){
        for ($c = 0; $c < $num; $c = $c + 1) {
		//categories:
		//messages: '"letscode","validity","id_category","content","msg_type"';
		//transactions:

		echo "Processing record, column $c of $num<br>";

		switch ($table) {
			case "users":
				switch ($c){
					case 0:
						$users["letscode"] = $data["$c"];
						echo "letscode is " .$data["$c"] ."<br>";
						break;
					case 1:
						$users["cdate"] = $data["$c"];
						break;
					case 2:
						$users["comments"] = $data["$c"];
						break;
					case 3:
						$users["hobbies"] = $data["$c"];
						break;
					case 4:
						$users["name"] = $data["$c"];
						break;
					case 5:
						$users["postcode"] = $data["$c"];
                                                break;
                                        case 6:
						$users["login"] = $data["$c"];
                                                break;
					case 7:
                                                $users["mailinglist"] = $data["$c"];
                                                break;
                                        case 8:
                                                $users["password"] = $data["$c"];
                                                break;
                                        case 9:
                                                $users["accountrole"] = $data["$c"];
                                                break;
                                        case 10:
                                                $users["status"] = $data["$c"];
                                                break;
                                        case 11:
                                                $users["lastlogin"] = $data["$c"];
                                                break;
                                        case 12:
                                                $users["minlimit"] = $data["$c"];
                                                break;
                                        case 13:
                                                $users["fullname"] = $data["$c"];
                                                break;
                                        case 14:
						$users["admincomment"] = $data["$c"];
                                                break;
                                        case 15:
						$users["adate"] = $data["$c"];
                                                break;
                                }
				break;
			case "contact":
				switch ($c){
					//contact: '"letscode","id_type_contact","comments","value","flag_public"'
                                        case 0:
                                                $contact["letscode"] = $data["$c"];
                                                echo "letscode is " .$data["$c"] ."<br>";
                                                break;
                                        case 1:
                                                $contact["id_type_contact"] = $data["$c"];
						break;
					case 2:
                                                $contact["comments"] = $data["$c"];
                                                break;
                                        case 3:
                                                $contact["value"] = $data["$c"];
                                                break;
                                        case 4:
                                                $contact["flag_public"] = $data["$c"];
                                                break;
				}
				break;
			case "categories":
				echo "<font color='red'>Categorie import wordt niet ondersteund</font><br>";
				break;
			case "messages":
				switch ($c){
					//'"letscode","validity","id_category","content","msg_type"';
					case 0:
                                                $messages["letscode"] = $data["$c"];
                                                echo "letscode is " .$data["$c"] ."<br>";
                                                break;
					case  1:
                                                $messages["validity"] = $data["$c"];
						break;
					case 2:
						$messages["id_category"] = $data["$c"];
                                                break;
					case 3:
						$messages["content"] = $data["$c"];
                                                break;
                                        case 4:
                                                $messages["msg_type"] = $data["$c"];
						break;
				}
				break;
			case "transactions":
				switch ($c){
					//'"Datum","Van","Aan","Bedrag","Dienst"';
					case 0:
                                                $transactions["datum"] = $data["$c"];
						echo "Transactie van " .$data["$c"] ."<br>";
						break;
					case  1:
						$transactions["van"] = $data["$c"];
                                                break;
                                        case  2:
                                                $transactions["aan"] = $data["$c"];
                                                break;
                                        case  3:
                                                $transactions["bedrag"] = $data["$c"];
                                                break;
                                        case  4:
                                                $transactions["dienst"] = $data["$c"];
                                                break;
				}
				break;
		}
	}

	// where to put it
	switch ($table) {
        	case "users":
			dbinsert_users($users);
			break;
		case "contact":
			dbinsert_contact($contact);
			break;
		case "messages":
                        dbinsert_messages($messages);
			break;
		case "transactions":
                        dbinsert_transactions($transactions);
			break;
	}

}

function dbinsert_transactions($transactions){
	global $db;
	$transactions["id_from"] = getuserid($transactions["van"]);
	$transactions["id_to"] = getuserid($transactions["aan"]);
	$transactions["description"] = $transactions["dienst"];
	$transactions["amount"] = $transactions["bedrag"];
	$transactions["date"] = $transactions["datum"];
	$transactions["cdate"] = date("Y-m-d H:i:s");

	if(!empty($transactions["id_from"]) && !empty($transactions["id_to"])){
		// Check for duplicate
		$mytransid = checkduplicatetransaction($transactions["date"],$transactions["id_from"],$transactions["id_to"],$transactions["description"]);

		if(empty($mytransid)){
			echo "Inserting transaction " .$transactions["datum"] ." into transactions table<br>";
			$result = $db->AutoExecute("transactions", $transactions, 'INSERT');
			print_r($transactions);

			print "Query returned $result<br>";
		} else {
			echo "<font color='red'>Transactie bestaat al in database</font><br>";
		}
	} else {
		echo "<font color='red'>UserID's niet gevonden, importeer gebruikers eerst!</font><br>";
	}
}

function dbinsert_messages($messages){
        global $db;
        $userid = getuserid($messages["letscode"]);

	if(!empty($userid)){
                $messages["id_user"] = $userid;

		// Check for duplicates
		$messageid = checkduplicatemessage($userid,$messages["content"]);
		if(empty($messageid)){
			echo "Inserting message " .$messages["letscode"] ." into messages table<br>";
			print_r($messages);
			$result = $db->AutoExecute("messages", $messages, 'INSERT');
			print "Query returned $result<br>";
		} else {
                        echo "<font color='red'>Dit V/A bestaat al in de database</font><br>";
                }
	} else {
		echo "<font color='red'>UserID voor letscode " .$messages["letscode"] ." niet gevonden, importeer gebruikers eerst</font><br>";
	}
}

function dbinsert_contact($contact){
	global $db;
	$userid = getuserid($contact["letscode"]);

	if(!empty($userid)){
		$contact["id_user"] = $userid;

		// Check for duplicates
		$contactid = checkduplicatecontact($userid,$contact["value"]);
		if(empty($contactid)){
			echo "Inserting contact " .$contact["letscode"] ." into contacts table<br>";
			print_r($contact);
			$result = $db->AutoExecute("contact", $contact, 'INSERT');
			print "Query returned $result<br>";
		} else {
			echo "<font color='red'>Dit contact bestaat al in de database</font><br>";
		}
	} else {
		echo "<font color='red'>UserID voor letscode " .$contact["letscode"] ." niet gevonden, importeer gebruikers eerst</font><br>";
	}
}

function dbinsert_users($users){
        global $db;
	// Add some defaults

	// Do some checks!
	if(!empty($users["letscode"])){
		// check for duplicated LETScodes
		if(checkuser($users["letscode"]) == $users["letscode"]){
			echo "<font color='red'>FOUT: LETSCode " .$users["letscode"] ." bestaat al!</font><br>";
			return("error");
		}
		// check for duplicated logins
		if(checklogin($users["login"]) == $users["login"]){
			echo "<font color='red'>FOUT: login " .$users["login"] ." bestaat al!</font><br>";
                        return("error");
                }

		echo "Inserting user " .$users["letscode"] ." into users table<br>";
		print_r($users);
	        $result = $db->AutoExecute("users", $users, 'INSERT');
		print "Query returned $result<br>";
	}
}

function getuserid($letscode){
        global $db;
	$query = "SELECT id FROM users WHERE letscode = '" .$letscode."'";
	$user = $db->GetRow($query);
	return $user["id"];
}

function checkduplicatecontact($userid,$value) {
	global $db;
        echo "Checking for duplicate contact<br>";
        $query = "SELECT * ";
        $query .= " FROM contact WHERE id_user = " .$userid ." AND value = '" .$value ."'";
	$mycontact = $db->GetRow($query);
	return $mycontact["id"];
}

function checkduplicatemessage($userid,$content) {
        global $db;
        echo "Checking for duplicate messages<br>";
        $query = "SELECT * ";
        $query .= " FROM messages WHERE id_user = " .$userid ." AND content = '" .$content ."'";
	echo $query;
	$mymessage= $db->GetRow($query);
        return $mymessage["id"];
}

function checkduplicatetransaction($date,$id_from,$id_to,$description){
	global $db;
        echo "Checking for duplicate transactions<br>";
        $query = "SELECT * ";
        $query .= " FROM transactions WHERE date = '" .$date ."' AND id_from = " .$id_from ." AND id_to = " .$id_to;
	$query .= " AND description = '" .$description ."'";
	//echo $query;
	$mytransaction = $db->GetRow($query);
	//echo "FOUND transaction as " .$mytransaction["id"] ."<br>";
	return $mytransaction["id"];
}

function checkuser($letscode){
	global $db;
	echo "Checking for duplicate LETS code<br>";
	$query = "SELECT * ";
        $query .= " FROM users WHERE letscode = '" .$letscode."'";
	$user = $db->GetRow($query);
        return $user["letscode"];
}

function checklogin($login){
	global $db;
	echo "Checking for duplicate login name<br>";
        $query = "SELECT * ";
        $query .= " FROM users WHERE login = '" .$login ."'";
        $user = $db->GetRow($query);
        return $user["login"];
}

include($rootpath."includes/inc_sidebar.php");
include($rootpath."includes/inc_footer.php");
?>
