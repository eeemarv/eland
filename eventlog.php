<?php
ob_start();
$rootpath = "./";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$q = $_GET['q'];
$letscode = $_GET['letscode'];
$type = $_GET['type'];

$letsgroup_id = $db->GetOne('SELECT id
	FROM letsgroups
	WHERE apimethod = \'internal\'');

$includejs = '
	<script src="' . $cdn_jquery . '"></script>
	<script src="' . $cdn_typeahead . '"></script>
	<script src="' . $rootpath . 'js/eventlog.js"></script>';

include($rootpath."includes/inc_header.php");

echo "<h1>Logs</h1>";

echo "<form method='get'>";
echo "<table class='selectbox' cellspacing='0' cellpadding='0' border='0'>";

echo "<tr><td valign='top' align='right'>Zoek event</td>";
echo "<td>";
echo "<input  type='text' name='q' value='" .$q ."' size='30'>";
echo "</td>";
echo "</tr>";

echo '<tr><td valign="top" align="right">Type</td><td>';
echo '<input type="text" name="type" value="' . $type . '" size="30">';
echo '</td></tr>';

echo '<tr><td valign="top" align="right">Letscode</td><td>';
echo '<input type="text" name="letscode" value="' . $letscode . '" size="30"';
echo ' data-letsgroup-id="' . $letsgroup_id . '">';
echo '</td></tr>';

echo "<tr><td></td><td>";
echo "<input type='submit' value='Zoeken' name='zend'>";
echo "</td><td></td></tr>";
echo "</table>";
echo "</form>";

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
	//$find['event'] = '|' . $q . '|i';
	$find['event'] = array('$regex' => new MongoRegex('/' . $q . '/i'));
}

$rows = $elas_log->find($find);

echo "<div class='border_b'><table class='data' cellpadding='0' cellspacing='0' border='1' width='99%'>";
echo "<tr class='header'>\n";
echo '<td rowspan="2">Tijd</td>';
echo "<td>Type</td>";
echo "<td>ip</td>";
echo "<td>gebruiker</td>";
echo '</tr><tr class="header">';
echo "<td colspan='3'>Event</td>";
echo "</tr>";

$i = 0;

foreach($rows as $value)
{
	$i++;
	$class = ($i % 2) ? 'uneven_row' : 'even_row';
	echo '<tr class="' . $class . '">';
	echo '<td rowspan="2">' . $value['ts_tz'] .'</td>';
	echo "<td>" .$value['type'] ."</td>";
	echo "<td>" .$value['ip'] ."</td>";
	echo '<td><a href="' . $rootpath . 'users/view.php?id=' . $value['user_id'] . '">';
	echo $value['letscode'] . ' ' . $value['username'] . '</a></td>';
	echo "</tr>";
	echo '<tr class="' . $class . '">';
	echo '<td colspan="3">' . $value['event'] . '</td>';
	echo '</tr>';
}

echo "</table></div>";

include($rootpath."includes/inc_footer.php");
