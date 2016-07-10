<?php
$rootpath = './';
$page_access = 'admin';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_pagination.php';

$q = isset($_GET['q']) ? $_GET['q'] : '';
$letscode = isset($_GET['letscode']) ? $_GET['letscode'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';

$orderby = (isset($_GET['orderby'])) ? $_GET['orderby'] : 'ts';
$asc = (isset($_GET['asc'])) ? $_GET['asc'] : 0;

$limit = (isset($_GET['limit'])) ? $_GET['limit'] : 25;
$start = (isset($_GET['start'])) ? $_GET['start'] : 0;


/*
$find = [];


	$log_item = [
		'schema'		=> $sch,
		'user_id'		=> ($s_master || $s_elas_guest) ? 0 : (($s_id) ?: 0),
		'user_schema'	=> $s_schema,
		'letscode'		=> strtolower($letscode),
		'username'		=> $username,
		'ip'			=> $ip,
		'type'			=> strtolower($type),
		'event'			=> $event,
	];

	$db->insert('eland_extra.logs', $log_item);
*/

$params = [
	'orderby'	=> $orderby,
	'asc'		=> $asc,
	'limit'		=> $limit,
	'start'		=> $start,
];

$params_sql = $where_sql = [];

$params_sql[] = $schema;

if ($letscode)
{
	list($l) = explode(' ', $letscode);

	$where_sql[] = 'letscode = ?';
	$params_sql[] = strtolower($l);
	$params['letscode'] = $l;
}

if ($type)
{
	$where_sql[] = 'type = ?';
	$params_sql[] = strtolower($type);
	$params['type'] = $type;
}

if ($q)
{
	$where_sql[] = 'event ilike ?';
	$params_sql[] = '%' . $q . '%';
	$params['q'] = $q;
}


/*

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

*/

if (count($where_sql))
{
	$where_sql = ' and ' . implode(' and ', $where_sql) . ' ';
}
else
{
	$where_sql = '';
}

$query = 'select *, to_char(ts, \'YYYY-MM-DD HH24:MI:SS\') as tss
	from eland_extra.logs
		where schema = ?' . $where_sql . '
	order by ' . $orderby . ' ';

$row_count = $db->fetchColumn('select count(*)
	from eland_extra.logs
	where schema = ?' . $where_sql, $params_sql);

$query .= ($asc) ? 'asc ' : 'desc ';
$query .= ' limit ' . $limit . ' offset ' . $start;

$rows = $db->fetchAll($query, $params_sql);

$pagination = new pagination('logs', $row_count, $params, $inline);

$asc_preset_ary = [
	'asc'	=> 0,
	'indicator' => '',
];

$tableheader_ary = [
	'ts' => array_merge($asc_preset_ary, [
		'lbl' => 'Tijd']),
	'type' => array_merge($asc_preset_ary, [
		'lbl' => 'Type']),

	'ip'	=> array_merge($asc_preset_ary, [
		'lbl' 		=> 'ip',
		'data_hide' => 'phone, tablet',
	]),
	'user_id'	=> array_merge($asc_preset_ary, [
		'lbl' 		=> 'Gebruiker',
		'data_hide'	=> 'phone, tablet',
	]),
	'event'	=> array_merge($asc_preset_ary, [
		'lbl' 		=> 'Event',
		'data_hide'	=> 'phone',
	]),
];

$tableheader_ary[$orderby]['asc'] = ($asc) ? 0 : 1;
$tableheader_ary[$orderby]['indicator'] = ($asc) ? '-asc' : '-desc';


$top_right .= '<a href="#" class="csv">';
$top_right .= '<i class="fa fa-file"></i>';
$top_right .= '&nbsp;csv</a>';

$includejs = '
	<script src="' . $rootpath . 'js/csv.js"></script>
	<script src="' . $cdn_typeahead . '"></script>
	<script src="' . $rootpath . 'js/typeahead.js"></script>';

$filtered = $q || $type || $letscode;

$h1 = 'Logs';
$h1 .= ($filtered) ? ' <small>gefilterd</small>' : '';

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

$pagination->render();

echo '<div class="panel panel-default printview">';

echo '<div class="table-responsive">';
echo '<table class="table table-hover table-bordered table-striped footable csv" ';
echo 'data-sort="false">';
echo '<thead>';
echo '<tr>';

$th_params = $params;

$th_params['start'] = 0;

foreach ($tableheader_ary as $key_orderby => $data)
{
	echo '<th';
	echo (isset($data['data_hide'])) ? ' data-hide="' . $data['data_hide'] . '"' : '';
	echo '>';
	if (isset($data['no_sort']))
	{
		echo $data['lbl'];
	}
	else
	{
		$th_params['orderby'] = $key_orderby;
		$th_params['asc'] = $data['asc'];

		echo '<a href="' . generate_url('logs', $th_params) . '">';
		echo $data['lbl'] . '&nbsp;<i class="fa fa-sort' . $data['indicator'] . '"></i>';
		echo '</a>';
	}
	echo '</th>';
}





/*
echo '<th data-sort-initial="descending">Tijd</th>';
echo '<th>Type</th>';
echo '<th data-hide="phone, tablet">ip</th>';
echo '<th data-hide="phone, tablet">gebruiker</th>';

echo '<th data-hide="phone">Event</th>';
*/
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach($rows as $value)
{
	echo '<tr>';
	echo '<td>' . $value['tss'] .'</td>';
	echo '<td>' . $value['type'] . '</td>';
	echo '<td>' . $value['ip'] . '</td>';
	echo '<td>';
	echo (isset($value['user_id']) && ctype_digit((string) $value['user_id'])) ? link_user($value['user_id']) : 'geen';
	echo '</td>';
	echo '<td>' . $value['event'] . '</td>';
	echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div></div>';

$pagination->render();

include $rootpath . 'includes/inc_footer.php';
