<?php
$rootpath = '../';
$page_access = 'admin';
require_once __DIR__ . '/../include/web.php';

$tschema = $app['this_group']->get_schema();

$except = $_GET['except'] ?? 0;

$account_codes = [];

$st = $app['db']->prepare('select letscode
	from ' . $tschema . '.users
	where id <> ?
	order by letscode asc', [$except]);
$st->execute();

while ($row = $st->fetch())
{
	if (empty($row['letscode']))
	{
		continue;
	}

	$account_codes[] = $row['letscode'];
}

$account_codes = json_encode($account_codes);

//$app['typeahead']->invalidate_thumbprint('account_codes', false, crc32($account_codes));

header('Content-type: application/json');

echo $account_codes;
