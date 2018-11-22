<?php

if ($css = $app['config']->get('css'))
{
	$app['assets']->add($css);
}

echo '<!DOCTYPE html>';
echo '<html lang="nl">';
echo '<head>';

echo '<title>';
echo $app['config']->get('systemname');
echo '</title>';

echo $app['assets']->get_css();

echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';

echo '<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">';
echo '<link rel="icon" type="image/png" href="/favicon-32x32.png" sizes="32x32">';
echo '<link rel="icon" type="image/png" href="/favicon-16x16.png" sizes="16x16">';
echo '<link rel="manifest" href="/manifest.json">';
echo '<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#d55b5b">';
echo '<meta name="theme-color" content="#ffffff">';

echo '</head>';
echo '<body data-session-params="';
echo htmlspecialchars(json_encode(get_session_query_param()));
echo '" class="';
echo $s_admin ? 'admin' : ($s_guest ? 'guest' : 'member');
echo '">';

echo '<img src="/gfx/loading.gif' . $app['assets']->get_version_param() . '" ';
echo 'class="ajax-loader" alt="waiting">';

echo '<div class="navbar navbar-default navbar-fixed-top';
echo $s_admin ? ' bg-info' : '';
echo $s_guest ? ' bg-warning' : '';
echo '">';
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

$homepage_url = $app['config']->get('homepage_url');

if (!$homepage_url)
{
	$homepage_url = get_default_page();
}

echo '<a href="';
echo $homepage_url;
echo '" class="pull-left hidden-xs">';
echo '<div class="logo"></div>';
echo '</a>';

echo '<a href="';
echo $homepage_url;
echo '" class="navbar-brand">';
echo $app['config']->get('systemname');
echo '</a>';
echo '</div>';

echo '<div class="collapse navbar-collapse" id="navbar-collapse-1">';
echo '<ul class="nav navbar-nav navbar-right">';

if (!$s_anonymous && ($count_interlets_groups + count($logins)) > 1)
{
	echo '<li class="dropdown">';
	echo '<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">';
	echo '<span class="fa fa-share-alt"></span> ';
	echo 'Systeem';
	echo '<span class="caret"></span></a>';
	echo '<ul class="dropdown-menu" role="menu">';
	echo '<li class="dropdown-header">';

	if (count($logins) === 1 && current($logins) === 'eLAS')
	{
		echo 'eLAS Gast Login';
	}
	else
	{
		echo count($logins) > 1 ? 'Eigen Systemen' : 'Eigen Systeem';
	}

	echo '</li>';

	foreach ($logins as $login_schema => $login_id)
	{
		$class = ($s_schema == $login_schema && count($logins) > 1) ? ' class="active-group"' : '';
		$class = ($login_schema == $app['this_group']->get_schema() && $login_schema == $s_schema) ? ' class="active"' : $class;

		echo '<li';
		echo $class;
		echo '>';

		echo '<a href="';
		echo $app['protocol'] . $app['groups']->get_host($login_schema) . '/' . $app['script_name'] . '.php?r=';
		echo $login_id == 'elas' ? 'guest' : $app['session']->get('role.' . $login_schema);
		echo '&u=' . $login_id;
		echo '">';

		echo $app['config']->get('systemname', $login_schema);
		echo '</a>';
		echo '</li>';
	}

	if ($count_interlets_groups)
	{
		echo '<li class="divider"></li>';
		echo '<li class="dropdown-header">';
		echo $count_interlets_groups > 1 ? 'Gekoppelde interSystemen' : 'Gekoppeld interSysteem';
		echo '</li>';

		if (count($eland_interlets_groups))
		{
			foreach ($eland_interlets_groups as $sch => $h)
			{
				echo '<li';
				echo ($app['this_group']->get_schema() == $sch) ? ' class="active"' : '';
				echo '>';

				$page = (isset($allowed_interlets_landing_pages[$app['script_name']])) ? $app['script_name'] : 'messages';

				echo '<a href="' . generate_url($page,  ['welcome' => 1], $sch) . '">';
				echo $app['config']->get('systemname', $sch) . '</a>';
				echo '</li>';
			}
		}

		if (count($elas_interlets_groups))
		{
			foreach ($elas_interlets_groups as $grp_id => $grp)
			{
				echo '<li>';
				echo '<a href="#" data-elas-group-id="';
				echo $grp_id;
				echo '">';
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
			'status'						=> ['exclamation-triangle', 'Status'],
			'categories'	 				=> ['clone', 'Categorieën'],
			'type_contact'					=> ['circle-o-notch', 'Contact Types'],
			'contacts'						=> ['map-marker', 'Contacten'],
			'config'						=> ['gears', 'Instellingen'],
			'intersystem'					=> ['share-alt', 'InterSysteem'],
			'apikeys'						=> ['key', 'Apikeys'],
			'export'						=> ['download', 'Export'],
			'autominlimit'					=> ['arrows-v', 'Auto Min Limiet'],
			'mass_transaction'				=> ['exchange', 'Massa-Transactie'],
			'logs'							=> ['history', 'Logs'],
		];

		if (!$app['config']->get('template_lets') || !$app['config']->get('interlets_en'))
		{
			unset($menu['intersystem'], $menu['apikeys']);
		}

		echo '<li class="dropdown">';
		echo '<a href="#" class="dropdown-toggle" data-toggle="dropdown" ';
		echo 'role="button" aria-expanded="false">';
		echo '<span class="fa fa-cog"></span> ';
		echo 'Admin modus';
		echo '<span class="caret"></span></a>';
		echo '<ul class="dropdown-menu" role="menu">';

		foreach ($menu as $link => $item)
		{
			$active = $app['script_name'] === $link ? ' class="active"' : '';
			echo '<li' . $active . '>';
			echo aphp($link, [], $item[1], false, false, $item[0]);
			echo '</li>';
		}
		echo '<li class="divider"></li>';

		if ($page_access == 'admin')
		{
			$user_url = $app['config']->get('default_landing_page') . '.php';

			$u_param = 'view_' . $user_url;
			$u_param = in_array($user_url, ['messages', 'users', 'news']) ? ['view' => $$u_param] : [];
		}
		else
		{
			$user_url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

			$u_param = [];
		}

		$u_param['r'] = 'user';
		$u_param['u'] = $_GET['u'];

		echo '<li>';
		echo '<a href="' . $user_url . '?' . http_build_query($u_param) . '">';
		echo '<i class="fa fa-user"></i>';
		echo ' Leden modus</a>';
		echo '</li>';

		if ($app['config']->get('template_lets') && $app['config']->get('interlets_en'))
		{
			$u_param['r'] = 'guest';

			echo '<li>';
			echo '<a href="' . $user_url . '?' . http_build_query($u_param) . '">';
			echo '<i class="fa fa-share-alt"></i>';
			echo ' Gast modus</a>';
			echo '</li>';
		}

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

echo '<div class="swiper-container">';
echo '<div class="row-offcanvas row-offcanvas-left">';
echo '<div id="sidebar" class="sidebar-offcanvas">';

if ($s_anonymous)
{
	$menu = [
		'login'			=> ['sign-in', 'Login', []],
	];

	if ($app['config']->get('contact_form_en'))
	{
		$menu['contact'] = ['comment-o', 'Contact', []];
	}

	if ($app['config']->get('registration_en'))
	{
		$menu['register'] = ['check-square-o', 'Inschrijven', []];
	}
}
else
{
	$menu = [
		'messages'				=> ['newspaper-o', 'Vraag & Aanbod', ['view' => $view_messages]],
		'users'					=> ['users', $s_admin ? 'Gebruikers' : 'Leden', ['status' => 'active', 'view' => $view_users]],
		'transactions'			=> ['exchange', 'Transacties', []],
		'news'					=> ['calendar-o', 'Nieuws', ['view' => $view_news]],
	];

	$menu['docs'] = ['files-o', 'Documenten', []];

	if ($app['config']->get('forum_en'))
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
	$active = $app['script_name'] == $link ? ' class="active"' : '';
	echo '<li' . $active . '>';
	echo aphp($link, $item[2],
		$item[1], false, false, $item[0]);
	echo '</li>';
}
echo '</ul>';

echo '</div>';

$class_admin = $page_access === 'admin' ? ' admin' : '';

echo '<div id="wrap">';
echo '<div id="main" class="container-fluid clear-top' . $class_admin . '">';

echo $app['alert']->get();

echo '<div class="row">';
echo '<div class="col-md-12 top-buttons">';

echo '<div class="visible-xs pull-left button-offcanvas menu-button">';
echo '<button type="button" class="btn btn-primary btn-md" data-toggle="offcanvas"';
echo ' title="Menu"><i class="fa fa-chevron-left"></i></button>';
echo '</div>';

echo $top_buttons ?? '';

if (($top_buttons_right ?? false) || ($csv_en ?? false))
{
	echo '<div class="pull-right">';

	if ($csv_en ?? false)
	{
		$app['assets']->add('csv.js');

		echo '<a href="#" class="csv btn btn-info btn-md" title="Download CSV">';
		echo '<i class="fa fa-file"></i>';
		echo '</a>&nbsp;';
	}

	echo $top_buttons_right ?? '';
	echo '</div>';
}

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
	echo isset($fa) ? '<i class="fa fa-' . $fa . '"></i> ' : '';
	echo $h1 . '</h1>';
}
