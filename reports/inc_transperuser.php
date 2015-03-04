<?php

function get_all_transactions($user_userid,$user_datefrom,$user_dateto,$user_prefix){
        global $db;

        if($user_userid == 'ALL') {
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
        	$query .= " AND (transactions.date >= '" .$user_datefrom;
	        $query .= "' AND transactions.date <= '" .$user_dateto;
        	$query .= "')";
		if($user_prefix != 'ALL') {
			$query .= " AND (fromusers.letscode like '" .$user_prefix ."%'";
			$query .= " OR tousers.letscode  like '" .$user_prefix ."%'";
			$query .= ")";
		}
	        $query .= " ORDER BY transactions.date";
        } else {
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
                $query .= " AND (transactions.id_from = " .$user_userid;
                $query .= " OR transactions.id_to = " .$user_userid;
                $query .= ")";
        	$query .= " AND (transactions.date >= '" .$user_datefrom;
        	$query .= "' AND transactions.date <= '" .$user_dateto;
        	$query .= "')";
        	$query .= " ORDER BY transactions.date";
        }

	//echo $query;
        $transactions = $db->GetArray($query);
        return $transactions;
}

?>
