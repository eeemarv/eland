<?php

use cnst\pages as cnst_pages;

// not used (yet)

class header
{
	protected $assets;

	protected $header;
	protected $c_ary;
	protected $footer;

	public function __construct(
		assets $assets,
		config $config,

	)
	{
		$this->assets = $assets;

	}

	public function add(string $in):void
	{
	}

	public function get_header():string
	{

	}

	public function get_footer():string
	{

	}

	public function get():Response
	{
		return new Response(implode('', $this->t_ary));
	}

	public function get_string():string
	{
		$matched_route = $app['request']->attributes->get('_route');

		if ($css = $app['config']->get('css', $app['tschema']))
		{
			$app['assets']->add_external_css([$css]);
		}

		$out = '<!DOCTYPE html>';
		$out .= '<html lang="nl">';
		$out .= '<head>';

		$out .= '<title>';
		$out .= $app['config']->get('systemname', $app['tschema']);
		$out .= '</title>';

		$out .= $app['assets']->get_css();

		$out .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
		$out .= '<meta name="viewport" content="width=device-width, initial-scale=1">';

		$out .= '<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">';
		$out .= '<link rel="icon" type="image/png" href="/favicon-32x32.png" sizes="32x32">';
		$out .= '<link rel="icon" type="image/png" href="/favicon-16x16.png" sizes="16x16">';
		$out .= '<link rel="manifest" href="/manifest.json">';
		$out .= '<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#d55b5b">';
		$out .= '<meta name="theme-color" content="#ffffff">';

		$out .= '</head>';
		$out .= '<body data-session-params="';
		//
		$out .= '" class="';
		$out .= $app['s_admin'] ? 'admin' : ($app['s_guest'] ? 'guest' : 'member');
		$out .= '">';

		$out .= '<img src="/gfx/loading.gif';
		$out .= $app['assets']->get_version_param();
		$out .= '" ';
		$out .= 'class="ajax-loader" alt="waiting">';

		$out .= '<div class="navbar navbar-default navbar-fixed-top';
		$out .= $app['s_admin'] ? ' bg-info' : '';
		$out .= $app['s_guest'] ? ' bg-warning' : '';
		$out .= '">';
		$out .= '<div class="container-fluid">';

		$out .= '<div class="navbar-header">';

		if (!$app['s_anonymous'])
		{
			$out .= '<button type="button" class="navbar-toggle collapsed" ';
			$out .= 'data-toggle="collapse" data-target="#navbar-collapse-1" ';
			$out .= 'aria-expanded="false">';
			$out .= '<span class="sr-only">Toggle navigation</span>';
			$out .= '<span class="icon-bar"></span>';
			$out .= '<span class="icon-bar"></span>';
			$out .= '<span class="icon-bar"></span>';
			$out .= '</button>';
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
				$route = $app['config']->get('default_landing_page', $app['tschema']);
				$homepage_url = $app['link']->path($route, $app['pp_ary']);
			}
		}

		$out .= '<a href="';
		$out .= $homepage_url;
		$out .= '" class="pull-left hidden-xs">';
		$out .= '<div class="logo"></div>';
		$out .= '</a>';

		$out .= '<a href="';
		$out .= $homepage_url;
		$out .= '" class="navbar-brand">';
		$out .= $app['config']->get('systemname', $app['tschema']);
		$out .= '</a>';
		$out .= '</div>';

		$out .= '<div class="collapse navbar-collapse" id="navbar-collapse-1">';
		$out .= '<ul class="nav navbar-nav navbar-right">';

		if (!$app['s_anonymous']
			&& ($app['intersystems']->get_count($app['s_schema']) + count($app['s_logins'])) > 1)
		{
			$out .= '<li class="dropdown">';
			$out .= '<a href="#" class="dropdown-toggle" ';
			$out .= 'data-toggle="dropdown" role="button" ';
			$out .= 'aria-expanded="false">';
			$out .= '<span class="fa fa-share-alt"></span> ';
			$out .= 'Systeem';
			$out .= '<span class="caret"></span></a>';
			$out .= '<ul class="dropdown-menu" role="menu">';
			$out .= '<li class="dropdown-header">';

			if (count($app['s_logins']) > 1)
			{
				$out .= 'Eigen Systemen';
			}
			else
			{
				$out .= 'Eigen Systeem';
			}

			$out .= '</li>';

			foreach ($app['s_logins'] as $login_schema => $login_id)
			{
				$out .= '<li';

				if ($login_schema === $app['s_schema'])
				{
					if ($login_schema === $app['tschema'])
					{
						$out .= ' class="active"';
					}
					else if (count($app['s_logins']) > 1)
					{
						$out .= ' class="active-group"';
					}

				}

				$out .= '>';

				$out .= $app['link']->link_no_attr($matched_route, [
					'system' 		=> $app['systems']->get_system($login_schema),
					'role_short'	=> $login_id === 'elas'
						? 'g'
						: $app['pp_role_short'],
				], [], $app['config']->get('systemname', $login_schema));

				$out .= '</li>';
			}

			if ($app['intersystems']->get_count($app['s_schema']))
			{
				$out .= '<li class="divider"></li>';
				$out .= '<li class="dropdown-header">';
				$out .= $app['intersystems']->get_count() > 1
					? 'Gekoppelde interSystemen'
					: 'Gekoppeld interSysteem';
				$out .= '</li>';

				if ($app['intersystems']->get_eland_count($app['s_schema']))
				{
					foreach ($app['intersystems']->get_eland($app['s_schema']) as $eland_schema => $h)
					{
						$out .= '<li';

						if ($app['tschema'] === $eland_schema)
						{
							$out .= ' class="active"';
						}

						$out .= '>';

						$route = isset(cnst_pages::INTERSYSTEM_LANDING[$matched_route])
							? $matched_route
							: 'messages';

						$out .= $app['link']->link_no_attr($route, $app['pp_ary'],
							['welcome' => 1],
							$app['config']->get('systemname', $eland_schema));

						$out .= '</li>';
					}
				}

				if ($app['intersystems']->get_elas_count($app['s_schema']))
				{
					foreach ($app['intersystems']->get_elas($app['s_schema']) as $elas_grp_id => $elas_grp)
					{
						$out .= '<li>';
						$out .= '<a href="#" data-elas-group-id="';
						$out .= $elas_grp_id;
						$out .= '">';
						$out .= $elas_grp['groupname'];
						$out .= '</a>';
						$out .= '</li>';
					}
				}
			}

			$out .= '</ul>';
			$out .= '</li>';
		}

		if (!$app['s_anonymous'])
		{
			$out .= '<li class="dropdown">';
			$out .= '<a href="#" class="dropdown-toggle" ';
			$out .= 'data-toggle="dropdown" role="button" ';
			$out .= 'aria-expanded="false">';
			$out .= '<span class="fa fa-user"></span> ';

			if ($app['s_master'])
			{
				$out .= 'Master';
			}
			else if ($app['s_elas_guest'])
			{
				$out .= 'eLAS gast login';
			}
			else if ($app['s_schema'] && $app['s_id'])
			{
				$out .= $app['s_system_self'] ? '' : $app['s_schema'] . '.';
				$out .= $app['account']->str($app['s_id'], $app['s_schema']);
			}

			$out .= '<span class="caret"></span></a>';
			$out .= '<ul class="dropdown-menu" role="menu">';

			if ($app['s_schema'] && !$app['s_master'] && !$app['s_guest'])
			{
				$out .= '<li>';

				$out .= $app['link']->link_fa('users', $app['pp_ary'],
					['id' => $app['s_id']], 'Mijn gegevens', [], 'user');

				$out .= '</li>';

				$out .= '<li>';

				$out .= $app['link']->link_fa('messages', $app['pp_ary'],
					['f' => ['uid' => $app['s_id']]],
					'Mijn vraag en aanbod', [], 'newspaper-o');

				$out .= '</li>';
				$out .= '<li>';

				$out .= $app['link']->link_fa('transactions', $app['pp_ary'],
					['f' => ['uid' => $app['s_id']]],
					'Mijn transacties', [], 'exchange');

				$out .= '</li>';

				$out .= '<li class="divider"></li>';
			}

			$out .= '<li>';

			$out .= $app['link']->link_fa('logout', $app['pp_ary'],
				[], 'Uitloggen', [], 'sign-out');

			$out .= '</li>';

			$out .= '</ul>';
			$out .= '</li>';

			if ($app['s_admin'])
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

				$out .= '<li class="dropdown">';
				$out .= '<a href="#" class="dropdown-toggle" data-toggle="dropdown" ';
				$out .= 'role="button" aria-expanded="false">';
				$out .= '<span class="fa fa-cog"></span> ';
				$out .= 'Admin modus';
				$out .= '<span class="caret"></span></a>';
				$out .= '<ul class="dropdown-menu" role="menu">';

				foreach ($menu as $route => $item)
				{
					$active = $matched_route === $route ? ' class="active"' : '';

					$out .= '<li' . $active . '>';

					$out .= $app['link']->link_fa($route, $app['pp_ary'],
						[], $item[1], [], $item[0]);

					$out .= '</li>';
				}

				$out .= '<li class="divider"></li>';

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

				$out .= '<li>';
				$out .= '<a href="';
				$out .= $user_url . '?';
				$out .= http_build_query($u_param);
				$out .= '">';
				$out .= '<i class="fa fa-user"></i>';
				$out .= ' Leden modus</a>';
				$out .= '</li>';

				if ($app['intersystem_en'])
				{
					$u_param['r'] = 'guest';

					$out .= '<li>';
					$out .= '<a href="' . $user_url . '?';
					$out .= http_build_query($u_param) . '">';
					$out .= '<i class="fa fa-share-alt"></i>';
					$out .= ' Gast modus</a>';
					$out .= '</li>';
				}

				$out .= '</ul>';
				$out .= '</li>';
			}
			else if ($app['s_system_self']
				&& ((isset($app['session_user'])
					&& count($app['session_user'])
					&& $app['session_user']['accountrole'] === 'admin')
				|| $app['s_master']))
			{
				$out .= '<li class="dropdown">';
				$admin_url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
				$get_params = $_GET;
				$get_params['r'] = 'admin';

				$out .= '<a href="' . $admin_url . '?';
				$out .= http_build_query($get_params) . '" ';
				$out .= 'title="Admin modus inschakelen">';
				$out .= '<span class="fa fa-times text-danger"></span> ';
				$out .= 'Admin modus';
				$out .= '</a>';
			}
		}

		$out .= '</ul>';
		$out .= '</div>';

		$out .= '</div>';
		$out .= '</div>';

		$out .= '<div class="swiper-container">';
		$out .= '<div class="row-offcanvas row-offcanvas-left">';
		$out .= '<div id="sidebar" class="sidebar-offcanvas">';

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

		$out .= '<br>';
		$out .= '<ul class="nav nav-pills nav-stacked">';

		foreach ($menu as $route => $item)
		{
			$out .= '<li';
			$out .= $matched_route == $route ? ' class="active"' : '';
			$out .= '>';

			$out .= $app['link']->link_fa($route, $app['pp_ary'],
				$item[2], $item[1], [], $item[0]);

			$out .= '</li>';
		}
		$out .= '</ul>';

		$out .= '</div>';

		$out .= '<div id="wrap">';
		$out .= '<div id="main" ';
		$out .= 'class="container-fluid clear-top';
		$out .= $app['s_admin'] ? ' admin' : '';
		$out .= '">';

		$out .= $app['alert']->get();

		$out .= '<div class="row">';
		$out .= '<div class="col-md-12 top-buttons">';

		$out .= '<div class="visible-xs pull-left ';
		$out .= 'button-offcanvas menu-button">';
		$out .= '<button type="button" class="btn btn-primary btn-md" ';
		$out .= 'data-toggle="offcanvas"';
		$out .= ' title="Menu"><i class="fa fa-chevron-left">';
		$out .= '</i></button>';
		$out .= '</div>';

		$out .= $app['btn_top']->get();

		if ($app['btn_nav']->has_content())
		{
			$out .= '<div class="pull-right">';
			$out .= $app['btn_nav']->get();
			$out .= '</div>';
		}

		$out .= '</div>';
		$out .= '</div>';

		$out .= $app['heading']->get_h1();
	}
}
