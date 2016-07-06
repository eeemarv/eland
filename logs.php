<?php
$rootpath = './';
$page_access = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$q = isset($_GET['q']) ? $_GET['q'] : '';
$letscode = isset($_GET['letscode']) ? $_GET['letscode'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';

$find = [];

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
	$find['event'] = ['$regex' => new MongoRegex('/' . $q . '/i')];
}

$mdb->connect();
$rows = $mdb->logs->find($find)->sort(['timestamp' => -1])->limit(300);

$includejs = '
	<script src="' . $cdn_typeahead . '"></script>
	<script src="' . $rootpath . 'js/typeahead.js"></script>';

$h1 = 'Logs';
$fa = 'history';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="get" class="form-horizontal">';

echo '<div class="row">';

echo '<div class="col-sm-4">';
echo '<div class="input-group margin-bottom">';
echo '<span class="input-group-addon" id="q_addon">';
echo '<i class="fa fa-search"></i></span>';

echo '<input type="text" class="form-control" ';
echo 'aria-describedby="q_addon" ';
echo 'name="q" id="q" placeholder="Zoek event" ';
echo 'value="' . $q . '">';
echo '</div>';
echo '</div>';

echo '<div class="col-sm-3">';
echo '<div class="input-group margin-bottom">';
echo '<span class="input-group-addon" id="type_addon">';
echo 'Type</span>';

echo '<input type="text" class="form-control" ';
echo 'aria-describedby="type_addon" ';
echo 'data-typeahead="' . get_typeahead('log_types') . '" '; 
echo 'name="type" id="type" placeholder="Type" ';
echo 'value="' . $type . '">';
echo '</div>';
echo '</div>';

$typeahead_users_ary = ['users_active', 'users_extern', 'users_inactive', 'users_im', 'users_ip'];

echo '<div class="col-sm-3">';
echo '<div class="input-group margin-bottom">';
echo '<span class="input-group-addon" id="letscode_addon">';
echo '<span class="fa fa-user"></span></span>';

echo '<input type="text" class="form-control" ';
echo 'aria-describedby="letscode_addon" ';
echo 'data-typeahead="' . get_typeahead($typeahead_users_ary) . '" '; 
echo 'name="letscode" id="letscode" placeholder="Letscode" ';
echo 'value="' . $letscode . '">';
echo '</div>';
echo '</div>';

echo '<div class="col-sm-2">';
echo '<input type="submit" value="Toon" class="btn btn-default btn-block" name="zend">';
echo '</div>';

echo '</div>';

$params_form = ['r' => 'admin', 'u' => $s_id];

foreach ($params_form as $name => $value)
{
	if (isset($value))
	{
		echo '<input name="' . $name . '" value="' . $value . '" type="hidden">';
	}
}

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
	echo '<td>';
	echo (isset($value['user_id']) && ctype_digit($value['user_id'])) ? link_user($value['user_id']) : 'geen';
	echo '</td>';
	echo '<td>' . $value['event'] . '</td>';
	echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div></div>';

include $rootpath . 'includes/inc_footer.php';
