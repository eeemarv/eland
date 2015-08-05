<?php
ob_start();
$rootpath = './';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';

$q = $_GET['q'];
$letscode = $_GET['letscode'];
$type = $_GET['type'];

$letsgroup_id = $db->GetOne('SELECT id
	FROM letsgroups
	WHERE apimethod = \'internal\'');

$includejs = '
	<script src="' . $cdn_typeahead . '"></script>
	<script src="' . $rootpath . 'js/eventlog.js"></script>';

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
echo 'value="' . $type . '">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="letscode" class="col-sm-2 control-label">Letscode</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="letscode" name="letscode" ';
echo ' data-letsgroup-id="' . $letsgroup_id . '" ';
echo 'value="' . $letscode . '">';
echo '</div>';
echo '</div>';

echo '<input type="submit" value="Zoeken" name="zend" class="btn btn-default">';

echo '</form>';

echo '</div>';
echo '</div>';

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

$rows = $elas_log->find($find);

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
	echo '<td><a href="' . $rootpath . 'users/view.php?id=' . $value['user_id'] . '">';
	echo $value['letscode'] . ' ' . $value['username'] . '</a></td>';
	echo '<td>' . $value['event'] . '</td>';
	echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';
