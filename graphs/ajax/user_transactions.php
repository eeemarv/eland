<?php
ob_start();

$rootpath = '../../';
require_once($rootpath.'includes/inc_default.php');
require_once($rootpath.'includes/inc_adoconnection.php');
require_once($rootpath.'includes/inc_request.php');

$req = new request('user');
$req->add('days', 365, 'get');
$req->add('user_id', 0, 'get');
$user_id = $req->get('user_id');

if (!$user_id){
	exit;
}

$user = $db->GetRow('SELECT saldo FROM users WHERE id = '.$user_id);

if (!$user){
	exit;
}

$balance = (int) $user['saldo'];

$begin_date = date('Y-m-d H:i:s', time() - (86400 * $req->get('days')));
$end_date = date('Y-m-d H:i:s');

$query = 'SELECT transactions.amount, transactions.id_from, transactions.id_to, ';
$query .= 'transactions.real_from, transactions.real_to, transactions.date, transactions.description, ';
$query .= 'users.id, users.name, users.letscode, users.accountrole, users.status ';
$query .= 'FROM transactions, users ';
$query .= 'WHERE (transactions.id_to = '.$user_id.' OR transactions.id_from = '.$user_id.') ';
$query .= 'AND (users.id = transactions.id_to OR users.id = transactions.id_from) ';
$query .= 'AND users.id <> '.$user_id.' ';
$query .= 'AND transactions.date >= \''.$begin_date.'\' ';
$query .= 'AND transactions.date <= \''.$end_date.'\' ';
$query .= 'ORDER BY transactions.date DESC';
$trans = $db->GetArray($query);

$begin_date = strtotime($begin_date);
$end_date = strtotime($end_date);

$transactions = $users = $_users = array();

foreach ($trans as $t){
	$date = strtotime($t['date']);
	$out = ($t['id_from'] == $user_id) ? true : false;
	$mul = ($out) ? 1 : -1;
	$balance += $t['amount'] * $mul;

	$name = $t['name'];
	$real = ($t['real_from']) ? $t['real_from'] : null;
	$real = ($t['real_to']) ? $t['real_to'] : null;
	if ($real){
		list($name, $code) = explode('(', $real);
		$name = trim($name);
		$code = $t['letscode'] . ' ' . trim($code, ' ()\t\n\r\0\x0B');
	} else {
		$code = $t['letscode'];
	}

	$transactions[] = array(
		'amount' => (int) $t['amount'],
		'date' => $date,
		'userCode' => strip_tags($code),
		'desc' => strip_tags($t['description']),
		'out' => $out,
		);

	$_users[(string) $code] = array(
		'name' => strip_tags($name),
		'linkable' => ($real || $t['status'] == 0) ? 0 : 1,
		'id' => $t['id'],
		);

}

foreach ($_users as $code => $ary){
	$users[] = array_merge($ary, array(
		'code' => (string) $code,
		));
}
unset($_users);

$transactions = array_reverse($transactions);

echo json_encode(array(
	'user_id' => $user_id,
	'ticks' => ($req->get('days') == 365) ? 12 : 4,
	'currency' => readconfigfromdb('currency'),
	'transactions' => $transactions,
	'users' => $users,
	'beginBalance' => $balance,
	'begin' => $begin_date,
	'end' => $end_date,
	));
