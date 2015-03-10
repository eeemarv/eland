<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if (isset($s_id)){
	$oid = get_oids($s_id);
	show_oids($oid);
}else{
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////

function get_oids($s_id){
	global $db;
	$query = "SELECT * FROM openid ";
	$query .= "WHERE user_id=".$s_id;
	//echo $query;
	$oid = $db->GetArray($query);
	return $oid;
}

function show_oids($oid){
	echo "<table cellpadding='0' cellspacing='0' border='1' width='99%' class='data'>";
	//echo "<tr><td colspan='5'><p>&#160;</p></td></tr>";
	echo "<tr class='even_row'><td colspan='5'><p><strong>OpenID</strong></p></td></tr>";

	foreach($oid as $key => $value){
		//echo $value["openid"];
		echo "<tr valign='top'  nowrap>";
		echo "<td>".htmlspecialchars($value["openid"],ENT_QUOTES)." </td>";
		echo "<td><a href='mydetails_oid_delete.php?id=".$value["id"]."'> verwijderen </a>|</td>";
		echo "</tr>";
	}
	echo "</table>";
}
