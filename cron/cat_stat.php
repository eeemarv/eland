<?php
ob_start();

$role = 'anonymous';
$rootpath = '../';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

// update msg counts for each category

$offer_count = $db->GetAssoc('SELECT m.id_category, COUNT(m.*)
	FROM messages m, users u
	WHERE  m.id_user = u.id
		AND u.status IN (1, 2, 3)
		AND msg_type = 1
	GROUP BY m.id_category');
	
$want_count = $db->GetAssoc('SELECT m.id_category, COUNT(m.*)
	FROM messages m, users u
	WHERE  m.id_user = u.id
		AND u.status IN (1, 2, 3)
		AND msg_type = 0
	GROUP BY m.id_category');

$all_cat = $db->GetArray('SELECT id, stat_msgs_offers, stat_msgs_wanted
	FROM categories
	WHERE id_parent IS NOT NULL');

foreach ($all_cat as $val)
{
	$offers = $val['stat_msgs_offers'];
	$wants = $val['stat_msgs_wanted'];
	$id = $val['id'];

	$want_count[$id] = (isset($want_count[$id])) ? $want_count[$id] : 0;
	$offer_count[$id] = (isset($offer_count[$id])) ? $offer_count[$id] : 0;

	if ($want_count[$id] == $wants && $offer_count[$id] == $offers)
	{
		continue;
	}

	$stats = array(
		'stat_msgs_offers'	=> ($offer_count[$id]) ?: 0,
		'stat_msgs_wanted'	=> ($want_count[$id]) ?: 0,
	);
	
	$db->AutoExecute('categories', $stats, 'UPDATE', 'id = ' . $id);
}

echo 'msg counts updated for each category.';
