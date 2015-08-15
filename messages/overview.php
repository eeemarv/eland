<?php
ob_start();
$rootpath = '../';
$role = 'user';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';
require_once $rootpath . 'includes/inc_form.php';

$q = ($_GET['q']) ?: '';
$hsh = ($_GET['hsh']) ?: '';
$cat_hsh = ($_GET['cat_hsh']) ?: '';

$msgs = $db->GetArray('select m.*,
		u.letscode, u.fullname, u.id as uid, u.postcode
	from messages m, users u
	where m.id_user = u.id
		and u.status in (1, 2)
	order by id desc');

$offer_sum = $want_sum = 0;

$cats = array();

$cats_hsh_name = array(
	''	=> '-- Alle categorieën --',
);

$rs = $db->Execute('SELECT * FROM categories ORDER BY fullname');

$ow_str = ' (vraag: %1$s - aanbod: %2$s)';

while ($row = $rs->FetchRow())
{
	$cats[$row['id']] = $row;	
	$c_hsh = substr(md5($row['id'] . $row['fullname']), 0, 4);
	if ($row['id_parent'])
	{
		$id_parent = $row['id_parent'];
		$cats_hsh_name[$c_hsh] = '........' . $row['name'];
		$offer = $row['stat_msgs_offers'];
		$want = $row['stat_msgs_wanted'];
		$cats_hsh_name[$c_hsh] .= sprintf($ow_str, $want, $offer);
		$offer_sum += $offer;
		$want_sum += $want;
		$cats[$row['id']]['hsh'] = $p_hsh . ' ' . $c_hsh;		
	}
	else
	{
		$cats_hsh_name[$p_hsh] .= ($p_hsh) ? sprintf($ow_str, $want_sum, $offer_sum) : ''; 
		$cats_hsh_name[$c_hsh] = $row['name'];
		$offer_sum = $want_sum = 0;
		$p_hsh = $c_hsh;
		$cats[$row['id']]['hsh'] = $c_hsh;
	}
}
$cats_hsh_name[$p_hsh] .= ($p_hsh) ? sprintf($ow_str, $want_sum, $offer_sum) : '';

$top_buttons = '<a href="' . $rootpath . 'messages/edit.php?mode=new" class="btn btn-success"';
$top_buttons .= ' title="Vraag of aanbod toevoegen"><i class="fa fa-plus"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

if ($s_accountrole == 'admin')
{
	$top_right = '<a href="' . $rootpath . 'export_messages.php';
	$top_right .= '" target="new">';
	$top_right .= '<i class="fa fa-file"></i>';
	$top_right .= '&nbsp;csv</a>';
}

$h1 = 'Vraag & Aanbod';
$fa = 'newspaper-o';

$includejs = '<script src="' . $rootpath . 'js/combined_filter_msgs.js"></script>';

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

echo '<div class="row">';
echo '<div class="col-sm-12">';
echo '<div class="input-group">';
echo '<div class="input-group-addon">';
echo '<i class="fa fa-files-o"></i>';
echo '</div>';
echo '<select class="form-control" id="cat_hsh" value="' . $cat_hsh . '" name="cat_hsh">';
render_select_options($cats_hsh_name, $cat_hsh);
echo '</select>';
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
echo '<th data-hide="phone, tablet">Wie</th>';
echo '<th>Postcode</th>';
echo '<th data-hide="phone, tablet">Categorie</th>';
echo '<th data-hide="phone, tablet">Geldig tot</th>';

if ($s_accountrole == 'admin')
{
	echo '<th data-hide="phone, tablet" data-sort-ignore="true">';
	echo '[Admin] Verlengen</th>';
}
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
	echo ' data-value="' . (($del) ? '09e9' : '34a9') . ' ' . $cats[$msg['id_category']]['hsh'] . '">';
	echo ($msg['msg_type']) ? 'Aanbod' : 'Vraag';
	echo '</td>';

	echo '<td>';
	echo '<a href="' .$rootpath . 'messages/view.php?id=' . $msg['id']. '">';
	echo htmlspecialchars($msg['content'],ENT_QUOTES);
	echo '</a>';
	echo '</td>';

	echo '<td>';
	echo '<a href="' . $rootpath . 'memberlist_view.php?id=' . $msg['uid'] . '">';
	echo htmlspecialchars($msg['letscode'] . ' ' . $msg['fullname'], ENT_QUOTES);
	echo '</a>';
	echo '</td>';

	echo '<td>';
	echo $msg['postcode'];
	echo '</td>';

	echo '<td>';
	echo '<a href="' . $rootpath . 'searchcat_viewcat.php?id=' . $msg['id_category'] . '">';
	echo htmlspecialchars($cats[$msg['id_category']]['fullname'], ENT_QUOTES);
	echo '</a>';
	echo '</td>';

	echo '<td>';
	echo $msg['validity'];
	echo '</td>';

	if ($s_accountrole == 'admin')
	{
		echo '<td>';
		echo '<a href="' . $rootpath . 'messages/extend.php?id=' . $msg['id'] . '&validity=12" class="btn btn-default btn-xs">';
		echo '1 jaar</a>&nbsp;';
		echo '<a href="' . $rootpath . 'messages/extend.php?id=' . $msg['id'] . '&validity=60" class="btn btn-default btn-xs">';
		echo '5 jaar</a>';
		echo '</td>';
	}

	echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';

