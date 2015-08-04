<?php
ob_start();
$rootpath = '';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';

if (!isset($_GET['id']))
{
	header('Location: ' . $rootpath . 'memberlist.php');
}

$id = $_GET["id"];

$includejs = '<script type="text/javascript">var user_id = ' . $id . ';
	var user_link_location = \'' . $rootpath . 'memberlist_view.php?id=\'; </script>
	<script src="' . $cdn_jquery . '"></script>
	<script src="' . $cdn_jqplot . 'jquery.jqplot.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.donutRenderer.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.cursor.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.dateAxisRenderer.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.canvasTextRenderer.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.canvasAxisTickRenderer.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.highlighter.min.js"></script>
	<script src="' . $rootpath . 'js/plot_user_transactions.js"></script>';

$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_jqplot . 'jquery.jqplot.min.css" />';

$currency = readconfigfromdb('currency');

$user = readuser($id);

$contacts = $db->GetArray('select c.*, tc.abbrev
	from contact c, type_contact tc
	where c.id_type_contact = tc.id
		and c.id_user = ' . $id . '
		and c.flag_public = 1');

$messages = $db->GetArray("SELECT *
	FROM messages
	where id_user = ".$id."
		and validity > now()
	order by cdate");

if (in_array($s_accountrole, array('admin', 'user')))
{
	$top_buttons .= '<a href="' . $rootpath . 'transactions/add.php?uid=' . $id . '" class="btn btn-warning"';
	$top_buttons .= ' title="Transactie naar ' . $user['letscode'] . ' ' . $user['fullname'] . '">';
	$top_buttons .= '<i class="fa fa-exchange"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Transactie</span></a>';
}
if (in_array($s_accountrole, array('admin', 'user')))
{
	$top_buttons .= '<a href="' . $rootpath . 'memberlist.php" class="btn btn-default"';
	$top_buttons .= ' title="Lijst"><i class="fa fa-users"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Lijst</span></a>';
}
if ($s_accountrole == 'admin')
{
	$top_buttons .= '<a href="' . $rootpath . 'users/view.php?id=' . $id . '" class="btn btn-default"';
	$top_buttons .= ' title="Beheer"><i class="fa fa-cog"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Admin</span></a>';
}

$h1 = $user['letscode'] . ' ' . htmlspecialchars($user['name'], ENT_QUOTES);
$fa = 'user';

include $rootpath . 'includes/inc_header.php';

echo '<div class="row">';
echo '<div class="col-md-4">';

if(isset($user['PictureFile']))
{
	echo '<img class="img-rounded" src="https://s3.eu-central-1.amazonaws.com/' . getenv('S3_BUCKET') . '/' . $user['PictureFile'] . '" width="250"></img>';
}
else
{
	echo '<i class="fa fa-user fa-5x text-muted"></i><br>Geen profielfoto';
}

echo '</div>';
echo '<div class="col-md-8">';

echo '<dl>';
echo '<dt>';
echo 'Volledige naam';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["fullname"],ENT_QUOTES);
echo '</dd>';

echo '<dt>';
echo 'Postcode';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["postcode"],ENT_QUOTES);
echo '</dd>';

echo '<dt>';
echo 'Geboortedatum';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["birthday"],ENT_QUOTES);
echo '</dd>';

echo '<dt>';
echo 'Hobbies / Interesses';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["hobbies"],ENT_QUOTES);
echo '</dd>';

echo '<dt>';
echo 'Commentaar';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["comments"],ENT_QUOTES);
echo '</dd>';

$status_ary = array(
	0	=> 'Gedesactiveerd',
	1	=> 'Actief',
	2	=> 'Uitstapper',
	3	=> 'Instapper', // not used
	4	=> 'Infopakket',
	5	=> 'Infoavond',
	6	=> 'Extern',
);

echo '<dt>';
echo 'Rechten';
echo '</dt>';
echo '<dd>';
echo $status_ary[$user['status']];
echo '</dd>';

echo '<dt>';
echo 'Saldo, limiet min, limiet max';
echo '</dt>';
echo '<dd>';
echo '<span class="label label-default">' . $user['saldo'] . '</span>&nbsp;';
echo '<span class="label label-danger">' . $user['minlimit'] . '</span>&nbsp;';
echo '<span class="label label-success">' . $user['maxlimit'] . '</span>';
echo '</dd>';
echo '</dl>';

echo '</div></div>';

echo '<div class="row">';
echo '<div class="col-md-12">';
echo '<h3><i class="fa fa-map-marker"></i> Contactinfo';
echo '</h3>';

echo '<div class="table-responsive">';
echo '<table class="table table-hover table-striped table-bordered footable">';

echo '<thead>';
echo '<tr>';
echo '<th>Type</th>';
echo '<th>Waarde</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach ($contacts as $c)
{
	echo '<tr>';
	echo '<td>' . $c['abbrev'] . '</td>';
	echo '<td>' . htmlspecialchars($c['value'],ENT_QUOTES) . '</td>';
	echo '</tr>';
}

echo '</tbody>';

echo '</table>';
echo '</div>';

echo '</div></div>';

echo '<div class="row">';
echo '<div class="col-md-12">';
echo '<h3>Saldo: <span class="label label-default">' . $user['saldo'] . '</span> ';
echo $currency . '</h3>';
echo '</div></div>';

echo '<div class="row">';
echo '<div class="col-md-6">';
echo '<div id="chartdiv1" data-height="480px" data-width="960px"></div>';
echo '</div>';
echo '<div class="col-md-6">';
echo '<div id="chartdiv2" data-height="480px" data-width="960px"></div>';
echo '<h4>Interacties laatste jaar</h4>';
echo '</div>';
echo '</div>';

echo '<div class="row">';
echo '<div class="col-md-12">';
echo '<h3><i class="fa fa-newspaper-o"></i> Vraag en aanbod';
echo '</h3>';

echo '<div class="table-responsive">';
echo '<table class="table table-hover table-striped table-bordered footable">';

echo '<thead>';
echo '<tr>';
echo '<th>V/A</th>';
echo '<th>Wat</th>';
echo '<th data-hide="phone, tablet">Geldig tot</th>';
echo '<th data-hide="phone, tablet">Geplaatst</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach ($messages as $m)
{
	$class = (strtotime($m['validity']) < time()) ? ' class="danger"' : '';
	list($validity) = explode(' ', $m['validity']);
	list($cdate) = explode(' ', $m['cdate']);
	
	echo '<tr' . $class . '>';
	echo '<td>';
	echo ($m['msg_type']) ? 'Aanbod' : 'Vraag';
	echo '</td>';
	echo '<td>';
	echo '<a href="' . $rootpath . 'messages/view.php?id=' . $m['id'] . '">';
	echo htmlspecialchars($m['content'],ENT_QUOTES);
	echo '</a>';
	echo '</td>';
	echo '<td>';
	echo $validity;
	echo '</td>';
	echo '<td>';
	echo $cdate;
	echo '</td>';
	echo '</tr>';
}
echo '</tbody>';
echo '</table>';

echo '</div>';
echo '</div></div>';
echo '</div></div>';

include $rootpath . 'includes/inc_footer.php';
