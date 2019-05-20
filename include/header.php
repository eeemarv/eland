<?php

if ($css = $app['config']->get('css', $app['tschema']))
{
	$app['assets']->add_external_css([$css]);
}

echo '<!DOCTYPE html>';
echo '<html lang="nl">';
echo '<head>';

echo '<title>';
echo $app['config']->get('systemname', $app['tschema']);
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
//
echo '" class="';
echo $app['s_admin'] ? 'admin' : ($app['s_guest'] ? 'guest' : 'member');
echo '">';

echo '<img src="/gfx/loading.gif';
echo $app['assets']->get_version_param();
echo '" ';
echo 'class="ajax-loader" alt="waiting">';

echo '<div class="navbar navbar-default navbar-fixed-top';
echo $app['s_admin'] ? ' bg-info' : '';
echo $app['s_guest'] ? ' bg-warning' : '';
echo '">';
echo '<div class="container-fluid">';

echo '<div class="navbar-header">';

if (!$app['s_anonymous'])
{
	echo '<button type="button" class="navbar-toggle collapsed" ';
	echo 'data-toggle="collapse" data-target="#navbar-collapse-1" ';
	echo 'aria-expanded="false">';
	echo '<span class="sr-only">Toggle navigation</span>';
	echo '<span class="icon-bar"></span>';
	echo '<span class="icon-bar"></span>';
	echo '<span class="icon-bar"></span>';
	echo '</button>';
}

$homepage_url = $app['config']->get('homepage_url', $app['tschema']);

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
echo $app['config']->get('systemname', $app['tschema']);
echo '</a>';
echo '</div>';

echo '<div class="collapse navbar-collapse" id="navbar-collapse-1">';
echo '<ul class="nav navbar-nav navbar-right">';

if (!$app['s_anonymous'] && ($app['count_intersystems'] + count($app['s_logins'])) > 1)
{
	echo '<li class="dropdown">';
	echo '<a href="#" class="dropdown-toggle" ';
	echo 'data-toggle="dropdown" role="button" ';
	echo 'aria-expanded="false">';
	echo '<span class="fa fa-share-alt"></span> ';
	echo 'Systeem';
	echo '<span class="caret"></span></a>';
	echo '<ul class="dropdown-menu" role="menu">';
	echo '<li class="dropdown-header">';

	if (count($app['s_logins']) === 1
		&& current($app['s_logins']) === 'eLAS')
	{
		echo 'eLAS Gast Login';
	}
	else
	{
		echo count($app['s_logins']) > 1
			? 'Eigen Systemen'
			: 'Eigen Systeem';
	}

	echo '</li>';

	foreach ($app['s_logins'] as $login_schema => $login_id)
	{
		$class = $app['s_schema'] === $login_schema
			&& count($app['s_logins']) > 1
				? ' class="active-group"'
				: '';
		$class = $login_schema === $app['tschema']
			&& $login_schema === $app['s_schema']
				? ' class="active"'
				: $class;

		echo '<li';
		echo $class;
		echo '>';

		echo '<a href="';
		echo $app['protocol'];
		echo $app['systems']->get_host($login_schema);
		echo '/';
		echo $app['script_name'];
		echo '.php?r=';
		echo $login_id === 'elas'
			? 'guest'
			: $app['session']->get('role.' . $login_schema);
		echo '&u=';
		echo $login_id;
		echo '">';

		echo $app['config']->get('systemname', $login_schema);
		echo '</a>';
		echo '</li>';
	}

	if ($app['count_intersystems'])
	{
		echo '<li class="divider"></li>';
		echo '<li class="dropdown-header">';
		echo $app['count_intersystems'] > 1
			? 'Gekoppelde interSystemen'
			: 'Gekoppeld interSysteem';
		echo '</li>';

		if (count($app['intersystem_ary']['eland']))
		{
			foreach ($app['intersystem_ary']['eland'] as $sch => $h)
			{
				echo '<li';

				if ($app['tschema'] === $sch)
				{
					echo ' class="active"';
				}

				echo '>';

				$page = isset(\util\cnst::INTERSYSTEM_LANDING_PAGES[$app['script_name']])
					? $app['script_name']
					: 'messages';

				echo $app['link']->link_no_attr($page, $app['pp_ary'],
					['welcome' => 1],
					$app['config']->get('systemname', $sch));

				echo '</li>';
			}
		}

		if (count($app['intersystem_ary']['elas']))
		{
			foreach ($app['intersystem_ary']['elas'] as $grp_id => $grp)
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

if (!$app['s_anonymous'])
{
	echo '<li class="dropdown">';
	echo '<a href="#" class="dropdown-toggle" ';
	echo 'data-toggle="dropdown" role="button" ';
	echo 'aria-expanded="false">';
	echo '<span class="fa fa-user"></span> ';

	if ($app['s_master'])
	{
		echo 'Master';
	}
	else if ($app['s_elas_guest'])
	{
		echo 'eLAS gast login';
	}
	else if ($app['s_schema'] && $app['s_id'])
	{
		echo $app['s_system_self'] ? '' : $app['s_schema'] . '.';
		echo link_user($app['s_id'], $app['s_schema'], false);
	}

	echo '<span class="caret"></span></a>';
	echo '<ul class="dropdown-menu" role="menu">';

	if ($app['s_schema'] && !$app['s_master'] && !$app['s_guest'])
	{
		echo '<li>';

		echo $app['link']->link_fa('users', $app['pp_ary'],
			['id' => $app['s_id']], 'Mijn gegevens', [], 'user');

		echo '</li>';

		echo '<li>';

		echo $app['link']->link_fa('messages', $app['pp_ary'],
			['f' => ['uid' => $app['s_id']]],
			'Mijn vraag en aanbod', [], 'newspaper-o');

		echo '</li>';
		echo '<li>';

		echo $app['link']->link_fa('transactions', $app['pp_ary'],
			['f' => ['uid' => $app['s_id']]],
			'Mijn transacties', [], 'exchange');

		echo '</li>';

		echo '<li class="divider"></li>';
	}

	echo '<li>';

	echo $app['link']->link_fa('logout', $app['pp_ary'],
		[], 'Uitloggen', [], 'sign-out');

	echo '</li>';

	echo '</ul>';
	echo '</li>';

	if ($app['s_admin'])
	{
		$menu = [
			'status'			=> ['exclamation-triangle', 'Status'],
			'categories'	 	=> ['clone', 'Categorieën'],
			'type_contact'		=> ['circle-o-notch', 'Contact Types'],
			'contacts'			=> ['map-marker', 'Contacten'],
			'config'			=> ['gears', 'Instellingen'],
			'intersystem'		=> ['share-alt', 'InterSysteem'],
			'apikeys'			=> ['key', 'Apikeys'],
			'export'			=> ['download', 'Export'],
			'autominlimit'		=> ['arrows-v', 'Auto Min Limiet'],
			'mass_transaction'	=> ['exchange', 'Massa-Transactie'],
			'logs'				=> ['history', 'Logs'],
		];

		if (!$app['config']->get('template_lets', $app['tschema'])
			|| !$app['config']->get('interlets_en', $app['tschema']))
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

		foreach ($menu as $route => $item)
		{
			$active = $app['matched_route'] === $route ? ' class="active"' : '';

			echo '<li' . $active . '>';

			echo $app['link']->link_fa($route, [
					'system'		=> $app['pp_system'],
					'role_short' 	=> $app['pp_role_short'],
				], [], $item[1], [], $item[0]);

			echo '</li>';
		}
		echo '<li class="divider"></li>';

		if ($app['page_access'] == 'admin')
		{
			$user_url = $app['config']->get('default_landing_page', $app['tschema']);
			$user_url .= '.php';

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
		echo '<a href="';
		echo $user_url . '?';
		echo http_build_query($u_param);
		echo '">';
		echo '<i class="fa fa-user"></i>';
		echo ' Leden modus</a>';
		echo '</li>';

		if ($app['config']->get('template_lets', $app['tschema'])
			&& $app['config']->get('interlets_en', $app['tschema']))
		{
			$u_param['r'] = 'guest';

			echo '<li>';
			echo '<a href="' . $user_url . '?';
			echo http_build_query($u_param) . '">';
			echo '<i class="fa fa-share-alt"></i>';
			echo ' Gast modus</a>';
			echo '</li>';
		}

		echo '</ul>';
		echo '</li>';
	}
	else if ($app['s_system_self']
		&& ((isset($app['session_user'])
			&& count($app['session_user'])
			&& $app['session_user']['accountrole'] === 'admin')
		|| $app['s_master']))
	{
		echo '<li class="dropdown">';
		$admin_url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		$get_params = $_GET;
		$get_params['r'] = 'admin';

		echo '<a href="' . $admin_url . '?';
		echo http_build_query($get_params) . '" ';
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

if ($app['s_anonymous'])
{
	$menu = [
		'login'			=> [
			'sign-in',
			'Login',
			[],
		],
	];

	if ($app['config']->get('contact_form_en', $app['tschema']))
	{
		$menu['contact'] = [
			'comment-o',
			'Contact',
			[],
		];
	}

	if ($app['config']->get('registration_en', $app['tschema']))
	{
		$menu['register'] = [
			'check-square-o',
			'Inschrijven',
			[],
		];
	}
}
else
{
	$menu = [
		'messages'		=> [
			'newspaper-o',
			'Vraag & Aanbod',
			[],
		],
		'users'			=> [
			'users',
			$app['s_admin'] ? 'Gebruikers' : 'Leden',
			['status' => 'active'],
		],
		'transactions'	=> [
			'exchange',
			'Transacties',
			[],
		],
		'news'			=> [
			'calendar-o',
			'Nieuws',
			[],
		],
		'docs' 			=> [
			'files-o',
			'Documenten',
			[],
		],
	];

	if ($app['config']->get('forum_en', $app['tschema']))
	{
		$menu['forum'] = [
			'comments-o',
			'Forum',
			[],
		];
	}

	if ($app['s_user'] || $app['s_admin'])
	{
		$menu['support'] = [
			'ambulance',
			'Probleem melden',
			[],
		];
	}
}

echo '<br>';
echo '<ul class="nav nav-pills nav-stacked">';

foreach ($menu as $route => $item)
{
	echo '<li';
	echo $app['matched_route'] == $route ? ' class="active"' : '';
	echo '>';

	echo $app['link']->link_fa($route, $app['pp_ary'],
		$item[2], $item[1], [], $item[0]);

	echo '</li>';
}
echo '</ul>';

echo '</div>';

echo '<div id="wrap">';
echo '<div id="main" ';
echo 'class="container-fluid clear-top';
echo $app['page_access'] === 'admin' ? ' admin' : '';
echo '">';

echo $app['alert']->get();

echo '<div class="row">';
echo '<div class="col-md-12 top-buttons">';

echo '<div class="visible-xs pull-left ';
echo 'button-offcanvas menu-button">';
echo '<button type="button" class="btn btn-primary btn-md" ';
echo 'data-toggle="offcanvas"';
echo ' title="Menu"><i class="fa fa-chevron-left">';
echo '</i></button>';
echo '</div>';

echo $app['btn_top']->get();

if ($app['btn_nav']->has_content())
{
	echo '<div class="pull-right">';
	echo $app['btn_nav']->get();
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

	if (isset($fa))
	{
		echo '<i class="fa fa-' . $fa . '"></i> ';
	}

	echo $h1 . '</h1>';
}
