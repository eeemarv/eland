<?php

echo '<!DOCTYPE html>';
echo '<html>';
echo '<head>';
echo '<title>' . $systemname .'</title>';

echo '<link type="text/css" rel="stylesheet" href="' . $cdn_bootstrap_css . '" media="screen">';
echo '<link type="text/css" rel="stylesheet" href="' . $cdn_fontawesome . '" media="screen">';
echo '<link type="text/css" rel="stylesheet" href="' . $cdn_footable_css . '" media="screen">';
echo '<link type="text/css" rel="stylesheet" href="' . $rootpath . 'gfx/base.css" media="screen">';
echo '<link type="text/css" rel="stylesheet" href="' . $rootpath . 'gfx/print.css" media="print">';

if (isset ($includecss))
{
	echo $includecss;
}

if ($css = readconfigfromdb('css'))
{
	echo '<link type="text/css" rel="stylesheet" href="' . $css . '" media="screen">';
} 

echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '</head>';
echo '<body>';

echo '<div class="navbar navbar-default navbar-fixed-top">';
echo '<div class="container-fluid">';

echo '<div class="navbar-header">';

if (!$s_anonymous)
{
	echo '<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar-collapse-1" aria-expanded="false">';
	echo '<span class="sr-only">Toggle navigation</span>';
	echo '<span class="icon-bar"></span>';
	echo '<span class="icon-bar"></span>';
	echo '<span class="icon-bar"></span>';
	echo '</button>';
}

echo aphp('index', '', array('<div class="logo"></div>'), 'pull-left hidden-xs');
echo aphp('index', '', $systemname, 'navbar-brand');

echo '</div>';

if (!$s_anonymous)
{
	echo '<div class="collapse navbar-collapse" id="navbar-collapse-1">';
	echo '<ul class="nav navbar-nav navbar-right">';
	echo '<li class="dropdown">';
	echo '<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">';
	echo '<span class="fa fa-user"></span> ';
	echo ($s_group_self) ? '' : $s_schema . '.';
	echo ($s_master) ? 'master' : link_user($s_id, $s_schema, false);
	echo '<span class="caret"></span></a>'; 
	echo '<ul class="dropdown-menu" role="menu">';
	if ($s_user || $s_admin)
	{
		echo '<li>' . aphp('users', 'id=' . $s_id, 'Mijn gegevens', false, false, 'user') . '</li>';
		echo '<li>' . aphp('messages', 'uid=' . $s_id, 'Mijn vraag en aanbod', false, false, 'newspaper-o') . '</li>';
		echo '<li>' . aphp('transactions', 'uid=' . $s_id, 'Mijn transacties', false, false, 'exchange') . '</li>';
		echo '<li class="divider"></li>';
	}
	echo '<li>' . aphp('logout', '', 'Uitloggen', '', '', 'sign-out') . '</li>';
	echo '</ul>';
	echo '</li>';
	if ($s_admin)
	{
		$menu = array(
			'categories'	 				=> array('clone', 'Categorieën'),
			'apikeys'						=> array('key', 'Apikeys'),
			'type_contact'					=> array('circle-o-notch', 'Contact types'),
			'contacts'						=> array('map-marker', 'Contacten'),
			'config'						=> array('gears', 'Instellingen'),
			'export'						=> array('download', 'Export'),
			'autominlimit'					=> array('arrows-v', 'Auto min limiet'),
			'mass_transaction'				=> array('exchange', 'Massa-Transactie'),
			'logs'							=> array('list', 'Logs'),
		);

		echo '<li class="dropdown">';
		echo '<a href="#" class="dropdown-toggle" data-toggle="dropdown" ';
		echo 'role="button" aria-expanded="false">';
		echo '<span class="fa fa-cog"></span> ';
		echo 'Admin modus';
		echo '<span class="caret"></span></a>'; 
		echo '<ul class="dropdown-menu" role="menu">';
		foreach ($menu as $link => $label)
		{
			$active = ($script_name == $link) ? ' class="active"' : '';
			echo '<li' . $active . '>';
			echo aphp($link, '', $label[1], false, false, $label[0]);
			echo '</li>';
		}
		echo '<li class="divider"></li>';
		$user_url = ($access_page == 0) ? 'index.php' : parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		$get_params = $_GET;
		$get_params['r'] = 'user';
		echo '<li>';
		echo '<a href="' . $user_url . '?' . http_build_query($get_params) . '"><i class="fa fa-times"></i>';
		echo ' Admin modus uit</a>';
		echo '</li>';
		echo '</ul>';
		echo '</li>';
	}
	else if ($_SESSION['accountrole'] == 'admin')
	{
		echo '<li class="dropdown">';
		$admin_url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		$get_params = $_GET;
		$get_params['r'] = 'admin';

		echo '<a href="' . $admin_url . '?' . http_build_query($get_params) . '" ';
		echo 'title="Admin modus inschakelen">';
		echo '<span class="fa fa-times text-danger"></span> ';
		echo 'Admin modus';
		echo '</a>'; 
	}
	echo '</ul>';
	echo '</div>';
}

echo '</div>';
echo '</div>';

echo '<div class="row-offcanvas row-offcanvas-left">';
echo '<div id="sidebar" class="sidebar-offcanvas">';

if ($s_anonymous)
{
	$menu = array(
		'login'		=> array('sign-in', 'Login'),
		'help'		=> array('ambulance', 'Help'),
	);

	if (readconfigfromdb('registration_en'))
	{
		$menu['register'] = array('check-square-o', 'Inschrijven');
	}
}
else
{
	$menu = array(
		'index'					=> array('home', 'Overzicht'),
		'messages'				=> array('newspaper-o', 'Vraag & Aanbod', 'view=' . $view_messages),
		'users'					=> array('users', (($s_admin) ? 'Gebruikers' : 'Leden'), 'status=active&view=' . $view_users),
		'transactions'			=> array('exchange', 'Transacties'),
		'news'					=> array('calendar-o', 'Nieuws', 'view=' . $view_news),
	);

	if ($s_user || $s_admin)
	{
		$menu['interlets'] = array('share-alt', 'InterLETS');
	}

	$menu['docs'] = array('files-o', 'Documenten');

	if (readconfigfromdb('forum_en'))
	{
		$menu['forum'] = array('comments-o', 'Forum');
	}

	if ($s_user || $s_admin)
	{
		$menu['help'] = array('ambulance', 'Probleem melden');
	}
}

echo '<br>';
echo '<ul class="nav nav-pills nav-stacked">';

foreach ($menu as $link => $label)
{
	$active = ($script_name == $link) ? ' class="active"' : '';
	echo '<li' . $active . '>';
	echo aphp($link, (isset($label[2])) ? $label[2] : '',
		$label[1], false, false, $label[0]);
	echo '</li>';
}
echo '</ul>';

echo '</div>';

$class_admin = ($role == 'admin') ? ' admin' : '';

echo '<div id="wrap">';
echo '<div id="main" class="container-fluid clear-top' . $class_admin . '">';

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
	echo ($role == 'admin' || $s_admin) ? '<small><span class="label label-info">Admin</span></small> ' : '';
	echo (isset($fa)) ? '<i class="fa fa-' . $fa . '"></i> ' : '';
	echo $h1 . '</h1>';
}
