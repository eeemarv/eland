<?php

namespace render;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use cnst\pages as cnst_pages;
use service\alert;
use service\assets;
use service\config;
use service\systems;
use service\intersystems;
use render\account;
use render\btn_nav;
use render\btn_top;
use render\heading;
use render\link;

class tpl
{
	protected $content = '';
	protected $menu;

	protected $alert;
	protected $assets;
	protected $config;
	protected $systems;
	protected $intersystems;
	protected $account;
	protected $btn_nav;
	protected $btn_top;
	protected $heading;
	protected $link;
	protected $tschema;
	protected $s_schema;
	protected $s_id;
	protected $pp_ary;
	protected $session_user;
	protected $s_logins;
	protected $s_anonymous;
	protected $s_guest;
	protected $s_user;
	protected $s_admin;
	protected $s_master;
	protected $s_elas_guest;
	protected $s_system_self;
	protected $intersystem_en;

	public function __construct(
		alert $alert,
		assets $assets,
		config $config,
		systems $systems,
		intersystems $intersystems,
		account $account,
		btn_nav $btn_nav,
		btn_top $btn_top,
		heading $heading,
		link $link,
		string $tschema,
		string $s_schema,
		int $s_id,
		array $pp_ary,
		array $session_user,
		array $s_logins,
		bool $s_anonymous,
		bool $s_guest,
		bool $s_user,
		bool $s_admin,
		bool $s_master,
		bool $s_elas_guest,
		bool $s_system_self,
		bool $intersystem_en
	)
	{
		$this->alert = $alert;
		$this->assets = $assets;
		$this->config = $config;
		$this->systems = $systems;
		$this->intersystems = $intersystems;
		$this->account = $account;
		$this->btn_nav = $btn_nav;
		$this->btn_top = $btn_top;
		$this->heading = $heading;
		$this->link = $link;
		$this->tschema = $tschema;
		$this->s_schema = $s_schema;
		$this->s_id = $s_id;
		$this->pp_ary = $pp_ary;
		$this->session_user = $session_user;
		$this->s_logins = $s_logins;
		$this->s_anonymous = $s_anonymous;
		$this->s_guest = $s_guest;
		$this->s_user = $s_user;
		$this->s_admin = $s_admin;
		$this->s_master = $s_master;
		$this->s_elas_guest = $s_elas_guest;
		$this->s_system_self = $s_system_self;
		$this->intersystem_en = $intersystem_en;
	}

	public function add(string $add):void
	{
		$this->content .= $add;
	}

	public function set_menu(string $menu):void
	{
		$this->menu = $menu;
	}

	public function get(Request $request):Response
	{
		$matched_route = $request->attributes->get('_route');

		if ($css = $this->config->get('css', $this->tschema))
		{
			$this->assets->add_external_css([$css]);
		}

		$out = '<!DOCTYPE html>';
		$out .= '<html lang="nl">';
		$out .= '<head>';

		$out .= '<title>';
		$out .= $this->config->get('systemname', $this->tschema);
		$out .= '</title>';

		$out .= $this->assets->get_css();

		$out .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
		$out .= '<meta name="viewport" content="width=device-width, initial-scale=1">';

		$out .= '<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">';
		$out .= '<link rel="icon" type="image/png" href="/favicon-32x32.png" sizes="32x32">';
		$out .= '<link rel="icon" type="image/png" href="/favicon-16x16.png" sizes="16x16">';
		$out .= '<link rel="manifest" href="/manifest.json">';
		$out .= '<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#d55b5b">';
		$out .= '<meta name="theme-color" content="#ffffff">';

		$out .= '</head>';
		$out .= '<body';

		if ($this->s_admin)
		{
			$out .= ' class="admin"';
		}
		else if ($this->s_guest)
		{
			$out .= ' class="guest"';
		}

		$out .= '">';

		$out .= '<img src="/gfx/loading.gif';
		$out .= $this->assets->get_version_param();
		$out .= '" ';
		$out .= 'class="ajax-loader" alt="waiting">';

		$out .= '<div class="navbar navbar-default navbar-fixed-top';
		$out .= $this->s_admin ? ' bg-info' : '';
		$out .= $this->s_guest ? ' bg-warning' : '';
		$out .= '">';
		$out .= '<div class="container-fluid">';

		$out .= '<div class="navbar-header">';

		if (!$this->s_anonymous)
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

		$homepage_url = $this->config->get('homepage_url', $this->tschema);

		if (!$homepage_url)
		{
			if ($this->s_anonymous)
			{
				$homepage_url = $this->link->path('login', [
					'system' => $this->pp_ary['system'],
				]);
			}
			else
			{
				$route = $this->config->get('default_landing_page', $this->tschema);
				$homepage_url = $this->link->path($route, $this->pp_ary);
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
		$out .= $this->config->get('systemname', $this->tschema);
		$out .= '</a>';
		$out .= '</div>';

		$out .= '<div class="collapse navbar-collapse" id="navbar-collapse-1">';
		$out .= '<ul class="nav navbar-nav navbar-right">';

		if (!$this->s_anonymous
			&& ($this->intersystems->get_count($this->s_schema) + count($this->s_logins)) > 1)
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

			if (count($this->s_logins) > 1)
			{
				$out .= 'Eigen Systemen';
			}
			else
			{
				$out .= 'Eigen Systeem';
			}

			$out .= '</li>';

			foreach ($this->s_logins as $login_schema => $login_id)
			{
				$out .= '<li';

				if ($login_schema === $this->s_schema)
				{
					if ($login_schema === $this->tschema)
					{
						$out .= ' class="active"';
					}
					else if (count($this->s_logins) > 1)
					{
						$out .= ' class="active-group"';
					}
				}

				$out .= '>';

				$out .= $this->link->link_no_attr($matched_route, [
					'system' 		=> $this->systems->get_system($login_schema),
					'role_short'	=> $login_id === 'elas'
						? 'g'
						: $this->pp_ary['role_short'],
				], [], $this->config->get('systemname', $login_schema));

				$out .= '</li>';
			}

			if ($this->intersystems->get_count($this->s_schema))
			{
				$out .= '<li class="divider"></li>';
				$out .= '<li class="dropdown-header">';
				$out .= $this->intersystems->get_count($this->s_schema) > 1
					? 'Gekoppelde interSystemen'
					: 'Gekoppeld interSysteem';
				$out .= '</li>';

				if ($this->intersystems->get_eland_count($this->s_schema))
				{
					foreach ($this->intersystems->get_eland($this->s_schema) as $eland_schema => $h)
					{
						$out .= '<li';

						if ($this->tschema === $eland_schema)
						{
							$out .= ' class="active"';
						}

						$out .= '>';

						$route = isset(cnst_pages::INTERSYSTEM_LANDING[$matched_route])
							? $matched_route
							: 'messages';

						$out .= $this->link->link_no_attr($route, $this->pp_ary,
							['welcome' => 1],
							$this->config->get('systemname', $eland_schema));

						$out .= '</li>';
					}
				}

				if ($this->intersystems->get_elas_count($this->s_schema))
				{
					foreach ($this->intersystems->get_elas($this->s_schema) as $elas_grp_id => $elas_grp)
					{
						$out .= '<li>';
						$out .= '<a href="#" data-elas-group-login="';

						$out .= htmlspecialchars($this->link->context_path('elas_group_login',
							$this->pp_ary, ['group_id' => $elas_grp_id]));

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

		if (!$this->s_anonymous)
		{
			$out .= '<li class="dropdown">';
			$out .= '<a href="#" class="dropdown-toggle" ';
			$out .= 'data-toggle="dropdown" role="button" ';
			$out .= 'aria-expanded="false">';
			$out .= '<span class="fa fa-user"></span> ';

			if ($this->s_master)
			{
				$out .= 'Master';
			}
			else if ($this->s_elas_guest)
			{
				$out .= 'eLAS gast login';
			}
			else if ($this->s_schema && $this->s_id)
			{
				$out .= $this->s_system_self ? '' : $this->s_schema . '.';
				$out .= $this->account->str($this->s_id, $this->s_schema);
			}

			$out .= '<span class="caret"></span></a>';
			$out .= '<ul class="dropdown-menu" role="menu">';

			if ($this->s_schema && !$this->s_master && !$this->s_guest)
			{
				$out .= '<li>';

				$out .= $this->link->link_fa('users', $this->pp_ary,
					['id' => $this->s_id], 'Mijn gegevens', [], 'user');

				$out .= '</li>';

				$out .= '<li>';

				$out .= $this->link->link_fa('messages', $this->pp_ary,
					['f' => ['uid' => $this->s_id]],
					'Mijn vraag en aanbod', [], 'newspaper-o');

				$out .= '</li>';
				$out .= '<li>';

				$out .= $this->link->link_fa('transactions', $this->pp_ary,
					['f' => ['uid' => $this->s_id]],
					'Mijn transacties', [], 'exchange');

				$out .= '</li>';

				$out .= '<li class="divider"></li>';
			}

			$out .= '<li>';

			$out .= $this->link->link_fa('logout', $this->pp_ary,
				[], 'Uitloggen', [], 'sign-out');

			$out .= '</li>';

			$out .= '</ul>';
			$out .= '</li>';

			if ($this->s_admin)
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

				if (!$this->intersystem_en)
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

					$out .= $this->link->link_fa($route, $this->pp_ary,
						[], $item[1], [], $item[0]);

					$out .= '</li>';
				}

				$out .= '<li class="divider"></li>';

				if ($this->pp_ary['role_short'] === 'a')
				{
					$user_url = $this->config->get('default_landing_page', $this->tschema);
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

				if ($this->intersystem_en)
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
			else if ($this->s_system_self
				&& ((isset($this->session_user)
					&& count($this->session_user)
					&& $this->session_user['accountrole'] === 'admin')
				|| $this->s_master))
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

		if ($this->s_anonymous)
		{
			$menu = [
				'login'			=> [
					'sign-in',
					'Login',
					[],
				],
			];

			if ($this->config->get('contact_form_en', $this->tschema))
			{
				$menu['contact'] = [
					'comment-o',
					'Contact',
					[],
				];
			}

			if ($this->config->get('registration_en', $this->tschema))
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
					$this->s_admin ? 'Gebruikers' : 'Leden',
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

			if ($this->config->get('forum_en', $this->tschema))
			{
				$menu['forum'] = [
					'comments-o',
					'Forum',
					[],
				];
			}

			if ($this->s_user || $this->s_admin)
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

			$out .= $this->link->link_fa($route, $this->pp_ary,
				$item[2], $item[1], [], $item[0]);

			$out .= '</li>';
		}
		$out .= '</ul>';

		$out .= '</div>';

		$out .= '<div id="wrap">';
		$out .= '<div id="main" ';
		$out .= 'class="container-fluid clear-top';
		$out .= $this->s_admin ? ' admin' : '';
		$out .= '">';

		$out .= $this->alert->get();

		$out .= '<div class="row">';
		$out .= '<div class="col-md-12 top-buttons">';

		$out .= '<div class="visible-xs pull-left ';
		$out .= 'button-offcanvas menu-button">';
		$out .= '<button type="button" class="btn btn-primary btn-md" ';
		$out .= 'data-toggle="offcanvas"';
		$out .= ' title="Menu"><i class="fa fa-chevron-left">';
		$out .= '</i></button>';
		$out .= '</div>';

		$out .= $this->btn_top->get();

		if ($this->btn_nav->has_content())
		{
			$out .= '<div class="pull-right">';
			$out .= $this->btn_nav->get();
			$out .= '</div>';
		}

		$out .= '</div>';
		$out .= '</div>';

		$out .= $this->heading->get_h1();

		$out .= $this->content;

		$out .= '</div>';
		$out .= '</div>';
		$out .= '</div>';

		$out .= '<footer class="footer">';

		$out .= '<p><a href="https://eland.letsa.net">';
		$out .= 'eLAND</a> web app voor gemeenschapsmunten</p>';

		$out .= '<p><b>Rapporteer bugs in de ';
		$out .= '<a href="https://github.com/eeemarv/eland/issues">';
		$out .= 'Github issue tracker</a>.';
		$out .= '</b>';
		$out .= ' (Maak eerst een <a href="https://github.com">';
		$out .= 'Github</a> account aan.)</p>';

		$out .= '</footer>';

		$out .= '</div>';

		$out .= $this->assets->get_js();

		$out .= '</body>';
		$out .= '</html>';

		return new Response($out);
	}
}
