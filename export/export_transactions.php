<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$user_userid = $_GET["userid"];
$user_datefrom = $_GET["datefrom"];
$user_dateto = $_GET["dateto"];

$transactions = $db->fetchAll('select 
		t.transid, 
		t.description,
		fu.name AS from_name, tu.name AS to_name,
		fu.letscode AS from_letscode, tu.letscode AS to_letscode,
		t.cdate AS cdatum,
		t.real_from,
		t.real_to,
		t.amount
	from transactions t, users fu, users tu
	where t.id_to = tu.id
	and t.id_from = fu.id
	order by t.date desc');

header("Content-disposition: attachment; filename=elas-transactions-".date("Y-m-d").".csv");
header("Content-Type: application/force-download");
header("Content-Transfer-Encoding: binary");
header("Pragma: no-cache");
header("Expires: 0");

echo '"Datum","Van", "interlets", "Aan", "interlets", "Bedrag","Dienst", "transactie id"';
echo "\r\n";

foreach($transactions as $value)
{
	echo '"';
	echo $value['cdatum'];
	echo '","';
	echo $value['from_letscode'] . ' ' . $value['from_name'];
	echo '","';
	echo $value['real_from'];
	echo '","';
	echo $value['to_letscode'] . ' ' . $value['to_name'];
	echo '","';
	echo $value['real_to'];
	echo '","';
	echo $value['amount'];
	echo '","';
	echo $value['description'];
	echo '","';
	echo $value['transid'];
	echo '"';
	echo "\r\n";
}
