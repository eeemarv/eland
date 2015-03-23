<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

include($rootpath."includes/inc_header.php");

$account_user_admin = (in_array($s_accountrole, array('admin', 'user'))) ? true : false;

$msg_orderby =  (isset($_GET["msg_orderby"])) ? $_GET["msg_orderby"] : "messages.id";

$user_filterby = $_GET["user_filterby"];

$date = date('Y-m-d');
global $db;
$query = "SELECT *, ";
$query .= " messages.id AS msgid, ";
$query .= " users.id AS userid, ";
$query .= " categories.id AS catid, ";
$query .= " categories.fullname AS catname, ";
$query .= " users.name AS username, ";
$query .= " users.letscode AS letscode, ";
$query .= " messages.validity AS valdate, ";
$query .= " messages.cdate AS date ";
$query .= " FROM messages, users, categories ";
$query .= "  WHERE messages.id_user = users.id ";

$query .= " AND messages.id_category = categories.id";

if (isset($user_filterby)){
	switch ($user_filterby) {
		case "expired":
			$query .= " AND messages.validity < " ."'" .$date ."'";
			break;
		case "valid":
			$query .= " AND messages.validity >= " ."'" .$date ."'";
			break;
	}
}

if (isset($msg_orderby)){
		$query .= " ORDER BY ".$msg_orderby. " ";
}else{
		$query .= " ORDER BY messages.msg_type,letscode ";
}
$messagerows = $db->GetArray($query);


if ($account_user_admin)
{
	echo "<table width='100%' border=0><tr><td>";
	echo "<div id='navcontainer'>";
	echo "<ul class='hormenu'>";
	echo "<li><a href='edit.php?mode=new'>Vraag & Aanbod toevoegen</a></li>";
	if ($s_accountrole == "admin")
	{	
		$myurl = $rootpath. 'export_messages.php"';
		echo "<li><a href='#' onclick=window.open('$myurl','msgexport','width=1200,height=480,scrollbars=yes,toolbar=no,location=no,menubar=no')>Export</a></li>";
	}	
	echo "</ul>";
	echo "</div>";
	echo "</td></tr></table>";
}

echo "<h1>Overzicht Vraag & Aanbod</h1>";

echo "<br>Filter: ";
echo "<a href='overview.php?user_filterby=all'>Alle</a>";
echo " - ";
echo "<a href='overview.php?user_filterby=expired'>Vervallen</a>";
echo " - ";
echo "<a href='overview.php?user_filterby=valid'>Geldig</a>";

echo "<div class='border_b'>";
echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
echo "<tr class='header'>";
echo "<td nowrap><strong>";
echo "<a href='overview.php?msg_orderby=msg_type'>V/A</a>";
echo "</strong></td>";
echo "<td ><strong>";
echo "<a href='overview.php?msg_orderby=content'>Wat</a>";
echo "</strong></td>";
echo "<td ><strong>Geldig tot";
echo "</strong></td>";
echo "<td ><strong>";
echo "<a href='overview.php?msg_orderby=letscode'>Wie</a>";
echo "</strong></td>";
echo "<td ><strong>";
echo "<a href='overview.php?msg_orderby=catname'>Categorie</a>";
echo "</strong></td>";
if ($account_user_admin)
{
	echo "<td><strong>Verlengen</strong></td>";
}
echo "</tr>";
$rownumb=0;
foreach($messagerows as $key => $value){
	$rownumb=$rownumb+1;
	if($rownumb % 2 == 1){
		echo "<tr class='uneven_row'>";
	}else{
			echo "<tr class='even_row'>";
	}

	echo "<td valign='top' nowrap>";
	if($value["msg_type"]==0){
		echo "V";
	}elseif ($value["msg_type"]==1){
		echo "A";
	}
	echo "</td>";
	echo "<td valign='top'>";
	if(strtotime($value["valdate"]) < time()) {
					echo "<del>";
			}
	echo "<a href='view.php?id=".$value["msgid"]."'>";
	$content = htmlspecialchars($value["content"],ENT_QUOTES);
	echo chop_string($content, 50);
	if(strlen($content)>50){
		echo "...";
	}
	echo "</a>";
			 if(strtotime($value["valdate"]) < time()) {
					echo "</del>";
			}

	echo "</a> ";
	echo "</td>";

	echo "<td>";
			if(strtotime($value["valdate"]) < time()) {
					echo "<font color='red'><b>";
			}
			echo $value["valdate"];
			if(strtotime($value["valdate"]) < time()) {
					echo "</b></font>";
			}
			echo "</td>";

	echo "<td valign='top' nowrap>";
	echo '<a href="' . $rootpath . 'memberlist_view.php?id=' . $value['userid'].'">';
	echo  htmlspecialchars($value["username"],ENT_QUOTES)." (".trim($value["letscode"]).")";
	echo '</a>';
	echo "</td>";
	echo "<td valign='top'>";
	echo htmlspecialchars($value["fullname"],ENT_QUOTES);
	echo "</td>";
	if ($account_user_admin)
	{
		echo "<td valign='top'>";
		if ($s_accountrole = 'admin' || ($s_accountrole == 'user' && $s_id == $value['userid']))
		{
			echo '<a href="message_extend.php?id='.$value["msgid"].'&validity=12">1 jaar</a> | <a href="message_extend.php?id="'.$value['msgid'].'&validity=60">5 jaar</a>';
		}
		echo "</td>";
	}
	echo "</tr>";
}
echo "</table></div>";

include($rootpath."includes/inc_footer.php");

function chop_string($content, $maxsize){
	$strlength = strlen($content);
	if ($strlength >= $maxsize){
		$spacechar = strpos($content," ", 50);
		if($spacechar == 0){
			return $content;
		}else{
			return substr($content,0,$spacechar);
		}
	}else{
		return $content;
	}
}
