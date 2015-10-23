<?php

$name = readconfigfromdb('systemname');
$script_name = ltrim($_SERVER['SCRIPT_NAME'], '/');

echo '<!DOCTYPE html>';
echo '<html>';
echo '<head>';
echo '<title>' . $name .'</title>';

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
echo '<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar-collapse-1" aria-expanded="false">';
echo '<span class="sr-only">Toggle navigation</span>';
echo '<span class="icon-bar"></span>';
echo '<span class="icon-bar"></span>';
echo '<span class="icon-bar"></span>';
echo '</button>';

echo '<a href="' . $rootpath . '" class="pull-left hidden-xs"><div class="logo"></div></a>';
echo '<a class="navbar-brand" href="' . $rootpath . '">';
echo $name . '</a>';

echo '</div>';

if ($s_letscode)
{
	echo '<div class="collapse navbar-collapse" id="navbar-collapse-1">';
	echo '<ul class="nav navbar-nav navbar-right">';
	echo '<li class="dropdown">';
	echo '<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">';
	echo '<span class="fa fa-user"></span> ';
	echo $s_letscode . ' ' . $s_name;
	echo '<span class="caret"></span></a>'; 
	echo '<ul class="dropdown-menu" role="menu">';
	if ($s_user || $s_admin)
	{
		echo '<li><a href="' . $rootpath . 'users.php?id=' . $s_id . '">';
		echo '<span class="fa fa-user"></span> Mijn gegevens</a></li>';
		echo '<li><a href="' . $rootpath . 'messages.php?uid=' . $s_id . '">';
		echo '<span class="fa fa-newspaper-o"></span> Mijn vraag en aanbod</a></li>';
		echo '<li><a href="' . $rootpath . 'transactions.php?uid=' . $s_id . '">';
		echo '<span class="fa fa-exchange"></span> Mijn transacties</a></li>';
		echo '<li class="divider"></li>';
	}
	echo '<li><a href="' . $rootpath . 'logout.php">';
	echo '<span class="fa fa-sign-out"></span> Uitloggen</a></li>';
	echo '</ul>';
	echo '</li>';
	if ($s_admin)
	{
		$menu = array(
			'categories.php'	 				=> array('clone', 'Categorieën'),
			'apikeys.php'						=> array('key', 'Apikeys'),
			'type_contact.php'					=> array('circle-o-notch', 'Contact types'),
			'reports/overview.php'				=> array('calculator', 'Rapporten'),
			'config.php'						=> array('gears', 'Instellingen'),
			'export.php'						=> array('download', 'Export'),
			'autominlimit.php'					=> array('arrows-v', 'Auto min limiet'),
			'mass_transaction.php'				=> array('exchange', 'Massa-Transactie'),
			'logs.php'							=> array('list', 'Logs'),
			'divider_1'							=> 'divider',
			'admin.php?location=' . urlencode($_SERVER['REQUEST_URI']) =>
													array('times text-danger', 'Admin modus uit'),
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
			if ($label == 'divider')
			{
				echo '<li class="divider"></li>';
				continue;
			}
			$active_class = ($script_name == $link) ? ' class="active"' : '';
			echo '<li' . $active_class . '><a href="' . $rootpath . $link .'">';
			echo '<span class="fa fa-' . $label[0] . '"></span> ' . $label[1] . '</a></li>';
		}
		echo '</ul>';
		echo '</li>';

	}
	else if ($_SESSION['rights'] == 'admin')
	{
		echo '<li class="dropdown">';
		echo '<a href="' . $rootpath . 'admin.php?location=' . urlencode($_SERVER['REQUEST_URI']) . '" ';
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

if (!$s_accountrole)
{
	$menu = array(
		'login.php'		=> array('sign-in', 'Login'),
		'help.php'		=> array('ambulance', 'Help'),
	);

	if (readconfigfromdb('registration_en'))
	{
		$menu['register.php'] = array('check-square-o', 'Inschrijven');
	}
}
else
{
	$menu = array(
		'index.php'					=> array('home', 'Overzicht'),
		'messages.php'				=> array('newspaper-o', 'Vraag & Aanbod'),
		'users.php'					=> array('users', (($s_admin) ? 'Gebruikers' : 'Leden')),
		'transactions.php'			=> array('exchange', 'Transacties'),
		'news.php'					=> array('calendar-o', 'Nieuws'),
	);

	if ($s_user || $s_admin)
	{
		$menu['interlets.php'] = array('share-alt', 'InterLETS');
	}

	if (readconfigfromdb('docs_en'))
	{
		$menu['docs.php'] = array('files-o', 'Documenten');
	}
	if (readconfigfromdb('forum_en'))
	{
		$menu['forum.php'] = array('comments-o', 'Forum');
	}

	if ($s_user || $s_admin)
	{
		$menu['help.php'] = array('ambulance', 'Probleem melden');
	}
}

echo '<br>';
echo '<ul class="nav nav-pills nav-stacked">';

foreach ($menu as $link => $label)
{
	$active_class = ($script_name == $link) ? ' class="active"' : '';
	echo '<li' . $active_class . ' role="presentation">';
	echo '<a href="' . $rootpath . $link . '">';
	echo '<span class="fa fa-' . $label[0] . '"></span> ';
	echo  $label[1] . '</a></li>';
}
echo '</ul>';

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
	echo ($role == 'admin' || $s_admin) ? '<small><span class="label label-info">Admin</span></small> ' : '';
	echo (isset($fa)) ? '<i class="fa fa-' . $fa . '"></i> ' : '';
	echo $h1 . '</h1>';
}
