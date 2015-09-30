<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';

$users = $out = $in = array();

$rs = $db->prepare('select *
	from users
	where status in (1, 2)
	order by letscode');

$rs->execute();

while ($row = $rs->fetch())
{
	$users[$row['id']] = $row;
}

if (isset($_GET['zend']))
{
	$date = $_GET['date'];
	$filter = $_GET['filter'];

	$d  = explode('-', $date);
	if (!checkdate($d[1], $d[2], $d[0]))
	{
		$alert->error('Geen geldige datum.');
		header('Location: ' . $rootpath . 'reports/balance.php');
		exit;
	}

	if ($date)
	{
		$rs = $db->prepare('select id_to, sum(amount)
			from transactions
			where date >= ?
			group by id_to');
		$rs->bindValue(1, $date);

		$rs->execute();

		while($row = $rs->fetch())
		{
			$out[$row['id_to']] = $row['sum'];
		}

		$rs = $db->prepare('select id_from, sum(amount)
			from transactions
			where date >= ?
			group by id_from');
		$rs->bindValue(1, $date);

		$rs->execute();

		while($row = $rs->fetch())
		{
			$out[$row['id_from']] = $row['sum'];
		}

		array_walk($users, function(&$user, $id) use ($out, $in){
			$user['saldo'] += $in[$id];
			$user['saldo'] -= $out[$id];
		});
	}
}

$includejs = '
	<script src="' . $cdn_datepicker . '"></script>
	<script src="' . $cdn_datepicker_nl . '"></script>
	<script src="' . $cdn_typeahead . '"></script>';

$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_datepicker_css . '" />';

$top_right = '<a href="' . $rootpath . 'reports/print_balance.php?date=';
$top_right .= $date . '">';
$top_right .= '<i class="fa fa-print"></i>&nbsp;print</a>&nbsp;&nbsp;';
$top_right .= '<a href="' . $rootpath . 'reports/csv_balance.php?date=';
$top_right .= $date . '" target="new">';
$top_right .= '<i class="fa fa-file"></i>';
$top_right .= '&nbsp;csv</a>';

$h1 = 'Saldo op datum';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="get">';
echo '<div class="col-lg-12">';
echo '<div class="input-group">';
echo '<span class="input-group-btn">';
echo '<button class="btn btn-default" type="submit" name="zend" value="1">Toon</button>';
echo '</span>';
echo '<input type="text" class="form-control" name="date" ';
echo 'data-provide="datepicker" data-date-format="yyyy-mm-dd" ';
echo 'data-date-language="nl" ';
echo 'data-date-today-highlight="true" ';
echo 'data-date-autoclose="true" ';
echo 'data-date-enable-on-readonly="false" ';
echo 'placeholder="Datum jjjj-mm-dd" ';
echo 'value="' . $date . '">';
echo '</div>';
echo '</div>';
echo '</form>';
echo '<div class="clearfix"></div>';

echo '</div>';
echo '</div>';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="get">';

echo '<div class="form-group">';
echo '<div class="col-lg-12">';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-search"></i>';
echo '</span>';
echo '<input type="text" class="form-control" id="filter" name="filter" value="' . $filter . '">';
echo '</div>';
echo '</div>';
echo '</div>';

echo '</form>';
echo '<div class="clearfix"></div>';
echo '</div>';
echo '</div>';

//show table
echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-striped table-hover footable"';
echo ' data-filter="#filter" data-filter-minimum="1">';
echo '<thead>';

echo '<tr>';
echo '<th data-sort-initial="true">Code</th>';
echo '<th>Naam</th>';
echo '<th>Saldo</th>';
echo '</tr>';

echo '</thead>';
echo '<tbody>';

foreach($users as $value)
{
	$id = $value['id'];

	$class = ($newusertreshold < strtotime($value['adate'])) ? ' class="success"' : '';
	$class = ($value["status"] == 2) ? ' class="danger"' : $class;

	echo '<tr' . $class . '>';

	echo '<td>';
	echo '<a href="users.php?id=' .$id .'">';
	echo $value['letscode'];
	echo '</a></td>';

	echo '<td>';
	echo '<a href="users.php?id=' .$id .'">'.htmlspecialchars($value['name'],ENT_QUOTES).'</a>';
	echo '</td>';

	echo '<td>';
	$balance = $value['saldo'];
	if($balance < $value['minlimit'] || ($value['maxlimit'] != NULL && $balance > $value['maxlimit']))
	{
		echo '<span class="text-danger">' . $balance . '</span>';
	}
	else
	{
		echo $balance;
	}

	echo '</td>';
	echo '</tr>';

}
echo '</tbody>';
echo '</table>';
echo '</div>';
echo '</div>';
echo '</div>';





//show_user_balance($users,$user_date,$user_prefix);

/////////////

function show_printversion($rootpath,$user_date,$user_prefix)
{
	echo "<a href='print_balance.php?date=";
	echo $user_date;
	echo "&prefix=" .$user_prefix;
	echo "'>";
	echo "<img src='".$rootpath."gfx/print.gif' border='0'> ";
	echo "Printversie</a>";
}

function show_csvversion($rootpath,$user_date,$user_prefix)
{
	echo "<a href='csv_balance.php?date=";
	echo $user_date;
	echo "&prefix=" .$user_prefix;
	echo "'>";
	echo "<img src='".$rootpath."gfx/csv.jpg' border='0'> ";
	echo "CSV Export</a>";
}

include $rootpath . 'includes/inc_footer.php';
