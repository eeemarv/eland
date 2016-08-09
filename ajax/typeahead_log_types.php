<?php
$rootpath = '../';
$page_access = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$log_types = [];

$st = $app['db']->prepare('select distinct type
	from eland_extra.logs
	where schema = ?
	order by type asc');

$st->bindValue(1, $schema);

$st->execute();

while ($row = $st->fetch())
{
	$log_types[] = $row['type'];
}

$log_types = json_encode($log_types);

invalidate_typeahead_thumbprint('log_types', false, crc32($log_types), 345600); // 4 days

header('Content-type: application/json');

echo $log_types;
