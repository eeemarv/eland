<?php
$rootpath = './';
$page_access = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$q = $_GET['q'];
$letscode = $_GET['letscode'];
$type = $_GET['type'];

$find = array();

if ($letscode)
{
	list($l) = explode(' ', $letscode);
	$find['letscode'] = strtolower(trim($l));
}

if ($type)
{
	$find['type'] = strtolower(trim($type));
}

if ($q)
{
	$find['event'] = array('$regex' => new MongoRegex('/' . $q . '/i'));
}

$mdb->connect();
$rows = $mdb->logs->find($find)->sort(array('timestamp' => -1))->limit(300);

$includejs = '
	<script src="' . $cdn_typeahead . '"></script>
	<script src="' . $rootpath . 'js/typeahead.js"></script>';

$h1 = 'Logs';
$fa = 'list';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="get" class="form-horizontal">';

echo '<div class="form-group">';
echo '<label for="q" class="col-sm-2 control-label">Zoek event</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="q" name="q" ';
echo 'value="' . $q . '">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="type" class="col-sm-2 control-label">Type</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="type" name="type" ';
echo 'data-typeahead="' . get_typeahead('log_types') . '" ';
echo 'value="' . $type . '">';
echo '</div>';
echo '</div>';

$typeahead_users_ary = array('users_active', 'users_extern', 'users_inactive', 'users_im', 'users_ip');

echo '<div class="form-group">';
echo '<label for="letscode" class="col-sm-2 control-label">Letscode</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="letscode" name="letscode" ';
echo 'data-typeahead="' . get_typeahead($typeahead_users_ary) . '" ';
echo 'value="' . $letscode . '">';
echo '</div>';
echo '</div>';

echo '<input type="submit" value="Zoeken" name="zend" style="display: none;">';

echo '</form>';

echo '</div>';
echo '</div>';

echo '<div class="panel panel-default printview">';

echo '<div class="table-responsive">';
echo '<table class="table table-hover table-bordered table-striped footable">';
echo '<thead>';
echo '<tr>';
echo '<th data-sort-initial="descending">Tijd</th>';
echo '<th>Type</th>';
echo '<th data-hide="phone, tablet">ip</th>';
echo '<th data-hide="phone, tablet">gebruiker</th>';

echo '<th data-hide="phone">Event</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';
foreach($rows as $value)
{
	echo '<tr>';
	echo '<td>' . $value['ts_tz'] .'</td>';
	echo '<td>' . $value['type'] . '</td>';
	echo '<td>' . $value['ip'] . '</td>';
	echo '<td>' . link_user($value['user_id']) . '</td>';
	echo '<td>' . $value['event'] . '</td>';
	echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div></div>';

include $rootpath . 'includes/inc_footer.php';
