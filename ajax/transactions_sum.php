<?php
$rootpath = '../';
$page_access = 'admin';
require_once __DIR__ . '/../include/web.php';

$tschema = $app['this_group']->get_schema();

$days = $_GET['days'] ?? false;
$ex_letscodes = $_GET['ex'] ?? [];
$in = isset($_GET['in']) && $_GET['in'] ? true : false;

if (!$days)
{
	http_response_code(404);
	exit;
}

if (!is_array($ex_letscodes))
{
	http_response_code(500);
	exit;
}

array_walk($ex_letscodes, function(&$value){ $value = trim($value); });

$res = $in ? 'to' : 'from';
$inp = $in ? 'from' : 'to';

$end_unix = time();
$begin_unix = $end_unix - ($days * 86400);
$begin = gmdate('Y-m-d H:i:s', $begin_unix);

$balance = [];

$sql_where = [];
$sql_params = [$begin];
$sql_types = [\PDO::PARAM_STR];

if (count($ex_letscodes))
{
	$sql_where[] = 'u.letscode not in (?)';
	$sql_params[] = $ex_letscodes;
	$sql_types[] = \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
}

$query = 'select sum(t.amount), t.id_' . $res . ' as uid
	from ' . $tschema . '.transactions t, ' . $tschema . '.users u
	where u.id = t.id_' . $inp . '
		and t.cdate > ?';

if (count($sql_where))
{
	$query .= ' and ' . implode(' and ', $sql_where);
}

$query .= ' group by t.id_' . $res;

$stmt = $app['db']->executeQuery($query, $sql_params, $sql_types);

$ary = [];

while ($row = $stmt->fetch())
{
	$ary[$row['uid']] = $row['sum'];
}

echo json_encode($ary);
exit;
