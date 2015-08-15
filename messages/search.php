<?php
ob_start();
$rootpath = '../';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';

$q = ($_GET['q']) ?: '';
$hsh = ($_GET['hsh']) ?: '';

$msgs = $db->GetArray('select m.*,
		c.id as cid, c.fullname as cat,
		u.letscode, u.fullname, u.id as uid, u.postcode
	from messages m, categories c, users u
	where m.id_category = c.id
		and m.id_user = u.id
		and u.status in (1, 2)
	order by id desc');

if (in_array($s_accountrole, array('user', 'admin')))
{ 
	$top_buttons = '<a href="' . $rootpath . 'messages/edit.php?mode=new" class="btn btn-success"';
	$top_buttons .= ' title="Vraag of aanbod toevoegen"><i class="fa fa-plus"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

	$top_buttons .= '<a href="' . $rootpath . 'userdetails/mymsg_overview.php" class="btn btn-default"';
	$top_buttons .= ' title="Mijn vraag en aanbod"><i class="fa fa-user"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Mijn vraag en aanbod</span></a>';
}

$h1 = 'Vraag en aanbod';
$fa = 'newspaper-o';

$includejs = '<script src="' . $rootpath . 'js/combined_filter.js"></script>';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="get">';
echo '<div class="row">';
echo '<div class="col-xs-12">';
echo '<div class="input-group">';
echo '<span class="input-group-addon">';
echo '<i class="fa fa-search"></i>';
echo '</span>';
echo '<input type="text" class="form-control" id="q" value="' . $q . '" name="q">';
echo '</div>';
echo '</div>';
echo '</div>';
echo '<input type="hidden" value="" id="combined-filter">';
echo '<input type="hidden" value="' . $hsh . '" name="hsh" id="hsh">';
echo '</form>';

echo '</div>';
echo '</div>';

echo '<ul class="nav nav-tabs" id="nav-tabs">';
echo '<li class="active"><a href="#" class="bg-white" data-filter="">Alle</a></li>';
echo '<li><a href="#" class="bg-white" data-filter="34a94cd7">Geldig</a></li>';
echo '<li><a href="#" class="bg-danger" data-filter="09293e38">Vervallen</a></li>';
echo '</ul>';

echo '<div class="table-responsive">';
echo '<table class="table table-hover table-striped table-bordered footable" ';
echo 'data-filter="#combined-filter" data-filter-minimum="1">';
echo '<thead>';
echo '<tr>';
echo "<th>V/A</th>";
echo "<th>Wat</th>";
echo '<th data-hide="phone, tablet">Geldig tot</th>';
echo '<th data-hide="phone, tablet">Wie</th>';
echo '<th data-hide="phone, tablet">Categorie</th>';
echo '<th data-hide="phone">Plaats</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach($msgs as $msg)
{
	$del = (strtotime($msg['validity']) < time()) ? true : false;

	echo '<tr';
	echo ($del) ? ' class="danger"' : '';
	echo '>';

	echo '<td ';
	echo ' data-value="' . (($del) ? '09293e38' : '34a94cd7') . '">';
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

	echo '<td>';
	echo '<a href="' . $rootpath . 'searchcat_viewcat.php?id=' . $msg['cid'] . '">';
	echo htmlspecialchars($msg['cat'],ENT_QUOTES);
	echo '</a>';
	echo '</td>';

	echo '<td>';
	echo $msg['postcode'];
	echo '</td>';

	echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';
