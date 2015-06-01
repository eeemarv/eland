<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

if (!isset($_GET["id"])){
	header('Location: overview.php');
}

$id = $_GET["id"];

$user = $db->GetRow('SELECT *
	FROM users
	WHERE id = '.$id);

$contacts = $db->GetArray('select c.*, tc.abbrev
	from contact c, type_contact tc
	where c.id_type_contact = tc.id
		and c.id_user = ' . $id);

$messages = $db->GetArray("SELECT *
	FROM messages
	where id_user = ".$id."
		and validity > now()
	order by cdate");

$transactions = $db->GetArray('select t.*,
		fu.name as from_username,
		tu.name as to_username,
		fu.letscode as from_letscode,
		tu.letscode as to_letscode
	from transactions t, users fu, users tu
	where (t.id_to = ' . $id . '
		or t.id_from = ' . $id . ')
		and t.id_to = tu.id
		and t.id_from = fu.id');

$currency = readconfigfromdb('currency');

$trans_en = ($db->GetOne('select id
	from transactions
	where id_to = ' . $id . '
		or id_from = ' . $id)) ? true : false;

$includejs = '<script type="text/javascript">var user_id = ' . $id . ';</script>
	<script src="' . $cdn_jquery . '"></script>
	<script src="' . $cdn_jqplot . 'jquery.jqplot.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.donutRenderer.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.cursor.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.dateAxisRenderer.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.canvasTextRenderer.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.canvasAxisTickRenderer.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.highlighter.min.js"></script>
	<script src="' . $rootpath . 'js/plot_user_transactions.js"></script>';

$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_jqplot . 'jquery.jqplot.min.css" />
	<link rel="stylesheet" type="text/css" href="' . $rootpath . 'gfx/tooltip.css" />';

$top_buttons = '<a href="edit.php?mode=new" class="btn btn-success"';
$top_buttons .= ' title="gebruiker toevoegen"><i class="fa fa-plus"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

$top_buttons .= '<a href="edit.php?mode=edit&id=' . $id . '" class="btn btn-primary"';
$top_buttons .= ' title="Gebruiker aanpassen"><i class="fa fa-pencil"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Aanpassen</span></a>';

$top_buttons .= '<a href="editpw.php?id='. $id . '" class="btn btn-info"';
$top_buttons .= ' title="Paswoord aanpassen"><i class="fa fa-key"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Paswoord aanpassen</span></a>';

$top_buttons .= '<a href="activate.php?id='. $id . '" class="btn btn-warning"';
$top_buttons .= ' title="Activeren"><i class="fa fa-check"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Activeren</span></a>';

if (!$trans_en)
{
	$top_buttons .= '<a href="delete.php?id=' . $id . '" class="btn btn-danger"';
	$top_buttons .= ' title="gebruiker verwijderen">';
	$top_buttons .= '<i class="fa fa-times"></i>';
	$top_buttons .= '<span class="hidden-xs hidden-sm"> Verwijderen</span></a>';
}

$h1 = $user['letscode'] . ' ' . $user['fullname'];
$fa = 'user';

include $rootpath . 'includes/inc_header.php';

echo '<div class="row">';
echo '<div class="col-md-4">';

if(isset($user["PictureFile"]))
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
echo 'Naam';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["name"],ENT_QUOTES);
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Volledige naam';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["fullname"],ENT_QUOTES);
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Postcode';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["postcode"],ENT_QUOTES);
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Geboortedatum';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["birthday"],ENT_QUOTES);
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Hobbies / Interesses';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["hobbies"],ENT_QUOTES);
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Commentaar';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["comments"],ENT_QUOTES);
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Login';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["login"],ENT_QUOTES);
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Tijdstip aanmaak';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["cdate"],ENT_QUOTES);
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Tijdstip activering';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["adate"],ENT_QUOTES);
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Laatste login';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["logdate"],ENT_QUOTES);
echo '</dd>';
echo '</dl>';

$status_ary = array(
	0	=> 'Gedesactiveerd',
	1	=> 'Actief',
	2	=> 'Uitstapper',
	3	=> 'Instapper', // not used
	4	=> 'Infopakket',
	5	=> 'Infoavond',
	6	=> 'Extern',
);

echo '<dl>';
echo '<dt>';
echo 'Rechten';
echo '</dt>';
echo '<dd>';
echo $status_ary[$user['status']];
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Commentaar van de admin';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user["admincomment"],ENT_QUOTES);
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Saldo, limiet min, limiet max';
echo '</dt>';
echo '<dd>';
echo '<span class="label label-default">' . $user['saldo'] . '</span>&nbsp;';
echo '<span class="label label-danger">' . $user['minlimit'] . '</span>&nbsp;';
echo '<span class="label label-success">' . $user['maxlimit'] . '</span>';
echo '</dd>';
echo '</dl>';

echo '<dl>';
echo '<dt>';
echo 'Periodieke Saldo mail met recent vraag en aanbod';
echo '</dt>';
echo '<dd>';
echo ($user["cron_saldo"] == 't') ? 'Aan' : 'Uit';
echo '</dd>';
echo '</dl>';

echo '</div></div>';

echo '<div class="row">';
echo '<div class="col-md-12">';
echo '<h3><i class="fa fa-map-marker"></i> Contacten ';
echo '<a href="' . $rootpath . 'users/cont_add.php?uid=' . $id . '"';
echo ' class="btn btn-success" title="Contact toevoegen">';
echo '<i class="fa fa-plus"></i><span class="hidden-xs"> Toevoegen</span></a>';
echo '</h3>';

echo '<div class="table-responsive">';
echo '<table class="table table-hover table-striped table-bordered footable">';

echo '<thead>';
echo '<tr>';
echo '<th>Type</th>';
echo '<th>Waarde</th>';
echo '<th data-hide="phone, tablet">Commentaar</th>';
echo '<th data-hide="phone, tablet">Publiek</th>';
echo '<th data-sort-ignore="true" data-hide="phone, tablet">Verwijderen</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach ($contacts as $c)
{
	$a = '<a href="' . $rootpath . 'users/cont_edit.php?cid=' . $c['id'];
	$a .= '&uid=' . $c['id_user'] . '">';
	echo '<tr>';
	echo '<td>' . $a . $c['abbrev'] . '</a></td>';
	echo '<td>' . $a . htmlspecialchars($c['value'],ENT_QUOTES) . '</a></td>';
	echo '<td>' . $a . htmlspecialchars($c['comment'],ENT_QUOTES) . '</a></td>';
	echo '<td>' . $a . (($c['flag_public'] == 1) ? 'Ja' : 'Nee') . '</a></td>';
	echo '<td><a href="' . $rootpath . 'users/cont_delete.php?cid='.$c['id'];
	echo '&uid=' . $c['id_user'] . '" class="btn btn-danger btn-xs"><i class="fa fa-times"></i>';
	echo ' Verwijderen</a></td>';
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

$includejs = '
<script>
jQuery(document).ready(function ($) {

	function scaleChart() {
		var parentWidth = $("#chartdiv1").parent().width();
		if (parentWidth) {
			$("#chartdiv1").css("width", parentWidth);
		}
		else
		{
			window.setTimeout(scaleChart, 30);
		}
	}
	scaleChart();
	$(window).bind("load", scaleChart);
	$(window).bind("resize", scaleChart);
	$(window).bind("orientationchange", scaleChart);
});
</script>
';

echo '<div class="row">';
echo '<div class="col-md-12">';
echo '<h3><i class="fa fa-newspaper-o"></i> Vraag en aanbod ';
echo '<a href="' . $rootpath . 'messages/edit.php?mode=new&uid=' . $id . '"';
echo ' class="btn btn-success" title="Vraag of aanbod toevoegen">';
echo '<i class="fa fa-plus"></i><span class="hidden-xs"> Toevoegen</span></a>';
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

echo '<div class="row">';
echo '<div class="col-md-12">';

echo '<h3><i class="fa fa-exchange"></i> Transacties ';
echo '<a href="' . $rootpath . 'transactions/add.php?uid=' . $id . '"';
echo ' class="btn btn-success" title="Transactie toevoegen">';
echo '<i class="fa fa-plus"></i><span class="hidden-xs"> Toevoegen</span></a> ';
echo '<a href="' . $rootpath . 'print_usertransacties.php?id=' . $id . '"';
echo ' class="btn btn-default" title="Print transactielijst">';
echo '<i class="fa fa-print"></i><span class="hidden-xs"> Print transactielijst</span></a> ';
echo '<a href="' . $rootpath . 'export_transactions.php?userid=' . $id . '"';
echo ' class="btn btn-default" title="csv export transacties">';
echo '<i class="fa fa-file"></i><span class="hidden-xs"> Export csv</span></a>';
echo '</h3>';

echo '<div class="table-responsive">';
echo '<table class="table table-hover table-striped table-bordered footable">';

echo '<thead>';
echo '<tr>';
echo '<th>Omschrijving</th>';
echo '<th>Bedrag</th>';
echo '<th data-hide="phone" data-sort-initial="descending">Tijdstip</th>';
echo '<th data-hide="phone, tablet">Uit/In</th>';
echo '<th data-hide="phone, tablet">Tegenpartij</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach($transactions as $t){

	echo '<tr>';
	echo '<td>';
	echo '<a href="' . $rootpath . 'transactions/view.php?id=' . $t['id'] . '">';
	echo htmlspecialchars($t['description'], ENT_QUOTES);
	echo '</a>';
	echo '</td>';
	
	echo '<td>';
	echo '<span class="text-';
	echo ($t['id_from'] == $id) ? 'danger">-' : 'success">';
	echo $t['amount'];
	echo '</span></td>';

	echo '<td>';
	echo $t['cdate'];
	echo '</td>';

	echo '<td>';
	echo ($t['id_from'] == $id) ? 'Uit' : 'In'; 
	echo '</td>';

	if ($t['id_from'] == $id)
	{
		if ($t['real_to'])
		{
			$other_user = htmlspecialchars($t['real_to'], ENT_QUOTES);
		}
		else
		{
			$other_user = '<a href="' . $rootpath . 'users/view.php?id=' . $t['id_to'] . '">';
			$other_user .= htmlspecialchars($t['to_letscode'] . ' ' . $t['to_username'], ENT_QUOTES);
			$other_user .= '</a>';
		}
	}
	else
	{
		if ($t['real_from'])
		{
			$other_user = htmlspecialchars($t['real_from'], ENT_QUOTES);
		}
		else
		{
			$other_user = '<a href="' . $rootpath . 'users/view.php?id=' . $t['id_from'] . '">';
			$other_user .= htmlspecialchars($t['from_letscode'] . ' ' . $t['from_username'], ENT_QUOTES);
			$other_user .= '</a>';
		}
	}

	echo '<td>';
	echo $other_user;
	echo '</td>';

	echo '</tr>';
}

echo '</tbody>';
echo '</table>';

echo '</div>';
echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';
