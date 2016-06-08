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
echo '<body';

if ($s_schema)
{
	echo ' data-elas-group-login="' . generate_url('ajax/elas_group_login', []) . '"';
}

echo '>';

echo '<img src="/gfx/loading.gif" class="ajax-loader">';

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

echo '<a href="' . generate_url('index') . '" class="pull-left hidden-xs">';
echo '<div class="logo"></div>';
echo '</a>';

echo aphp('index', [], $systemname, 'navbar-brand');

echo '</div>';

if (!$s_anonymous)
{
	echo '<div class="collapse navbar-collapse" id="navbar-collapse-1">';
	echo '<ul class="nav navbar-nav navbar-right">';

	if ($s_schema && (count($eland_interlets_groups) || count($elas_interlets_groups)))
	{
		echo '<li class="dropdown">';
		echo '<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">';
		echo '<span class="fa fa-share-alt"></span> ';
		echo 'Groep';
		echo '<span class="caret"></span></a>'; 
		echo '<ul class="dropdown-menu" role="menu">';
		echo '<li';
		echo ($s_group_self) ? ' class="active"' : '';
		echo '>';
		echo '<a href="' . generate_url($script_name, [], $s_schema) . '">';
		echo readconfigfromdb('systemname', $s_schema) . ' (eigen groep)';
		echo '</a>';
		echo '</li>';
		echo '<li class="divider"></li>';

		if (count($eland_interlets_groups))
		{
			foreach ($eland_interlets_groups as $sch => $h)
			{
				echo '<li';
				echo ($schema == $sch) ? ' class="active"' : '';
				echo '>';

				$page = ($allowed_interlets_landing_pages[$script_name]) ? $script_name : 'index';

				echo '<a href="' . generate_url($page,  ['welcome' => 1], $sch) . '">';
				echo readconfigfromdb('systemname', $sch) . '</a>';
				echo '</li>';
			}
		}

		if (count($elas_interlets_groups))
		{
			foreach ($elas_interlets_groups as $grp_id => $grp)
			{
				echo '<li>';
				echo '<a href="#" data-elas-group-id="' . $grp_id . '">';
				echo $grp['groupname'] . '</a>';
				echo '</li>';
			}
		}

		echo '</ul>';
		echo '</li>';
	}

	echo '<li class="dropdown">';
	echo '<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">';
	echo '<span class="fa fa-user"></span> ';
	if ($s_schema)
	{
		echo ($s_group_self) ? '' : $s_schema . '.';
		echo (isset($s_master)) ? 'master' : link_user($s_id, $s_schema, false);
	}
	else
	{
		echo 'Gast login';
	}
	echo '<span class="caret"></span></a>'; 
	echo '<ul class="dropdown-menu" role="menu">';
	if ($s_schema)
	{
		echo '<li><a href="' . generate_url('users', ['id' => $s_id], $s_schema) . '">';
		echo '<i class="fa fa-user"></i> Mijn gegevens';
		echo '</a></li>';

		echo '<li><a href="' . generate_url('messages', ['uid' => $s_id], $s_schema) . '">';
		echo '<i class="fa fa-newspaper-o"></i> Mijn vraag en aanbod';
		echo '</a></li>';

		echo '<li><a href="' . generate_url('transactions', ['uid' => $s_id], $s_schema) . '">';
		echo '<i class="fa fa-exchange"></i> Mijn transacties';
		echo '</a></li>';

		echo '<li class="divider"></li>';
	}

	echo '<li><a href="' . generate_url('logout', [], $s_schema) . '">';
	echo '<i class="fa fa-sign-out"></i> Uitloggen';
	echo '</a></li>';

	echo '</ul>';
	echo '</li>';
	if ($s_admin)
	{
		$menu = [
			'categories'	 				=> ['clone', 'Categorieën'],
			'type_contact'					=> ['circle-o-notch', 'Contact types'],
			'contacts'						=> ['map-marker', 'Contacten'],
			'config'						=> ['gears', 'Instellingen'],
			'interlets'						=> ['share-alt', 'InterLETS'],
			'apikeys'						=> ['key', 'Apikeys'],
			'export'						=> ['download', 'Export'],
			'autominlimit'					=> ['arrows-v', 'Auto min limiet'],
			'mass_transaction'				=> ['exchange', 'Massa-Transactie'],
			'logs'							=> ['list', 'Logs'],
		];

		echo '<li class="dropdown">';
		echo '<a href="#" class="dropdown-toggle" data-toggle="dropdown" ';
		echo 'role="button" aria-expanded="false">';
		echo '<span class="fa fa-cog"></span> ';
		echo 'Admin modus';
		echo '<span class="caret"></span></a>'; 
		echo '<ul class="dropdown-menu" role="menu">';
		foreach ($menu as $link => $item)
		{
			$active = ($script_name == $link) ? ' class="active"' : '';
			echo '<li' . $active . '>';
			echo aphp($link, [], $item[1], false, false, $item[0]);
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
	else if ($s_group_self && $session_user['accountrole'] == 'admin')
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
	$menu = [
		'login'		=> ['sign-in', 'Login', []],
		'help'		=> ['ambulance', 'Help', []],
	];

	if (readconfigfromdb('registration_en'))
	{
		$menu['register'] = ['check-square-o', 'Inschrijven', []];
	}
}
else
{
	$menu = [
		'index'					=> ['home', 'Overzicht', []],
		'messages'				=> ['newspaper-o', 'Vraag & Aanbod', ['view' => $view_messages]],
		'users'					=> ['users', (($s_admin) ? 'Gebruikers' : 'Leden'), ['status' => 'active', 'view' => $view_users]],
		'transactions'			=> ['exchange', 'Transacties', []],
		'news'					=> ['calendar-o', 'Nieuws', ['view' => $view_news]],
	];

	$menu['docs'] = ['files-o', 'Documenten', []];

	if (readconfigfromdb('forum_en'))
	{
		$menu['forum'] = ['comments-o', 'Forum', []];
	}

	if ($s_user || $s_admin)
	{
		$menu['help'] = ['ambulance', 'Probleem melden', []];
	}
}

echo '<br>';
echo '<ul class="nav nav-pills nav-stacked">';

foreach ($menu as $link => $item)
{
	$active = ($script_name == $link) ? ' class="active"' : '';
	echo '<li' . $active . '>';
	echo aphp($link, $item[2],
		$item[1], false, false, $item[0]);
	echo '</li>';
}
echo '</ul>';

echo '</div>';

$class_admin = ($page_access == 'admin') ? ' admin' : '';

echo '<div id="wrap">';
echo '<div id="main" class="container-fluid clear-top' . $class_admin . '">';

$alert->render();

echo '<div class="row">';
echo '<div class="col-md-12 top-buttons">';
echo '<div class="visible-xs pull-left button-offcanvas">';
echo '<button type="button" class="btn btn-primary btn-md " data-toggle="offcanvas"';
echo ' title="Menu"><i class="fa fa-chevron-left"></i></button>';
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
	echo ($page_access == 'admin' || $s_admin) ? '<small><span class="label label-info">Admin</span></small> ' : '';
	echo (isset($fa)) ? '<i class="fa fa-' . $fa . '"></i> ' : '';
	echo $h1 . '</h1>';
}
