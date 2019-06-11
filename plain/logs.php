<?php

if (!$app['s_admin'])
{
	exit;
}

$filter = $app['request']->query->get('f', []);
$pag = $app['request']->query->get('p', []);
$sort = $app['request']->query->get('s', []);

$app['log_db']->update();

$params = [
	's'	=> [
		'orderby'	=> $sort['orderby'] ?? 'ts',
		'asc'		=> $sort['asc'] ?? 0,
	],
	'p'	=> [
		'start'		=> $pag['start'] ?? 0,
		'limit'		=> $pag['limit'] ?? 25,
	],
];

$params_sql = $where_sql = [];

$params_sql[] = $app['tschema'];

if (isset($filter['code'])
	&& $filter['code'])
{
	[$l_code] = explode(' ', $filter['code']);

	$where_sql[] = 'letscode = ?';
	$params_sql[] = strtolower($l_code);
	$params['f']['code'] = $filter['code'];
}

if (isset($filter['type'])
	&& $filter['type'])
{
	$where_sql[] = 'type ilike ?';
	$params_sql[] = strtolower($filter['type']);
	$params['f']['type'] = $filter['type'];
}

if (isset($filter['q'])
	&& $filter['q'])
{
	$where_sql[] = 'event ilike ?';
	$params_sql[] = '%' . $filter['q'] . '%';
	$params['f']['q'] = $filter['q'];
}

if (isset($filter['fdate'])
	&& $filter['fdate'])
{
	$where_sql[] = 'ts >= ?';
	$params_sql[] = $filter['fdate'];
	$params['f']['fdate'] = $filter['fdate'];
}

if (isset($filter['tdate'])
	&& $filter['tdate'])
{
	$where_sql[] = 'ts <= ?';
	$params_sql[] = $filter['tdate'];
	$params['f']['tdate'] = $filter['tdate'];
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
	order by ' . $params['s']['orderby'] . ' ';

$row_count = $app['db']->fetchColumn('select count(*)
	from xdb.logs
	where schema = ?' . $where_sql, $params_sql);

$query .= $params['s']['asc'] ? 'asc ' : 'desc ';
$query .= ' limit ' . $params['p']['limit'];
$query .= ' offset ' . $params['p']['start'];

$rows = $app['db']->fetchAll($query, $params_sql);

$app['pagination']->init('logs', $app['pp_ary'],
	$row_count, $params);

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

$tableheader_ary[$params['s']['orderby']]['asc'] = $params['s']['asc'] ? 0 : 1;
$tableheader_ary[$params['s']['orderby']]['indicator'] = $params['s']['asc'] ? '-asc' : '-desc';

$app['btn_nav']->csv();

$app['assets']->add(['datepicker', 'csv.js']);

$filtered = (isset($filter['q']) && $filter['q'] !== '')
	|| (isset($filter['type']) && $filter['type'] !== '')
	|| (isset($filter['code']) && $filter['code'] !== '')
	|| (isset($filter['fdate']) && $filter['fdate'] !== '')
	|| (isset($filter['tdate']) && $filter['tdate'] !== '');

$app['heading']->add('Logs');
$app['heading']->add_filtered($filtered);
$app['heading']->fa('history');

include __DIR__ . '/../include/header.php';

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
echo 'name="f[q]" id="q" placeholder="Zoek Event" ';
echo 'value="';
echo $filter['q'] ?? '';
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

echo $app['typeahead']->ini($app['pp_ary'])
	->add('log_types', [])
	->str();

echo '" ';
echo 'name="f[type]" id="type" placeholder="Type" ';
echo 'value="';
echo $filter['type'] ?? '';
echo '">';
echo '</div>';
echo '</div>';

echo '<div class="col-sm-3">';
echo '<div class="input-group margin-bottom">';
echo '<span class="input-group-addon" id="code_addon">';
echo '<span class="fa fa-user"></span></span>';

echo '<input type="text" class="form-control" ';
echo 'aria-describedby="code_addon" ';

echo 'data-typeahead="';
echo $app['typeahead']->ini($app['pp_ary'])
	->add('accounts', ['status' => 'active'])
	->add('accounts', ['status' => 'inactive'])
	->add('accounts', ['status' => 'ip'])
	->add('accounts', ['status' => 'im'])
	->add('accounts', ['status' => 'extern'])
	->str([
		'filter'        => 'accounts',
		'newuserdays'   => $app['config']->get('newuserdays', $app['tschema']),
	]);
echo '" ';

echo 'name="f[code]" id="code" placeholder="Account Code" ';
echo 'value="';
echo $filter['code'] ?? '';
echo '">';
echo '</div>';
echo '</div>';

echo '<div class="col-sm-2">';
echo '<input type="submit" value="Toon" ';
echo 'class="btn btn-default btn-block" name="zend">';
echo '</div>';

echo '</div>';

$params_form = $params;
unset($params_form['f']);
unset($params_form['uid']);
unset($params_form['p']['start']);

$params_form['r'] = 'admin';
$params_form['u'] = $app['s_id'];

$params_form = http_build_query($params_form, 'prefix', '&');
$params_form = urldecode($params_form);
$params_form = explode('&', $params_form);

foreach ($params_form as $param)
{
	[$name, $value] = explode('=', $param);

	if (!isset($value) || $value === '')
	{
		continue;
	}

	echo '<input name="' . $name . '" ';
	echo 'value="' . $value . '" type="hidden">';
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

foreach ($tableheader_ary as $key_orderby => $data)
{
	echo '<th';

	if (isset($data['data_hide']))
	{
		echo ' data-hide="' . $data['data_hide'] . '"';
	}

	echo '>';

	if (isset($data['no_sort']))
	{
		echo $data['lbl'];
	}
	else
	{
		$th_params['s'] = [
			'orderby'	=> $key_orderby,
			'asc' 		=> $data['asc'],
		];

		echo '<a href="';
		echo $app->path('logs', array_merge($app['pp_ary'], $th_params));
		echo '">';
		echo $data['lbl'];
		echo '&nbsp;';
		echo '<i class="fa fa-sort';
		echo $data['indicator'];
		echo '"></i>';
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
	echo '<td>';
	echo $app['date_format']->get($value['ts'], 'sec', $app['tschema']);
	echo '</td>';
	echo '<td>';
	echo $value['type'];
	echo '</td>';
	echo '<td>';
	echo $value['ip'];
	echo '</td>';
	echo '<td>';

	if (isset($value['user_schema'])
		&& isset($value['user_id'])
		&& ctype_digit((string) $value['user_id'])
		&& !empty($value['user_schema']))
	{
		echo $app['account']->link($value['user_id'], $value['user_schema']);
	}
	else
	{
		echo '<i> ** geen ** </i>';
	}

	echo '</td>';
	echo '<td>' . $value['event'] . '</td>';
	echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div></div>';

echo $app['pagination']->get();

include __DIR__ . '/../include/footer.php';
