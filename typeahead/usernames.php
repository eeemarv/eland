<?php
$rootpath = '../';
$page_access = 'admin';
require_once __DIR__ . '/../include/web.php';

$tschema = $app['this_group']->get_schema();

$except = $_GET['except'] ?? 0;

$usernames = [];

$st = $app['db']->prepare('select name
	from ' . $tschema . '.users
	where id <> ?
	order by name asc', [$except]);
$st->execute();

while ($row = $st->fetch())
{
	if (empty($row['name']))
	{
		continue;
	}

	$usernames[] = $row['name'];
}

$usernames = json_encode($usernames);

//$app['typeahead']->invalidate_thumbprint('usernames?except=' . $except, false, crc32($usernames));

header('Content-type: application/json');

echo $usernames;
