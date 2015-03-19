<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_transactions.php");

include($rootpath."includes/inc_header.php");
include($rootpath."includes/inc_nav.php");

if (!isset($_GET["id"]))
{
	header("Location: overview.php");
	exit;
}

$id = $_GET["id"];
$transaction = get_transaction($id);
echo "<h1>Transactie</h1>";

$currency = readconfigfromdb("currency");
echo "<div >";
echo "<table cellpadding='0' cellspacing='0' border='1' class='data' width='99%'>";
echo "<tr>";
echo "<td width='150'>Datum</td>";
echo "<td>".$transaction["datum"] ."</td>";
echo "</tr><tr>";
echo "<td width='150'>Creatiedatum</td>";
echo "<td>".$transaction["cdatum"] ."</td>";
echo "</tr><tr>";
echo "<td width='150'>TransactieID</td>";
echo "<td>".$transaction["transid"] ."</td>";
echo "</tr><tr>";
echo "<td width='150'>Account Van</td>";
echo "<td>". $transaction["fromusername"]. " (" .trim($transaction["fromletscode"]).")</td>";
echo "</tr><tr>";
echo "<td width='150'>Van</td>";
echo "<td>". $transaction["real_from"] ."</td>";
echo "</tr><tr>";
echo "<td width='150'>Account Aan</td>";
echo "<td>". $transaction["tousername"]. " (" .trim($transaction["toletscode"]).")</td>";
echo "</tr><tr>";
echo "<td width='150'>Aan</td>";
echo "<td>". $transaction["real_to"] ."</td>";
echo "</tr><tr>";

echo "<td width='150'>Waarde</td>";
echo "<td>". $transaction["amount"] ." $currency</td>";
echo "</tr><tr>";
echo "<td width='150'>Omschrijving</td>";
echo "<td>". $transaction["description"]."</td>";

echo "</tr>";
echo "</table></div>";

include($rootpath."includes/inc_footer.php");
