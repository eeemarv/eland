<?php
header("Content-Type:text/html;charset=utf-8");
echo '<!DOCTYPE html>';
echo '<html>';
echo '<head>';
echo '<title>' . readconfigfromdb("systemname") .'</title>';

echo "<link type='text/css' rel='stylesheet' href='".$rootpath."gfx/main.css'>";

echo "<link type='text/css' rel='stylesheet' href='".$rootpath."gfx/layout.css'>";
echo "<link type='text/css' rel='stylesheet' href='".$rootpath."gfx/menu.css'>";
echo "<link type='text/css' rel='stylesheet' href='".$rootpath."tinybox/tinybox.css'>";

// echo "<link type='text/css' rel='stylesheet' href='".$rootpath."gfx/alert.css'>";

echo '<link type="text/css" rel="stylesheet" href="' . $cdn_bootstrap_css . '">';
echo '<link type="text/css" rel="stylesheet" href="' . $cdn_fontawesome . '">';
echo '<link type="text/css" rel="stylesheet" href="' . $cdn_footable_css . '">';
echo '<link type="text/css" rel="stylesheet" href="' . $rootpath . 'gfx/base.css">';

echo "<script type='text/javascript' src='/tinybox/tinybox.js'></script>";

if (isset ($includecss))
{
	echo $includecss;
}

echo "<link rel='alternate' type='application/rss+xml' title='Messages RSS' href='$rootpath/rss.php?feed=messages' />";
echo "<link rel='alternate' type='application/rss+xml' title='News RSS' href='$rootpath/rss.php?feed=news' />";

echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '</head>';
echo '<body>';

//echo '<div class="page-container">';

?>
<script type='text/javascript'>
	function OpenTBox(url){
		TINY.box.show({url:url,width:0,height:0})
	}
</script>

<?php
$name = readconfigfromdb('systemname');

echo '<div class="navbar navbar-default navbar-fixed-top">';
echo '<div class="navbar-header">';
echo '<a class="navbar-brand" href="' . $rootpath . '">';
echo '<img class="img-responsive navbar-left hidden-xs" width="70" src="' . $rootpath . 'gfx/logo.png">';
echo $name . '</a>';
echo '</div>';
echo '</div>';

echo '<div class="row-offcanvas row-offcanvas-left">';
echo '<div id="sidebar" class="sidebar-offcanvas">';
// echo '<div class="col-md-12">';

$menu = array();

if (!$s_accountrole)
{
	$menu[] = array(
		'login.php'		=> 'Login',
		'help.php'		=> 'Help',
	);
}
else
{
	$menu[] = array(
		'ircchat.php'	=> 'Chatbox #letsbe',
	);

	$main_menu = array(
		'searchcat.php'		=> 'Vraag & Aanbod',
		'memberlist.php'	=> 'Contactlijst',
	);

	if ($s_accountrole == 'user' || $s_accountrole == 'admin')
	{
		$main_menu['transactions/alltrans.php'] = 'Transacties';
	}

	$main_menu['news/overview.php'] = 'Nieuws';

	if ($s_accountrole == 'user' || $s_accountrole == 'admin')
	{
		$main_menu['interlets/userview.php'] = 'Interlets';
	}

	$menu[] = $main_menu;

	$menu[] = array(
		'userdetails/mydetails.php'			=> 'Mijn gegevens',
		'userdetails/mymsg_overview.php'	=> 'Mijn Vraag & Aanbod',
		'userdetails/mytrans_overview.php'	=> 'Mijn transacties',
		'transactions/add.php'				=> 'Nieuwe transactie',
	);

	$menu[] = array(
		'help.php'	=> 'Probleem melden',
	);
}

foreach ($menu as $sub_menu)
{
	echo '<ul>';

	foreach ($sub_menu as $link => $label)
	{
		echo '<li><a href="' . $rootpath . $link . '">' . $label . '</a></li>';
	}

	echo '</ul>';
}

if ($s_accountrole == 'admin')
{
	echo '<ul class="admin">';

	$menu = array(
		'users/overview.php?user_orderby=letscode'	=> 'Gebruikers',
		'categories/overview.php'	 				=> 'Categorieën',
		'interlets/overview.php'					=> 'LETS Groepen',
		'apikeys/overview.php'						=> 'Apikeys',
		'type_contact/overview.php'					=> 'Contacttypes',
		'messages/overview.php'						=> 'Vraag & Aanbod',
		'reports/overview.php'						=> 'Rapporten',
		'preferences/config.php'					=> 'Instellingen',
		'importexport.php'							=> 'Import/Export',
		'eventlog.php'								=> 'Logs',
		'transactions/many_to_one.php'				=> 'Massa-Transactie',
	);

	foreach ($menu as $link => $label)
	{
		echo '<li><a href="' . $rootpath . $link . '">' . $label . '</a></li>';
	}

	echo '</ul>';
}

echo '</div>';

$class_admin = ($role == 'admin') ? ' class="admin"' : '';

echo '<div id="main"' . $class_admin . '>';
echo '<div class="col-md-12">';
echo '<div class="visible-xs pull-left button-offcanvas">';
echo '<button type="button" class="btn btn-primary btn-md " data-toggle="offcanvas"><i class="glyphicon glyphicon-chevron-left"></i></button>';
echo '</div>';

echo '<div>';
$alert->render();
echo '</div>';
