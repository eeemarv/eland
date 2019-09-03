<?php declare(strict_types=1);

use cnst\pages as cnst_pages;

$matched_route = $app['request']->attributes->get('_route');

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
echo '<body';

if ($app['pp_admin'])
{
	echo ' class="admin"';
}
else if ($app['s_guest'])
{
	echo ' class="guest"';
}

echo '">';

echo '<img src="/gfx/loading.gif';
echo $app['assets']->get_version_param();
echo '" ';
echo 'class="ajax-loader" alt="waiting">';

echo '<div class="navbar navbar-default navbar-fixed-top';
echo $app['pp_admin'] ? ' bg-info' : '';
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
	if ($app['s_anonymous'])
	{
		$homepage_url = $app['link']->path('login', ['system' => $app['pp_system']]);
	}
	else
	{
		$homepage_url = $app['link']->path($app['r_default'], $app['pp_ary']);
	}
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

if (!$app['s_anonymous']
	&& ($app['intersystems']->get_count($app['s_schema']) + count($app['s_logins'])) > 1)
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

	if (count($app['s_logins']) > 1)
	{
		echo 'Eigen Systemen';
	}
	else
	{
		echo 'Eigen Systeem';
	}

	echo '</li>';

	foreach ($app['s_logins'] as $login_schema => $login_id)
	{
		echo '<li';

		if ($login_schema === $app['s_schema'])
		{
			if ($login_schema === $app['tschema'])
			{
				echo ' class="active"';
			}
			else if (count($app['s_logins']) > 1)
			{
				echo ' class="active-group"';
			}

		}

		echo '>';

		echo $app['link']->link_no_attr($matched_route, [
			'system' 		=> $app['systems']->get_system($login_schema),
			'role_short'	=> $login_id === 'elas'
				? 'g'
				: $app['pp_role_short'],
		], [], $app['config']->get('systemname', $login_schema));

		echo '</li>';
	}

	if ($app['intersystems']->get_count($app['s_schema']))
	{
		echo '<li class="divider"></li>';
		echo '<li class="dropdown-header">';
		echo $app['intersystems']->get_count() > 1
			? 'Gekoppelde interSystemen'
			: 'Gekoppeld interSysteem';
		echo '</li>';

		if ($app['intersystems']->get_eland_count($app['s_schema']))
		{
			foreach ($app['intersystems']->get_eland($app['s_schema']) as $eland_schema => $h)
			{
				echo '<li';

				if ($app['tschema'] === $eland_schema)
				{
					echo ' class="active"';
				}

				echo '>';

				$route = isset(cnst_pages::INTERSYSTEM_LANDING[$matched_route])
					? $matched_route
					: 'messages';

				echo $app['link']->link_no_attr($route, $app['pp_ary'],
					['welcome' => 1],
					$app['config']->get('systemname', $eland_schema));

				echo '</li>';
			}
		}

		if ($app['intersystems']->get_elas_count($app['s_schema']))
		{
			foreach ($app['intersystems']->get_elas($app['s_schema']) as $elas_grp_id => $elas_grp)
			{
				echo '<li>';
				echo '<a href="#" data-elas-group-login="';

				echo htmlspecialchars($app['link']->context_path('elas_group_login',
					$app['pp_ary'], ['group_id' => $elas_grp_id]));

				echo '">';
				echo $elas_grp['groupname'];
				echo '</a>';
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
		echo $app['account']->str($app['s_id'], $app['s_schema']);
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

	if ($app['pp_admin'])
	{
		$menu = [
			'status'			=> ['exclamation-triangle', 'Status'],
			'categories'	 	=> ['clone', 'Categorieën'],
			'contact_types'		=> ['circle-o-notch', 'Contact Types'],
			'contacts'			=> ['map-marker', 'Contacten'],
			'config'			=> ['gears', 'Instellingen'],
			'intersystem'		=> ['share-alt', 'InterSysteem'],
			'apikeys'			=> ['key', 'Apikeys'],
			'export'			=> ['download', 'Export'],
			'autominlimit'		=> ['arrows-v', 'Auto Min Limiet'],
			'mass_transaction'	=> ['exchange', 'Massa-Transactie'],
			'logs'				=> ['history', 'Logs'],
		];

		if (!$app['intersystem_en'])
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
			$active = $matched_route === $route ? ' class="active"' : '';

			echo '<li' . $active . '>';

			echo $app['link']->link_fa($route, $app['pp_ary'],
				[], $item[1], [], $item[0]);

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

		if ($app['intersystem_en'])
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
			$app['pp_admin'] ? 'Gebruikers' : 'Leden',
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

	if ($app['s_user'] || $app['pp_admin'])
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
	echo $matched_route == $route ? ' class="active"' : '';
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
echo $app['pp_admin'] ? ' admin' : '';
echo '">';

echo $app['alert']->get();

echo '<div class="row">';
echo '<div class="col-md-12 top-buttons">';

echo '<div class="visible-xs pull-left ';
echo 'button-offcanvas menu-button">';
echo '<button type="button" class="btn btn-primary" ';
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

echo $app['heading']->get_h1();
