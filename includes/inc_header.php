<?php

$include_js = $include_css = [];

foreach ($include_ary as $include)
{
	if (isset($asset_ary[$include]))
	{
		if (isset($asset_ary[$include]['js']))
		{
			if (is_array($asset_ary[$include]['js']))
			{
				foreach ($asset_ary[$include]['js'] as $js)
				{
					$include_js[] = $js;
				}
			}
			else
			{
				$include_js[] = $asset_ary[$include]['js'];
			}
		}

		if (isset($asset_ary[$include]['css']))
		{
			if (is_array($asset_ary[$include]['css']))
			{
				foreach ($asset_ary[$include]['css'] as $css)
				{
					$include_css[] = $css;
				}
			}
			else
			{
				$include_css[] = $asset_ary[$include]['css'];
			}
		}

		continue;
	}

	$ext = strtolower(pathinfo($include, PATHINFO_EXTENSION));

	if ($ext == 'js')
	{
		$include_js[] = $rootpath . 'js/' . $include;
	}
	else if ($ext == 'css')
	{
		$include_css[] = $rootpath . 'gfx/' . $include;
	}
}

if ($css = readconfigfromdb('css'))
{
	$include_css[] = $css;
} 

echo '<!DOCTYPE html>';
echo '<html>';
echo '<head>';
echo '<title>' . $systemname .'</title>';

foreach ($include_css as $css)
{
	echo '<link type="text/css" rel="stylesheet" href="' . $css . '" media="screen">';
}

echo '<link type="text/css" rel="stylesheet" href="' . $rootpath . 'gfx/print.css" media="print">';

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

echo '<div class="collapse navbar-collapse" id="navbar-collapse-1">';
echo '<ul class="nav navbar-nav navbar-right">';

$count_interlets_groups = count($eland_interlets_groups) + count($elas_interlets_groups);

if (!$s_anonymous && ($count_interlets_groups + count($logins)) > 1)
{
	echo '<li class="dropdown">';
	echo '<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">';
	echo '<span class="fa fa-share-alt"></span> ';
	echo 'Groep';
	echo '<span class="caret"></span></a>'; 
	echo '<ul class="dropdown-menu" role="menu">';

	foreach ($logins as $login_schema => $login_id)
	{
		$class = ($s_schema == $login_schema && count($logins) > 1) ? ' class="active-group"' : '';
		$class = ($login_schema == $schema && $login_schema == $s_schema) ? ' class="active"' : $class;

		echo '<li';
		echo $class;
		echo '>';
		
		echo '<a href="';
		echo $app_protocol . $hosts[$login_schema] . '/' . $script_name . '.php?r=';
		echo ($login_id == 'elas') ? 'guest' : $_SESSION['roles'][$login_schema];
		echo '&u=' . $login_id;
		echo '">';

		echo readconfigfromdb('systemname', $login_schema);
		echo ($login_id == 'elas') ? ' (eLAS gast login)' : ' (eigen groep)';
		echo '</a>';
		echo '</li>';

	}

	if ($count_interlets_groups)
	{
		echo '<li class="divider"></li>';

		if (count($eland_interlets_groups))
		{
			foreach ($eland_interlets_groups as $sch => $h)
			{
				echo '<li';
				echo ($schema == $sch) ? ' class="active"' : '';
				echo '>';

				$page = (isset($allowed_interlets_landing_pages[$script_name])) ? $script_name : 'index';

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
	}

	echo '</ul>';
	echo '</li>';
}

if (!$s_anonymous)
{
	echo '<li class="dropdown">';
	echo '<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">';
	echo '<span class="fa fa-user"></span> ';

	if ($s_master)
	{
		echo 'Master';
	}
	else if ($s_elas_guest)
	{
		echo 'eLAS gast login';
	}
	else if ($s_schema && $s_id)
	{
		echo ($s_group_self) ? '' : $s_schema . '.';
		echo link_user($s_id, $s_schema, false);
	}

	echo '<span class="caret"></span></a>'; 
	echo '<ul class="dropdown-menu" role="menu">';
	if ($s_schema && !$s_master && !$s_guest)
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
			'logs'							=> ['history', 'Logs'],
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
		$user_url = ($page_access == 'admin') ? 'index.php' : parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		$get_params = $_GET;
		$get_params['r'] = 'user';
		echo '<li>';
		echo '<a href="' . $user_url . '?' . http_build_query($get_params) . '"><i class="fa fa-times"></i>';
		echo ' Admin modus uit</a>';
		echo '</li>';
		echo '</ul>';
		echo '</li>';
	}
	else if ($s_group_self && ((isset($session_user) && $session_user['accountrole'] == 'admin') || $s_master))
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

}

echo '</ul>';
echo '</div>';

echo '</div>';
echo '</div>';

echo '<div class="row-offcanvas row-offcanvas-left">';
echo '<div id="sidebar" class="sidebar-offcanvas">';

if ($s_anonymous)
{
	$menu = [
		'login'			=> ['sign-in', 'Login', []],
	];

	if (readconfigfromdb('contact_form_en'))
	{
		$menu['contact'] = ['comment-o', 'Contact', []];
	}

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
		$menu['support'] = ['ambulance', 'Probleem melden', []];
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
