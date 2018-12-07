<?php
$rootpath = '../';
$page_access = 'admin';
require_once __DIR__ . '/../include/web.php';

$tschema = $app['this_group']->get_schema();

$usernames = [];

$st = $app['db']->prepare('select name
	from ' . $tschema . '.users
	order by name asc');
$st->execute();

while ($row = $st->fetch())
{
	$usernames[] = $row['name'];
}

$usernames = json_encode($usernames);

$app['typeahead']->invalidate_thumbprint('usernames', false, crc32($usernames));

header('Content-type: application/json');

echo $usernames;
