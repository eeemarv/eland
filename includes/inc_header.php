<?php

$name = readconfigfromdb('systemname');

echo '<!DOCTYPE html>';
echo '<html>';
echo '<head>';
echo '<title>' . $name .'</title>';

echo '<link type="text/css" rel="stylesheet" href="' . $rootpath . 'tinybox/tinybox.css">';
echo '<link type="text/css" rel="stylesheet" href="' . $cdn_bootstrap_css . '">';
echo '<link type="text/css" rel="stylesheet" href="' . $cdn_fontawesome . '">';
echo '<link type="text/css" rel="stylesheet" href="' . $cdn_footable_css . '">';
echo '<link type="text/css" rel="stylesheet" href="' . $rootpath . 'gfx/base.css">';

echo '<script type="text/javascript" src="/tinybox/tinybox.js"></script>';

if (isset ($includecss))
{
	echo $includecss;
}

// echo "<link rel='alternate' type='application/rss+xml' title='Messages RSS' href='$rootpath/rss.php?feed=messages' />";
// echo "<link rel='alternate' type='application/rss+xml' title='News RSS' href='$rootpath/rss.php?feed=news' />";

echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '</head>';
echo '<body>';

?>
<script type='text/javascript'>
	function OpenTBox(url){
		TINY.box.show({url:url,width:0,height:0})
	}
</script>

<?php

echo '<div class="navbar navbar-default navbar-fixed-top">';
echo '<div class="container-fluid">';

echo '<div class="navbar-header">';
echo '<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar-collapse-1" aria-expanded="false">';
echo '<span class="sr-only">Toggle navigation</span>';
echo '<span class="icon-bar"></span>';
echo '<span class="icon-bar"></span>';
echo '<span class="icon-bar"></span>';
echo '</button>';

echo '<a class="navbar-brand" href="' . $rootpath . 'index.php">';
echo '<img class="img-responsive navbar-left hidden-xs hidden-sm" width="70" ';
echo 'src="' . $rootpath . 'gfx/logo-inv.png">';
echo $name . '</a>';
echo '</div>';

if ($s_letscode)
{
	echo '<div class="collapse navbar-collapse" id="navbar-collapse-1">';
	echo '<ul class="nav navbar-nav navbar-right">';
	echo '<li class="dropdown">';
	echo '<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">';
	echo '<span class="fa fa-user"></span> ';
//	echo '<span class="hidden-xs"> ';
	echo $s_letscode . ' ' . $s_name;
//	echo '</span>';
	echo '<span class="caret"></span></a>'; 
	echo '<ul class="dropdown-menu" role="menu">';
	if ($s_accountrole == 'user' || $s_accountrole == 'admin')
	{
		echo '<li><a href="' . $rootpath . 'userdetails/mydetails.php">Mijn gegevens</a></li>';
		echo '<li><a href="' . $rootpath . 'userdetails/mymsg_overview.php">Mijn vraag en aanbod</a></li>';
		echo '<li><a href="' . $rootpath . 'userdetails/mytrans_overview.php">Mijn transacties</a></li>';
		echo '<li class="divider"></li>';
	}
	echo '<li><a href="' . $rootpath . 'logout.php">Uitloggen</a></li>';
	echo '</ul>';
	echo '</li>';
	if ($s_accountrole == 'admin')
	{
		$menu = array(
			'users/overview.php'						=> 'Gebruikers',
			'categories/overview.php'	 				=> 'Categorieën',
			'interlets/overview.php'					=> 'LETS Groepen',
			'apikeys/overview.php'						=> 'Apikeys',
			'type_contact/overview.php'					=> 'Contact types',
			'messages/overview.php'						=> 'Vraag & Aanbod',
			'reports/overview.php'						=> 'Rapporten',
			'preferences/config.php'					=> 'Instellingen',
			'export.php'								=> 'Export',
	//		'bulk.php'									=> 'Bulk acties',
			'transactions/many_to_one.php'				=> 'Massa-Transactie',
			'eventlog.php'								=> 'Logs',
		);

		echo '<li class="dropdown">';
		echo '<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">';
		echo '<span class="fa fa-cog"></span> ';
//		echo '<span class="hidden-xs"> ';
		echo 'Admin';
//		echo '</span>';
		echo '<span class="caret"></span></a>'; 
		echo '<ul class="dropdown-menu" role="menu">';
		foreach ($menu as $link => $label)
		{
			echo '<li><a href="' . $rootpath . $link .'">' . $label . '</a></li>';
		}
		echo '</ul>';
		echo '</li>';

	}
	echo '</ul>';
	echo '</div>';
}

echo '</div>';
echo '</div>';

echo '<div class="row-offcanvas row-offcanvas-left">';
echo '<div id="sidebar" class="sidebar-offcanvas">';

$script_name = ltrim($_SERVER['SCRIPT_NAME'], '/');

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
	$main_menu = array(
		'index.php'			=> array('home', 'Overzicht'),
		'searchcat.php'		=> array('newspaper-o', 'Vraag & Aanbod'),
		'memberlist.php'	=> array('users', 'Contactlijst'),
	);

	if ($s_accountrole == 'user' || $s_accountrole == 'admin')
	{
		$main_menu['transactions/alltrans.php'] = array('exchange', 'Transacties');
		$main_menu['transactions/add.php'] = array('exchange', 'Nieuwe transactie');
	}

	$main_menu['news/overview.php'] = array('calendar-o', 'Nieuws');

	if ($s_accountrole == 'user' || $s_accountrole == 'admin')
	{
		$main_menu['interlets/userview.php'] = array('share-alt', 'Interlets');
	}

	$menu[] = $main_menu;

	$menu[] = array(
		'help.php'		=> array('ambulance', 'Probleem melden'),
		'ircchat.php'	=> array('comments', 'Chatbox #letsbe'),
	);
}

foreach ($menu as $sub_menu)
{
	echo '<ul class="nav nav-pills nav-stacked">';

	foreach ($sub_menu as $link => $label)
	{
		$active_class = ($script_name == $link) ? ' class="active"' : '';
		echo '<li' . $active_class . ' role="presentation">';
		echo '<a href="' . $rootpath . $link . '">';
		echo '<span class="fa fa-' . $label[0] . '"></span> ';
		echo  $label[1] . '</a></li>';
	}

	echo '</ul>';
}

echo '</div>';

$class_admin = ($role == 'admin') ? ' admin' : '';

echo '<div id="main" class="container-fluid' . $class_admin . '">';

$alert->render();

echo '<div class="row">';
echo '<div class="col-md-12 top-buttons">';
echo '<div class="visible-xs pull-left button-offcanvas">';
echo '<button type="button" class="btn btn-primary btn-md " data-toggle="offcanvas"';
echo ' title="Menu"><i class="glyphicon glyphicon-chevron-left"></i></button>';
echo '</div>';
echo (isset($top_buttons)) ? $top_buttons : '';
echo '</div>';
echo '</div>';

if (isset($top_right))
{
	echo '<div class="pull-right hidden-xs">';
	echo $top_right;
	echo '</div>';
}

if (isset($h1))
{
	echo '<h1>';
	echo ($role == 'admin') ? '<span class="label label-default">Admin</span> ' : '';
	echo (isset($fa)) ? '<i class="fa fa-' . $fa . '"></i> ' : '';
	echo $h1 . '</h1>';
	echo ($role == 'admin') ? '<p>&nbsp;</p>' : '';
}
