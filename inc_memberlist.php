<?php
			
function get_all_active_users($user_orderby,$prefix_filterby,$searchname,$sortfield){
	global $db;
	$query = "SELECT * FROM users ";
	$query .= "WHERE (status = 1  ";
	$query .= "OR status =2 OR status = 3)  ";
	$query .= "AND users.accountrole <> 'guest' ";
	if ($prefix_filterby <> 'ALL'){
		 $query .= "AND users.letscode like '" .$prefix_filterby ."%'";
	}
	if(!empty($searchname)){
		$query .= " AND (LOWER(fullname) like '%" .strtolower($searchname) ."%' OR LOWER(name) like '%" .strtolower($searchname) ."%')";
	}
	if(!empty($sortfield)){
		$query .= " ORDER BY " .$sortfield;
	}

	//echo $query;
	$userrows = $db->GetArray($query);
	return $userrows;
}

?>
