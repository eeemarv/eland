<?php
ob_start();
$rootpath = './';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';

$id = ($_GET['id']) ?: false;
$del = ($_GET['del']) ?: false;
$edit = ($_GET['edit']) ?: false;
$add = ($_GET['add']) ?: false;

$inline = ($_GET['inline']) ? true : false;
$uid = ($_GET['uid']) ?: false;

$q = ($_GET['q']) ?: '';
$hsh = ($_GET['hsh']) ?: '';
$cid = ($_GET['cid']) ?: '';
$cat_hsh = ($_GET['cat_hsh']) ?: '';

$extend = ($_GET['extend']) ?: false;

$submit = ($_POST['zend']) ? true : false;

if ($id)
{

}

$s_owner = ($s_id == $uid && $s_id && $uid) ? true : false;

$sql_and_where = ($uid) ? ' and u.id = ? ' : '';
$sql_params = ($uid) ? array($uid) : array();

$msgs = $db->fetchAll('select m.*,
		u.postcode
	from messages m, users u
	where m.id_user = u.id
		and u.status in (1, 2)
		' . $sql_and_where . '
	order by id desc', $sql_params);

$offer_sum = $want_sum = 0;

$cats = $cats_hsh = array();

$cats_hsh_name = array(
	''	=> '-- Alle categorieën --',
);

if ($uid)
{
	$st = $db->executeQuery('select c.*
		from categories c, messages m
		where m.id_category = c.id
			and m.id_user = ?
		order by c.fullname', array($uid));
}
else
{
	$st = $db->executeQuery('select * from categories order by fullname');
}

$ow_str = ' . . . . . . . V%1$s A%2$s';

while ($row = $st->fetch())
{
	$cats[$row['id']] = $row;	
	$c_hsh = substr(md5($row['id'] . $row['fullname']), 0, 4);
	if ($row['id_parent'])
	{
		$id_parent = $row['id_parent'];
		$cats_hsh_name[$c_hsh] = '. . . . . ' . $row['name'];
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

	$cats_hsh[$row['id']] = $c_hsh;	
}
$cats_hsh_name[$p_hsh] .= ($p_hsh) ? sprintf($ow_str, $want_sum, $offer_sum) : '';

$cat_hsh = ($cat_hsh) ?: (($cats_hsh[$cid]) ?: '');

if ($s_admin || $s_user)
{
	if (!$inline)
	{
		$top_buttons .= '<a href="' . $rootpath . 'messages.php?add=1" class="btn btn-success"';
		$top_buttons .= ' title="Vraag of aanbod toevoegen"><i class="fa fa-plus"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';
	}

	if ($uid)
	{
		if ($s_admin)
		{
			$str = 'Vraag of aanbod voor ' . link_user($uid, null, false);
			$top_buttons .= '<a href="' . $rootpath . 'messages.php?add=1&uid=' . $uid . '" ';
			$top_buttons .= 'class="btn btn-success" ';
			$top_buttons .= 'title="' . $str . '">';
			$top_buttons .= '<i class="fa fa-plus"></i>';
			$top_buttons .= '<span class="hidden-xs hidden-sm"> ' . $str . '</span></a>';
		}

		if (!$inline)
		{
			$top_buttons .= '<a href="' . $rootpath . 'messages.php" class="btn btn-default"';
			$top_buttons .= ' title="Lijst alle vraag en aanbod"><i class="fa fa-newspaper-o"></i>';
			$top_buttons .= '<span class="hidden-xs hidden-sm"> Lijst</span></a>';
		}
	}
	else
	{
		$top_buttons .= '<a href="' . $rootpath . 'messages.php?uid=' . $s_id . '" class="btn btn-default"';
		$top_buttons .= ' title="Mijn vraag en aanbod"><i class="fa fa-newspaper-o"></i>';
		$top_buttons .= '<span class="hidden-xs hidden-sm"> Mijn vraag en aanbod</span></a>';
	}
}

if ($s_admin)
{
	$top_right .= '<a href="#" class="csv">';
	$top_right .= '<i class="fa fa-file"></i>';
	$top_right .= '&nbsp;csv</a>';
}

$h1 = 'Vraag & Aanbod';
$h1 .= ($uid) ? ' van ' . link_user($uid) : '';
$h1 = (!$s_admin && $s_owner) ? 'Mijn vraag en aanbod' : $h1;

$fa = 'newspaper-o';

if (!$inline)
{
	$includejs = '<script src="' . $rootpath . 'js/combined_filter_msgs.js"></script>
		<script src="' . $rootpath . 'js/msgs_sum.js"></script>
		<script src="' . $rootpath . 'js/csv.js"></script>';

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
	echo '<i class="fa fa-clone"></i>';
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

	echo '<div class="pull-right hidden-xs">';
	echo 'Totaal: <span id="total"></span>';
	echo '</div>';

	echo '<ul class="nav nav-tabs" id="nav-tabs">';
	echo '<li class="active"><a href="#" class="bg-white" data-filter="">Alle</a></li>';
	echo '<li><a href="#" class="bg-white" data-filter="34a9">Geldig</a></li>';
	echo '<li><a href="#" class="bg-danger" data-filter="09e9">Vervallen</a></li>';
	echo '</ul>';	
}
else
{
	echo '<div class="row">';
	echo '<div class="col-md-12">';

	echo '<h3><i class="fa fa-newspaper-o"></i> ' . $h1;
	echo '<span class="inline-buttons">' . $top_buttons . '</span>';
	echo '</h3>';
}

echo '<div class="table-responsive">';
echo '<table class="table table-hover table-striped table-bordered footable csv"';
echo ' data-filter="#combined-filter" data-filter-minimum="1" id="msgs">';
echo '<thead>';
echo '<tr>';
echo "<th>V/A</th>";
echo "<th>Wat</th>";
if (!$uid)
{
	echo '<th data-hide="phone, tablet">Wie</th>';
	echo '<th>Postcode</th>';
}
echo '<th data-hide="phone, tablet">Categorie</th>';
echo '<th data-hide="phone, tablet">Geldig tot</th>';

if ($s_admin)
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
	echo '<a href="' .$rootpath . 'messages.php?id=' . $msg['id']. '">';
	echo htmlspecialchars($msg['content'],ENT_QUOTES);
	echo '</a>';
	echo '</td>';

	if (!$uid)
	{
		echo '<td>';
		echo link_user($msg['id_user']);
		echo '</td>';

		echo '<td>';
		echo $msg['postcode'];
		echo '</td>';
	}

	echo '<td>';
	echo '<a href="' . $rootpath . 'messages.php?cid=' . $msg['id_category'] . '">';
	echo htmlspecialchars($cats[$msg['id_category']]['fullname'], ENT_QUOTES);
	echo '</a>';
	echo '</td>';

	echo '<td>';
	echo $msg['validity'];
	echo '</td>';

	if ($s_admin)
	{
		echo '<td>';
		echo '<a href="' . $rootpath . 'messages.php?extend=' . $msg['id'] . '&validity=12" class="btn btn-default btn-xs">';
		echo '1 jaar</a>&nbsp;';
		echo '<a href="' . $rootpath . 'messages.php?extend=' . $msg['id'] . '&validity=60" class="btn btn-default btn-xs">';
		echo '5 jaar</a>';
		echo '</td>';
	}

	echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>';

if ($inline)
{
	echo '</div></div>';
}
else
{
	include $rootpath . 'includes/inc_footer.php';
}

function cancel($id = null)
{
	global $rootpath;

	header('Location: ' . $rootpath . 'messages.php' . (($id) ? '?id=' . $id : ''));
	exit;
}

