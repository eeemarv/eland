<?php

$page_access = 'admin';
require_once __DIR__ . '/include/web.php';

$q = $_GET['q'] ?? '';
$code = $_GET['code'] ?? '';
$type = $_GET['type'] ?? '';
$fdate = $_GET['fdate'] ?? '';
$tdate = $_GET['tdate'] ?? '';
$orderby = $_GET['orderby'] ?? 'ts';
$asc = $_GET['asc'] ?? 0;
$limit = $_GET['limit'] ?? 25;
$start = $_GET['start'] ?? 0;

$app['log_db']->update();

$params = [
	'orderby'	=> $orderby,
	'asc'		=> $asc,
	'limit'		=> $limit,
	'start'		=> $start,
];

$params_sql = $where_sql = [];

$params_sql[] = $app['this_group']->get_schema();

if ($code)
{
	[$l] = explode(' ', $code);

	$where_sql[] = 'letscode = ?';
	$params_sql[] = strtolower($l);
	$params['code'] = $l;
}

if ($type)
{
	$where_sql[] = 'type ilike ?';
	$params_sql[] = strtolower($type);
	$params['type'] = $type;
}

if ($q)
{
	$where_sql[] = 'event ilike ?';
	$params_sql[] = '%' . $q . '%';
	$params['q'] = $q;
}

if ($fdate)
{
	$where_sql[] = 'ts >= ?';
	$params_sql[] = $fdate;
	$params['fdate'] = $fdate;
}

if ($tdate)
{
	$where_sql[] = 'ts <= ?';
	$params_sql[] = $tdate;
	$params['tdate'] = $tdate;
}

if (count($where_sql))
{
	$where_sql = ' and ' . implode(' and ', $where_sql) . ' ';
}
else
{
	$where_sql = '';
}

$query = 'select *
	from xdb.logs
		where schema = ?' . $where_sql . '
	order by ' . $orderby . ' ';

$row_count = $app['db']->fetchColumn('select count(*)
	from xdb.logs
	where schema = ?' . $where_sql, $params_sql);

$query .= ($asc) ? 'asc ' : 'desc ';
$query .= ' limit ' . $limit . ' offset ' . $start;

$rows = $app['db']->fetchAll($query, $params_sql);

$app['pagination']->init('logs', $row_count, $params, $inline);

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
	'code'	=> array_merge($asc_preset_ary, [
		'lbl' 		=> 'Gebruiker',
		'data_hide'	=> 'phone, tablet',
	]),
	'event'	=> array_merge($asc_preset_ary, [
		'lbl' 		=> 'Event',
		'data_hide'	=> 'phone',
	]),
];

$tableheader_ary[$orderby]['asc'] = $asc ? 0 : 1;
$tableheader_ary[$orderby]['indicator'] = $asc ? '-asc' : '-desc';

$top_right .= '<a href="#" class="csv">';
$top_right .= '<i class="fa fa-file"></i>';
$top_right .= '&nbsp;csv</a>';

$app['assets']->add(['datepicker', 'typeahead', 'typeahead.js', 'csv.js']);

$filtered = $q || $type || $code || $fdate || $tdate;

$h1 = 'Logs';
$h1 .= ($filtered) ? ' <small>gefilterd</small>' : '';

$fa = 'history';

include __DIR__ . '/include/header.php';

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
echo 'name="q" id="q" placeholder="Zoek Event" ';
echo 'value="';
echo $q;
echo '">';
echo '</div>';
echo '</div>';

echo '<div class="col-sm-3">';
echo '<div class="input-group margin-bottom">';
echo '<span class="input-group-addon" id="type_addon">';
echo 'Type</span>';

echo '<input type="text" class="form-control" ';
echo 'aria-describedby="type_addon" ';
echo 'data-typeahead="';
echo $app['typeahead']->get('log_types');
echo '" ';
echo 'name="type" id="type" placeholder="Type" ';
echo 'value="';
echo $type;
echo '">';
echo '</div>';
echo '</div>';

$typeahead_users_ary = ['users_active', 'users_extern',
	'users_inactive', 'users_im', 'users_ip'];

echo '<div class="col-sm-3">';
echo '<div class="input-group margin-bottom">';
echo '<span class="input-group-addon" id="code_addon">';
echo '<span class="fa fa-user"></span></span>';

echo '<input type="text" class="form-control" ';
echo 'aria-describedby="code_addon" ';
echo 'data-typeahead="';
echo $app['typeahead']->get($typeahead_users_ary);
echo '" ';
echo 'data-newuserdays="';
echo $app['config']->get('newuserdays', $app['this_group']->get_schema());
echo '" ';
echo 'name="code" id="code" placeholder="Account Code" ';
echo 'value="';
echo $code;
echo '">';
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

echo $app['pagination']->get();

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
		echo $data['lbl'] . '&nbsp;';
		echo '<i class="fa fa-sort' . $data['indicator'] . '"></i>';
		echo '</a>';
	}
	echo '</th>';
}

echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach($rows as $value)
{
	echo '<tr>';
	echo '<td>' . $app['date_format']->get($value['ts'], 'sec') .'</td>';
	echo '<td>' . $value['type'] . '</td>';
	echo '<td>' . $value['ip'] . '</td>';
	echo '<td>';
	echo (isset($value['user_id']) && ctype_digit((string) $value['user_id'])) ? link_user($value['user_id'], $value['user_schema']) : 'geen';
	echo '</td>';
	echo '<td>' . $value['event'] . '</td>';
	echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div></div>';

echo $app['pagination']->get();

include __DIR__ . '/include/footer.php';
