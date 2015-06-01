<?php
ob_start();
$rootpath = "../";
$role = 'user';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

$msgs = $db->GetArray('select m.*,
		c.id as cid, c.fullname as cat
	from messages m, categories c
	where m.id_category = c.id
		and m.id_user = ' . $s_id . '
	order by id desc');

$top_buttons = '<a href="' . $rootpath . 'messages/edit.php?mode=new" class="btn btn-success"';
$top_buttons .= ' title="Vraag of aanbod toevoegen"><i class="fa fa-plus"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

include $rootpath . 'includes/inc_header.php';

echo '<h1><i class="fa fa-newspaper-o"></i> Mijn Vraag & Aanbod</h1>';

echo '<div class="table-responsive">';
echo '<table class="table table-hover table-striped table-bordered footable">';
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
	echo '<a href="' . $rootpath . 'searchcat_viewcat.php?id=' . $msg['cid'] . '">';
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
