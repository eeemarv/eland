<?php
$rootpath = '../';
$page_access = 'admin';
require_once __DIR__ . '/../include/web.php';

$tschema = $app['this_group']->get_schema();

$usernames = [];

$st = $app['db']->prepare('select letscode
	from ' . $tschema . '.users
	order by letscode asc');
$st->execute();

while ($row = $st->fetch())
{
	if (empty($row['letscode']))
	{
		continue;
	}

	$usernames[] = $row['letscode'];
}

$usernames = json_encode($usernames);

$app['typeahead']->invalidate_thumbprint('account_codes', false, crc32($usernames));

header('Content-type: application/json');

echo $usernames;
