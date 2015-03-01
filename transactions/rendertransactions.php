<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_transactions.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];
	
$trans_orderby = $_GET["trans_orderby"];
$asc = $_GET["asc"];

$trans_orderby = (isset($trans_orderby) && ($trans_orderby != '')) ? $trans_orderby : 'date';
$asc = (isset($asc) && ($asc != '')) ? $asc : 0;

if($s_accountrole == "user" || $s_accountrole == "admin" || $s_accountrole == "interlets"){
	$transactions = get_all_transactions($trans_orderby, $asc);
	show_all_transactions($transactions, $trans_orderby, $asc);
} else {
	redirect_login($rootpath);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

function redirect_login($rootpath){
	header("Location: ".$rootpath."login.php");
}

function show_addlink($rootpath){
	echo "<div class='border_b'>| <a href='add.php' accesskey='N'>Transactie toevoegen</a> | ";
	echo "<a href='bijdrage_add.php' >Maandelijkse bijdrage</a> | ";
	echo "<a href='".$rootpath."export_transactions.php'>Export</a> | </div>";
}

function show_ptitle(){
	echo "<h1>Overzicht transacties</h1>";
}

function show_all_transactions($transactions, $trans_orderby, $asc){
	$asc_preset_ary = array(
		'asc'	=> 0,
		'indicator' => '');

	$tableheader_ary = array(
		'date'	=> array_merge($asc_preset_ary, array(
			'lang' => 'Transactiedatum')),
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
		echo $value["datum"];
		echo "</td>";
		echo "<td nowrap valign='top'>";
		if(!empty($value["real_from"])){
			echo htmlspecialchars($value["real_from"],ENT_QUOTES);
		} else {
			echo htmlspecialchars($value["fromusername"],ENT_QUOTES). " (" .trim($value["fromletscode"]).")";
		}
		echo "</td><td valign='top' nowrap>";
		if(!empty($value["real_to"])){
                        echo htmlspecialchars($value["real_to"],ENT_QUOTES);
                } else {
			echo htmlspecialchars($value["tousername"],ENT_QUOTES). " (" .trim($value["toletscode"]).")";
		}
		echo "</td><td valign='top' nowrap>";
		echo $value["amount"];
		echo "</td><td valign='top'><a href='view.php?id=".$value["transid"] ."'>";
		echo htmlspecialchars($value["description"],ENT_QUOTES);
		echo "</a> ";
		echo "</td></tr>";
	}
	echo "</table></div>";
}

?>
