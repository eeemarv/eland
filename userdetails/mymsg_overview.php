<?php
ob_start();
$rootpath = '../';
$role = 'user';
require_once $rootpath . 'includes/inc_default.php';

$msgs = $db->fetchAll('select m.*,
		c.id as cid, c.fullname as cat
	from messages m, categories c
	where m.id_category = c.id
		and m.id_user = ?
	order by id desc', array($s_id));

$top_buttons = '<a href="' . $rootpath . 'messages/edit.php?mode=new" class="btn btn-success"';
$top_buttons .= ' title="Vraag of aanbod toevoegen"><i class="fa fa-plus"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

$top_buttons .= '<a href="' . $rootpath . 'userdetails/mydetails.php" class="btn btn-default"';
$top_buttons .= ' title="Mijn gegevens"><i class="fa fa-user"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Mijn gegevens</span></a>';

$top_buttons .= '<a href="' . $rootpath . 'userdetails/mytrans_overview.php" class="btn btn-default"';
$top_buttons .= ' title="Mijn transacties"><i class="fa fa-exchange"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Mijn transacties</span></a>';

$top_buttons .= '<a href="' . $rootpath . 'messages/overview.php" class="btn btn-default"';
$top_buttons .= ' title="Alle vraag en aanbod"><i class="fa fa-newspaper-o"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Alle vraag en aanbod</span></a>';

$h1 = 'Mijn Vraag & Aanbod';
$fa = 'newspaper-o';

$includejs = '<script src="' . $rootpath . 'js/combined_filter.js"></script>';

include $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="get" class="form-horizontal">';

echo '<div class="row">';
echo '<div class="col-sm-12">';
echo '<div class="input-group">';
echo '<div class="input-group-addon">';
echo '<i class="fa fa-search"></i>';
echo '</div>';
echo '<input type="text" class="form-control" id="q" value="' . $q . '" name="q">';
echo '</div>';
echo '</div></div>';

echo '<input type="hidden" value="" id="combined-filter">';
echo '<input type="hidden" value="' . $hsh . '" name="hsh" id="hsh">';
echo '</form>';

echo '</div>';
echo '</div>';

echo '<ul class="nav nav-tabs" id="nav-tabs">';
echo '<li class="active"><a href="#" class="bg-white" data-filter="">Alle</a></li>';
echo '<li><a href="#" class="bg-white" data-filter="34a9">Geldig</a></li>';
echo '<li><a href="#" class="bg-danger" data-filter="09e9">Vervallen</a></li>';
echo '</ul>';

echo '<div class="table-responsive">';
echo '<table class="table table-hover table-striped table-bordered footable"';
echo ' data-filter="#combined-filter" data-filter-minimum="1">';
echo '<thead>';
echo '<tr>';
echo "<th>V/A</th>";
echo "<th>Wat</th>";
echo '<th data-hide="phone, tablet">Geldig tot</th>';
echo '<th data-hide="phone, tablet">Categorie</th>';
echo '<th data-hide="phone, tablet" data-sort-ignore="true">Verlengen</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach($msgs as $msg)
{
	$del = (strtotime($msg['validity']) < time()) ? true : false;

	echo '<tr';
	echo ($del) ? ' class="danger"' : '';
	echo '>';

	echo '<td';
	echo ' data-value="' . (($del) ? '09e9' : '34a9') . '">';
	echo ($msg['msg_type']) ? 'A' : 'V';
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
	echo '<a href="' . $rootpath . 'messages/overview.php?cid=' . $msg['cid'] . '">';
	echo htmlspecialchars($msg['cat'],ENT_QUOTES);
	echo '</a>';
	echo '</td>';

	echo '<td>';
	echo '<a href="mymsg_extend.php?id=' . $msg['id'] . '&validity=12" class="btn btn-default btn-xs">';
	echo '1 jaar</a>&nbsp;';
	echo '<a href="mymsg_extend.php?id=' . $msg['id'] . '&validity=60" class="btn btn-default btn-xs">';
	echo '5 jaar</a>';
	echo '</td>';
	
	echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>';


include $rootpath . 'includes/inc_footer.php';
