<?php
ob_start();
$rootpath = "";
$role = 'guest';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if (!isset($_GET['id']))
{
	header("Location: searchcat.php");
	exit;
}

$id = $_GET["id"];

$msgs = $db->GetArray('select m.*,
		u.letscode, u.fullname, u.id as uid
	from messages m, users u
	where m.id_user = u.id
		and u.status in (1, 2)
	order by id desc');


if (in_array($s_accountrole, array('admin', 'user')))
{
	$top_buttons = '<a href="' . $rootpath . 'messages/edit.php?mode=new" class="btn btn-success"';
	$top_buttons .= ' title="Vraag of aanbod toevoegen"><i class="fa fa-plus"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';
}

$h1 = $db->GetOne("SELECT fullname FROM categories WHERE id=". $id);
$fa = 'newspaper-o';

include $rootpath . 'includes/inc_header.php';

echo '<ul class="nav nav-tabs">';
echo '<li class="active"><a href="#" class="bg-white">Alle</a></li>';
echo '<li class="active"><input type="text" class="search"></li>';
echo '<li><a href="#" class="bg-white">Geldig</a></li>';
echo '<li><a href="#" class="bg-danger">Vervallen</a></li>';
echo '</ul>';

/*
echo "<br>Filter: ";
echo "<a href='overview.php?user_filterby=all'>Alle</a>";
echo " - ";
echo "<a href='overview.php?user_filterby=expired'>Vervallen</a>";
echo " - ";
echo "<a href='overview.php?user_filterby=valid'>Geldig</a>";
*/

echo '<div class="table-responsive">';
echo '<table class="table table-hover table-striped table-bordered footable">';
echo '<thead>';
echo '<tr>';
echo "<th>V/A</th>";
echo "<th>Wat</th>";
echo '<th data-hide="phone, tablet">Geldig tot</th>';
echo '<th data-hide="phone, tablet">Wie</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach($msgs as $msg)
{
	$del = (strtotime($msg['validity']) < time()) ? true : false;

	echo '<tr';
	echo ($del) ? ' class="danger"' : '';
	echo '>';
	echo '<td>';

	echo ($msg["msg_type"]) ? 'A' : 'V';
	echo '</td>';

	echo '<td>';
	echo '<a href="' .$rootpath . 'messages/view.php?id=' . $msg['id']. '">';
	echo htmlspecialchars($msg['content'],ENT_QUOTES);
	echo '</a>';
	echo '</td>';

	echo '<td>';
	echo $msg['validity'];
	echo '</td>';

	echo '<td>';
	echo '<a href="' . $rootpath . 'memberlist_view.php?id=' . $msg['uid'] . '">';
	echo htmlspecialchars($msg['letscode'] . ' ' . $msg['fullname'], ENT_QUOTES);
	echo '</a>';
	echo '</td>';

	echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';
