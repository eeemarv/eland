<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$user_userid = $_GET["userid"];
$user_datefrom = $_GET["datefrom"];
$user_dateto = $_GET["dateto"];

if(isset($s_id) && ($s_accountrole == "admin")){
	show_ptitle($user_userid,$user_datefrom,$user_dateto);
	$transactions = get_all_transactions();
	show_all_transactions($transactions);
}else{
	redirect_login($rootpath);
}

/////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_ptitle(){
        header("Content-disposition: attachment; filename=elas-transactions-".date("Y-m-d").".csv");
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

function get_users(){
	global $db;
        $query = "SELECT * FROM users ";
        $query .= "WHERE (status = 1  ";
        $query .= "OR status =2 OR status = 3)  ";
        $query .= "AND users.accountrole <> 'guest' ";
        $query .= " order by letscode";
        $list_users = $db->GetArray($query);
        return $list_users;
}

function show_all_transactions($transactions){

        //echo '"id","Creatiedatum","Transactiedatum","Van","Aan","Bedrag","Dienst","Ingebracht door"';
	echo '"Datum","Van","Aan","Bedrag","Dienst"';
        echo "\r\n";
	foreach($transactions as $key => $value){
		echo "\"";
                echo $value["datum"];
		echo "\",";
		echo "\"";
                //echo htmlspecialchars($value["fromusername"],ENT_QUOTES). " (" .trim($value["fromletscode"]).")";
		echo htmlspecialchars($value["fromletscode"],ENT_QUOTES);
		echo "\",";
		echo "\"";
                //echo htmlspecialchars($value["tousername"],ENT_QUOTES). " (" .trim($value["toletscode"]).")";
		echo htmlspecialchars($value["toletscode"],ENT_QUOTES);
		echo "\",";
		echo "\"";
                echo $value["amount"];
		echo "\",";
		echo "\"";
                echo htmlspecialchars($value["description"],ENT_QUOTES);
		echo "\"";

                echo "\r\n";
        }
}

function get_all_transactions(){
        global $db;

        $query = "SELECT *, ";
        $query .= " transactions.id AS transid, ";
        $query .= " fromusers.id AS userid, ";
        $query .= " fromusers.name AS fromusername, tousers.name AS tousername, ";
        $query .= " fromusers.letscode AS fromletscode, tousers.letscode AS toletscode, ";
        $query .= " transactions.date AS datum, ";
        $query .= " transactions.cdate AS cdatum ";
        $query .= " FROM transactions, users  AS fromusers, users AS tousers";
	$query .= " WHERE transactions.id_to = tousers.id";
        $query .= " AND transactions.id_from = fromusers.id";
        $query .= " ORDER BY transactions.date DESC";
	$query .= " LIMIT 5000";

        $transactions = $db->GetArray($query);
        return $transactions;
}
