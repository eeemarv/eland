<?php
ob_start();
$rootpath = "../";
$role = 'guest';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

include($rootpath."includes/inc_header.php");

$trans_orderby = $_GET["trans_orderby"];
$asc = $_GET["asc"];

$trans_orderby = (isset($trans_orderby) && ($trans_orderby != '')) ? $trans_orderby : 'cdate';
$asc = (isset($asc) && ($asc != '')) ? $asc : 0;

if (!($s_accountrole == "user" || $s_accountrole == "admin" || $s_accountrole == "interlets")){
	header("Location: ".$rootpath."login.php");
}

echo "<table width='100%' border=0><tr><td>";
echo "<div id='navcontainer'>";
echo "<ul class='hormenu'>";
echo '<li><a href="'. $rootpath . 'transactions/add.php">Nieuwe transactie</a></li>';
echo "</ul>";
echo "</div>";
echo "</td></tr></table>";

echo "<h1>Overzicht transacties</h1>";

$query_orderby = ($trans_orderby == 'fromusername' || $trans_orderby == 'tousername') ? $trans_orderby : 't.'.$trans_orderby;
$query = 'SELECT t.*, 
		t.id AS transid, 
		fu.id AS fromuserid,
		tu.id AS touserid,
		fu.name AS fromusername,
		tu.name AS tousername,
		fu.letscode AS fromletscode, tu.letscode AS toletscode, 
		t.date AS datum,
		t.cdate AS cdatum 
	FROM transactions t, users fu, users tu
	WHERE t.id_to = tu.id
	AND t.id_from = fu.id
	ORDER BY '.$query_orderby. ' ';
$query .= ($asc) ? 'ASC ' : 'DESC ';
$query .= 'LIMIT 1000';
$transactions = $db->GetArray($query);

$asc_preset_ary = array(
	'asc'	=> 0,
	'indicator' => '');

$tableheader_ary = array(
	'cdate'	=> array_merge($asc_preset_ary, array(
		'lang' => 'Tijdstip')),
	'fromusername' => array_merge($asc_preset_ary, array(
		'lang' => 'Van')),
	'tousername' => array_merge($asc_preset_ary, array(
		'lang' => 'Aan')),
	'amount' => array_merge($asc_preset_ary, array(
		'lang' => 'Bedrag')),
	'description' => array_merge($asc_preset_ary, array(
		'lang' => 'Dienst')));

$tableheader_ary[$trans_orderby]['asc'] = ($asc) ? 0 : 1;
$tableheader_ary[$trans_orderby]['indicator'] = ($asc) ? '&nbsp;&#9650;' : '&nbsp;&#9660;';

echo "<div class='border_b'>";
echo "<table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
echo "<tr class='header'>";

foreach ($tableheader_ary as $key_orderby => $data){
	echo '<td valign="top"><strong><a href="alltrans.php?trans_orderby='.$key_orderby.'&asc='.$data['asc'].'">';
	echo $data['lang'].$data['indicator'].'</a></strong></td>';
}

echo "</tr>";
$rownumb=0;
foreach($transactions as $key => $value){
	$rownumb=$rownumb+1;
	if($rownumb % 2 == 1){
		echo "<tr class='uneven_row'>";
	}else{
			echo "<tr class='even_row'>";
	}
	echo "<td nowrap valign='top'>";
	echo $value["cdatum"];
	echo "</td>";
	echo '</td><td valign="top"';
	echo ($value['fromuserid'] == $s_id) ? ' class="me"' : '';
	echo '>';
	if(!empty($value["real_from"])){
		echo htmlspecialchars($value["real_from"],ENT_QUOTES);
	} else {
		echo '<a href="' . $rootpath . 'memberlist_view.php?id=' . $value['fromuserid'] . '">';
		echo htmlspecialchars($value["fromusername"],ENT_QUOTES). " (" .trim($value["fromletscode"]).")";
		echo '</a>';
	}
	echo '</td><td valign="top"';
	echo ($value['touserid'] == $s_id) ? ' class="me"' : '';
	echo '>';
	if(!empty($value["real_to"])){
		echo htmlspecialchars($value["real_to"],ENT_QUOTES);
	} else { 
		echo '<a href="' . $rootpath . 'memberlist_view.php?id=' . $value['touserid'] . '">';
		echo htmlspecialchars($value["tousername"],ENT_QUOTES). " (" .trim($value["toletscode"]).")";
		echo '</a>';
	}
	echo "</td><td valign='top' nowrap>";
	echo $value["amount"];
	echo "</td><td valign='top'><a href='view.php?id=".$value["transid"] ."'>";
	echo htmlspecialchars($value["description"],ENT_QUOTES);
	echo "</a> ";
	echo "</td></tr>";
}
echo "</table></div>";

include($rootpath."includes/inc_footer.php");
