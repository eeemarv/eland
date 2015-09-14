<?php
ob_start();
$rootpath = '../';
$role = 'user';
require_once $rootpath . 'includes/inc_default.php';

$user = readuser($s_id);

$contacts = $db->fetchAll('select c.*, tc.abbrev
	from contact c, type_contact tc
	where c.id_type_contact = tc.id
		and c.id_user = ?
	order by c.id', array($s_id));

$currency = readconfigfromdb('currency');

$includejs = '<script type="text/javascript">var user_id = ' . $s_id . ';
	var user_link_location = \'' . $rootpath . 'memberlist_view.php?id=\'; </script>
	<script src="' . $cdn_jqplot . 'jquery.jqplot.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.donutRenderer.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.cursor.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.dateAxisRenderer.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.canvasTextRenderer.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.canvasAxisTickRenderer.min.js"></script>
	<script src="' . $cdn_jqplot . 'plugins/jqplot.highlighter.min.js"></script>
	<script src="' . $rootpath . 'js/plot_user_transactions.js"></script>';

$top_buttons = '<a href="' . $rootpath . 'userdetails/mydetails_edit.php" class="btn btn-primary"';
$top_buttons .= ' title="Mijn gegevens aanpassen"><i class="fa fa-pencil"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Aanpassen</span></a>';

$top_buttons .= '<a href="' . $rootpath . 'userdetails/mydetails_pw.php" class="btn btn-info"';
$top_buttons .= ' title="Mijn paswoord aanpassen"><i class="fa fa-key"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Paswoord aanpassen</span></a>';

$top_buttons .= '<a href="' . $rootpath . 'userdetails/mymsg_overview.php" class="btn btn-default"';
$top_buttons .= ' title="Mijn vraag en aanbod"><i class="fa fa-newspaper-o"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Mijn vraag en aanbod</span></a>';

$top_buttons .= '<a href="' . $rootpath . 'userdetails/mytrans_overview.php" class="btn btn-default"';
$top_buttons .= ' title="Mijn transacties"><i class="fa fa-exchange"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Mijn transacties</span></a>';

$top_buttons .= '<a href="' . $rootpath . 'memberlist.php" class="btn btn-default"';
$top_buttons .= ' title="Lijst"><i class="fa fa-users"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Lijst</span></a>';

$includecss = '<link rel="stylesheet" type="text/css" href="' . $cdn_jqplot . 'jquery.jqplot.min.css" />';

$h1 = 'Mijn gegevens';
$fa = 'user';

include $rootpath . 'includes/inc_header.php';

echo '<div class="row">';
echo '<div class="col-md-4">';

if(isset($user['PictureFile']))
{
	echo '<img class="img-rounded" src="https://s3.eu-central-1.amazonaws.com/' . getenv('S3_BUCKET') . '/' . $user['PictureFile'] . '" width="250"></img>';

	$btn_del_img = '<a href="' . $rootpath . 'userdetails/remove_picture.php" class="btn btn-danger" ';
	$btn_del_img .= 'title="foto verwijderen">';
	$btn_del_img .= '<span class="fa fa-times"></span><span class="hidden-xs hidden-sm hidden-md">';
	$btn_del_img .= 'Foto verwijderen</span></a>';
	$ra = 'vervangen';
}
else
{
	echo '<i class="fa fa-user fa-5x text-muted"></i><br>Geen profielfoto';
	$btn_del_img = '';
	$ra = 'toevoegen';
}

$myurl = $rootpath . 'userdetails/upload_picture.php';
$btn_add_img = "<script type='text/javascript'>function AddPic () { OpenTBox('" . $myurl ."'); } </script>";
$btn_add_img .= '<a href="javascript: AddPic()" class="btn btn-success" title="Foto ' . $ra . '">';
$btn_add_img .= '<i class="fa fa-plus"></i>';
$btn_add_img .= '<span class="hidden-xs hidden-sm hiddin-md"> Foto ' . $ra . '</span></a>';

echo '<br><p>' . $btn_add_img . '&nbsp;' . $btn_del_img . '</p>';

echo '</div>';
echo '<div class="col-md-8">';

echo '<dl>';

echo '<dt>';
echo 'Letscode';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user['letscode'],ENT_QUOTES);
echo '</dd>';

echo '<dt>';
echo 'Naam';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user['name'],ENT_QUOTES);
echo '</dd>';

echo '<dt>';
echo 'Volledige naam';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user['fullname'],ENT_QUOTES);
echo '</dd>';

echo '<dt>';
echo 'Postcode';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user['postcode'],ENT_QUOTES);
echo '</dd>';

echo '<dt>';
echo 'Geboortedatum';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user['birthday'],ENT_QUOTES);
echo '</dd>';

echo '<dt>';
echo 'Hobbies / Interesses';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user['hobbies'],ENT_QUOTES);
echo '</dd>';

echo '<dt>';
echo 'Commentaar';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user['comments'],ENT_QUOTES);
echo '</dd>';

echo '<dt>';
echo 'Login';
echo '</dt>';
echo '<dd>';
echo htmlspecialchars($user['login'],ENT_QUOTES);
echo '</dd>';

echo '<dt>';
echo 'Saldo, limiet min, limiet max';
echo ' (' . $currency . ')';
echo '</dt>';
echo '<dd>';
echo '<span class="label label-default">' . $user['saldo'] . '</span>&nbsp;';
echo '<span class="label label-danger">' . $user['minlimit'] . '</span>&nbsp;';
echo '<span class="label label-success">' . $user['maxlimit'] . '</span>';
echo '</dd>';

echo '<dt>';
echo 'Periodieke Saldo mail met recent vraag en aanbod';
echo '</dt>';
echo '<dd>';
echo ($user['cron_saldo'] == 't') ? 'Aan' : 'Uit';
echo '</dd>';
echo '</dl>';

echo '</div></div>';

echo '<div class="row">';
echo '<div class="col-md-12">';
echo '<h3><i class="fa fa-map-marker"></i> Contactinfo ';
echo '<a href="' . $rootpath . 'userdetails/mydetails_cont_add.php"';
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
	$a = '<a href="' . $rootpath . 'userdetails/mydetails_cont_edit.php?id=' . $c['id'];
	$a .= '">';
	echo '<tr>';
	echo '<td>' . $a . $c['abbrev'] . '</a></td>';
	echo '<td>' . $a . htmlspecialchars($c['value'],ENT_QUOTES) . '</a></td>';
	echo '<td>' . $a . htmlspecialchars($c['comments'],ENT_QUOTES) . '</a></td>';
	echo '<td>' . $a . (($c['flag_public'] == 1) ? 'Ja' : 'Nee') . '</a></td>';
	echo '<td><a href="' . $rootpath . 'userdetails/mydetails_cont_delete.php?id=' . $c['id'];
	echo '" class="btn btn-danger btn-xs"><i class="fa fa-times"></i>';
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
