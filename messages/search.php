<?php
ob_start();
$rootpath = "../";
$role = 'guest';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if(isset($_GET['q']))
{
	$q = $_GET['q'];

	$msgs = $db->GetArray('select m.*,
		u.id as uid, u.letscode, u.fullname,
		c.id as cid, c.fullname as cat
		from messages m, users u, categories c
		where lower(content) like \'%' . $q . '%\'
			and m.id_user = u.id
			and u.status in (1, 2)
			and m.id_category = c.id');
}

if (in_array($s_accountrole, array('user', 'admin')))
{ 
	$top_buttons = '<a href="' . $rootpath . 'messages/edit.php?mode=new" class="btn btn-success"';
	$top_buttons .= ' title="Vraag of aanbod toevoegen"><i class="fa fa-plus"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';
}

$h1 = 'Zoek vraag en aanbod';
$fa = 'leanpub';

include $rootpath . 'includes/inc_header.php';

echo '<form method="get" action="' . $rootpath . 'messages/search.php">';
echo '<div class="col-lg-12">';
echo '<div class="input-group">';
echo '<span class="input-group-btn">';
echo '<button class="btn btn-default" type="submit"><i class="fa fa-search"></i> Zoeken</button>';
echo '</span>';
echo '<input type="text" class="form-control" name="q">';
echo '</div>';
echo '</div>';
echo '<br><small><i>Een leeg zoekveld geeft ALLE V/A als resultaat terug</i></small>';
echo '</form>';

echo '<div class="table-responsive">';
echo '<table class="table table-hover table-striped table-bordered footable">';
echo '<thead>';
echo '<tr>';
echo "<th>V/A</th>";
echo "<th>Wat</th>";
echo '<th data-hide="phone, tablet">Geldig tot</th>';
echo '<th data-hide="phone, tablet">Wie</th>';
echo '<th data-hide="phone, tablet">Categorie</th>';
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

	echo '<td>';
	echo '<a href="' . $rootpath . 'searchcat_viewcat.php?id=' . $msg['cid'] . '">';
	echo htmlspecialchars($msg['cat'],ENT_QUOTES);
	echo '</a>';
	echo '</td>';

	echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';
