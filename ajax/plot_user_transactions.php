<?php

$rootpath = '../';
$page_access = 'guest';
require_once __DIR__ . '/../include/web.php';

$tschema = $app['this_group']->get_schema();

$days = $_GET['days'] ?? 365;
$user_id = $_GET['user_id'] ?? false;

if (!$user_id)
{
	exit;
}

$user = $app['user_cache']->get($user_id, $tschema);

if (!$user)
{
	exit;
}

$groups = $_groups = $transactions = $users = $_users  = [];

$groups = $app['db']->fetchAll('select id, groupname as n, localletscode as c, url
	from ' . $tschema . '.letsgroups');

foreach ($groups as $g)
{
	$g['domain'] = strtolower(parse_url($g['url'], PHP_URL_HOST));
	$_groups[$g['c']] = $g;
}

$balance = (int) $user['saldo'];

$begin_date = date('Y-m-d H:i:s', time() - (86400 * $days));
$end_date = date('Y-m-d H:i:s');

$query = 'select t.id, t.amount, t.id_from, t.id_to,
		t.real_from, t.real_to, t.date, t.description,
		u.id as user_id, u.name, u.letscode, u.accountrole, u.status
	from ' . $tschema . '.transactions t, ' . $tschema . '.users u
	where (t.id_to = ? or t.id_from = ?)
		and (u.id = t.id_to or u.id = t.id_from)
		and u.id <> ?
		and t.date >= ?
		and t.date <= ?
	order by t.date DESC';
$trans = $app['db']->fetchAll($query, [$user_id, $user_id, $user_id, $begin_date, $end_date]);

$begin_date = strtotime($begin_date);
$end_date = strtotime($end_date);

foreach ($trans as $t)
{
	$date = strtotime($t['date']);
	$out = $t['id_from'] == $user_id ? true : false;
	$mul = $out ? 1 : -1;
	$balance += $t['amount'] * $mul;

	$name = $t['name'];
	$real = $t['real_from'] ? $t['real_from'] : null;
	$real = $t['real_to'] ? $t['real_to'] : null;

	if ($real)
	{
		$group = $_groups[$t['letscode']];

		if ($sch = $app['groups']->get_schema($group['domain']))
		{
			[$code, $name] = explode(' ', $real);
		}
		else
		{
			[$name, $code] = explode('(', $real);
			$name = trim($name);
		}

		$code = $t['letscode'] . '.' . trim($code, ' ()\t\n\r\0\x0B');
	}
	else
	{
		$code = $t['letscode'];
	}

	$transactions[] = [
		'a' 		=> (int) $t['amount'],
		'date' 		=> $date,
		'c' 		=> strip_tags($code),
		'desc'		=> strip_tags($t['description']),
		'out'		=> $out,
		'id' 		=> $t['id'],
	];

	$_users[(string) $code] = [
		'n' 		=> strip_tags($name),
		'l' 		=> ($real || $t['status'] == 0) ? 0 : 1,
		's'			=> $t['status'],
		'id' 		=> $t['user_id'],
		'g'			=> (isset($group['id'])) ? $group['id'] : 0,
	];

	unset($group);
}

foreach ($_users as $code => $ary)
{
	$users[] = array_merge($ary, [
		'c' 		=> (string) $code,
	]);
}

unset($_users, $_groups);

$transactions = array_reverse($transactions);

header('Content-type: application/json');

echo json_encode([
	'user_id' 		=> $user_id,
	'ticks' 		=> $days == 365 ? 12 : 4,
	'currency' 		=> $app['config']->get('currency', $tschema),
	'transactions' 	=> $transactions,
	'users' 		=> $users,
	'beginBalance' 	=> $balance,
	'begin' 		=> $begin_date,
	'end' 			=> $end_date,
	'groups'		=> $groups,
]);
