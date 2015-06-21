<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';
require_once $rootpath . 'includes/inc_userinfo.php';

$users = $db->GetAssoc('select id, *
	from users
	where status in (1, 2)
	order by letscode');

if (isset($_GET['zend']))
{
	$date = $_GET['date'];
	$filter = $_GET['filter'];

	if ($date)
	{
		$out = $db->GetAssoc('select id_to, sum(amount)
			from transactions
			where date >= \'' . $date . '\'
			group by id_to');

		$in = $db->GetAssoc('select id_from, sum(amount)
			from transactions
			where date >= \'' . $date . '\'
			group by id_from');

		array_walk($users, function(&$user, $id) use ($out, $in){
			$user['saldo'] += $in[$id];
			$user['saldo'] -= $out[$id];
		});
	}
}

$includejs = '
	<script src="' . $cdn_jquery . '"></script>
	<script src="' . $cdn_datepicker . '"></script>
	<script src="' . $cdn_datepicker_nl . '"></script>
	<script src="' . $cdn_typeahead . '"></script>
	<script src="' . $rootpath . 'js/fooprefilter.js"></script>';

$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_datepicker_css . '" />';
/*
$top_right = '<a href="print_memberlist.php';
$top_right .= '">';
$top_right .= '<i class="fa fa-print"></i>&nbsp;print</a>&nbsp;&nbsp;';
$top_right .= '<a href="' . $rootpath . 'csv_memberlist.php';
$top_right .= '" target="new">';
$top_right .= '<i class="fa fa-file"></i>';
$top_right .= '&nbsp;csv</a>';
*/
$h1 = 'Saldo op datum';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="get">';

echo '<div class="form-group"';
echo '>';
echo '<label for="date" class="col-sm-2 control-label">Datum (jjjj-mm-dd)</label>';
echo '<div class="col-sm-10">';
echo '<input type="text" class="form-control" id="date" name="date" ';
echo 'data-provide="datepicker" data-date-format="yyyy-mm-dd" ';
echo 'data-date-language="nl" ';
echo 'data-date-today-highlight="true" ';
echo 'data-date-autoclose="true" ';
echo 'data-date-enable-on-readonly="false" ';
echo 'value="' . $date . '">';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<div class="col-sm-12">';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-search"></i>';
echo '</span>';
echo '<input type="text" class="form-control" id="filter" name="filter" value="' . $filter . '">';
echo '</div>';
echo '</div>';
echo '</div>';

echo '<button type="submit" name="zend" value="1" class="btn btn-default">Toon</button>';

echo '</form>';

echo '</div>';
echo '</div>';




/*
echo "<form method='GET'>";
echo "<table  class='data'  cellspacing='0' cellpadding='0' border='0'>\n";

echo "<tr><td>Datum afsluiting (yyyy-mm-dd):   </td>\n";
echo "<td>";
echo "<input type='text' name='date' size='10' ";
if (isset($posted_list["date"]))
{
	echo " value ='".$posted_list["date"]."' ";
}
echo 'data-provide="datepicker" data-date-format="yyyy-mm-dd" ';
echo 'data-date-language="nl" ';
echo 'data-date-today-highlight="true" ';
echo 'data-date-autoclose="true" ';
echo 'data-date-enable-on-readonly="false" ';        
echo ">";
echo "</td>";

echo "<td>";
	echo "<input type='submit' name='zend' value='Filter'>";
	echo "</td>\n</tr>\n\n";
echo "<tr>";
echo "<td>";
echo "Filter subgroep:";
	echo "</td><td>";
echo "<select name='prefix'>\n";

	echo "<option value='ALL'>ALLE</option>";
	$list_prefixes = get_prefixes();
	foreach ($list_prefixes as $key => $value){
			echo "<option value='" .$value["prefix"] ."'>" .$value["shortname"] ."</option>";
	}
	echo "</select>\n";
echo "</td></tr>\n\n";

echo "</table>\n";
	echo "</form>";

echo '</div>';
echo '</div>';


echo "</td>";
echo "<td valign='top' align='right'>";
show_printversion($rootpath,$user_date,$posted_list["prefix"]);
echo "<br>";
show_csvversion($rootpath,$user_date,$posted_list["prefix"]);
echo "</td>";
echo "</tr>";
echo "</table>";
*/

//show table
echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-striped table-hover footable"';
echo ' data-filter="#filter" data-filter-minimum="1">';
echo '<thead>';

echo '<tr>';
echo '<th data-sort-initial="true">Code</th>';
echo '<th data-filter="#filter">Naam</th>';
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
	echo '<a href="memberlist_view.php?id=' .$id .'">';
	echo $value['letscode'];
	echo '</a></td>';
	
	echo '<td>';
	echo '<a href="memberlist_view.php?id=' .$id .'">'.htmlspecialchars($value['fullname'],ENT_QUOTES).'</a>';
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
