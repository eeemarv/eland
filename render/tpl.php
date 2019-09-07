<?php declare(strict_types=1);

namespace render;

use Symfony\Component\HttpFoundation\Response;
use cnst\pages as cnst_pages;
use cnst\menu as cnst_menu;
use service\alert;
use service\assets;
use service\config;
use service\systems;
use service\intersystems;
use service\item_access;
use render\account;
use render\btn_nav;
use render\btn_top;
use render\heading;
use render\link;

class tpl
{
	const LINK_ROUTE = [
		'messages'		=> 'messages_extended',
		'users'			=> 'users_list',
		'transactions'	=> 'transactions',
		'docs'			=> 'docs',
		'news'			=> 'news_extended',
	];

	protected $content = '';
	protected $menu;

	protected $alert;
	protected $assets;
	protected $config;
	protected $systems;
	protected $intersystems;
	protected $item_access;
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
	protected $pp_anonymous;
	protected $pp_guest;
	protected $pp_user;
	protected $pp_admin;
	protected $s_master;
	protected $s_elas_guest;
	protected $s_system_self;
	protected $intersystem_en;
	protected $r_messages;
	protected $r_users;
	protected $r_news;

	public function __construct(
		alert $alert,
		assets $assets,
		config $config,
		systems $systems,
		intersystems $intersystems,
		item_access $item_access,
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
		bool $pp_anonymous,
		bool $pp_guest,
		bool $pp_user,
		bool $pp_admin,
		bool $s_master,
		bool $s_elas_guest,
		bool $s_system_self,
		bool $intersystem_en,
		string $r_messages,
		string $r_users,
		string $r_news,
		string $r_users_show
	)
	{
		$this->alert = $alert;
		$this->assets = $assets;
		$this->config = $config;
		$this->systems = $systems;
		$this->intersystems = $intersystems;
		$this->item_access = $item_access;
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
		$this->pp_anonymous = $pp_anonymous;
		$this->pp_guest = $pp_guest;
		$this->pp_user = $pp_user;
		$this->pp_admin = $pp_admin;
		$this->s_master = $s_master;
		$this->s_elas_guest = $s_elas_guest;
		$this->s_system_self = $s_system_self;
		$this->intersystem_en = $intersystem_en;
		$this->r_messages = $r_messages;
		$this->r_users = $r_users;
		$this->r_news = $r_news;
		$this->r_users_show = $r_users_show;
	}

	public function add(string $add):void
	{
		$this->content .= $add;
	}

	public function menu(string $menu):void
	{
		$this->menu = $menu;
	}

	public function get():Response
	{
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

		if ($this->pp_admin)
		{
			$out .= ' class="admin"';
		}
		else if ($this->pp_guest)
		{
			$out .= ' class="guest"';
		}

		$out .= '">';

		$out .= '<img src="';
		$out .= $this->assets->get('loading.gif');
		$out .= '" ';
		$out .= 'class="ajax-loader" alt="waiting">';

		$out .= '<div class="navbar navbar-default navbar-fixed-top';
		$out .= $this->pp_admin ? ' bg-info' : '';
		$out .= $this->pp_guest ? ' bg-warning' : '';
		$out .= '">';
		$out .= '<div class="container-fluid">';

		$out .= '<div class="navbar-header">';

		if (!$this->pp_anonymous)
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
			if ($this->pp_anonymous)
			{
				$homepage_url = $this->link->path('login', [
					'system' => $this->pp_ary['system'],
				]);
			}
			else
			{
				$route = $this->config->get('default_landing_page', $this->tschema);
				$route = self::LINK_ROUTE[$route];
//				$homepage_url = $this->link->path($route, $this->pp_ary);
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

		if (!$this->pp_anonymous
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

				$route = $this->get_route_from_menu($this->menu);

				$out .= $this->link->link_no_attr($route, [
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

						$menu = isset(cnst_pages::INTERSYSTEM_LANDING[$this->menu])
							? $this->menu
							: 'messages';

						$route = $this->get_route_from_menu($menu);

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

		if (!$this->pp_anonymous)
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

			if ($this->s_schema && !$this->s_master && !$this->pp_guest)
			{
				$out .= '<li>';

				$out .= $this->link->link_fa($this->r_users_show, $this->pp_ary,
					['id' => $this->s_id], 'Mijn gegevens', [], 'user');

				$out .= '</li>';

				$out .= '<li>';

				$out .= $this->link->link_fa($this->r_messages, $this->pp_ary,
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

			if ($this->pp_admin)
			{
				$admin_menu = [
					'status'			=> ['exclamation-triangle', 'Status'],
					'categories'	 	=> ['clone', 'Categorieën'],
					'contact_types'		=> ['circle-o-notch', 'Contact Types'],
					'contacts'			=> ['map-marker', 'Contacten'],
					'config'			=> ['gears', 'Instellingen'],
					'intersystems'		=> ['share-alt', 'InterSysteem'],
					'apikeys'			=> ['key', 'Apikeys'],
					'export'			=> ['download', 'Export'],
					'autominlimit'		=> ['arrows-v', 'Auto Min Limiet'],
					'mass_transaction'	=> ['exchange', 'Massa-Transactie'],
					'logs'				=> ['history', 'Logs'],
				];

				if (!$this->intersystem_en)
				{
					unset($admin_menu['intersystems'], $admin_menu['apikeys']);
				}

				$out .= '<li class="dropdown">';
				$out .= '<a href="#" class="dropdown-toggle" data-toggle="dropdown" ';
				$out .= 'role="button" aria-expanded="false">';
				$out .= '<span class="fa fa-cog"></span> ';
				$out .= 'Admin modus';
				$out .= '<span class="caret"></span></a>';
				$out .= '<ul class="dropdown-menu" role="menu">';

				foreach ($admin_menu as $route => $item)
				{
					$active = $this->menu === $route ? ' class="active"' : '';

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

		$out .= '<br>';
		$out .= '<ul class="nav nav-pills nav-stacked">';

		foreach (cnst_menu::SIDEBAR as $route => $item)
		{
			if (!$this->item_access->is_visible($item['access']))
			{
				continue;
			}

			if (isset($item['config_en']))
			{
				if (!$this->config->get($item['config_en'], $this->tschema))
				{
					continue;
				}
			}

			$out .= '<li';
			$out .= $this->menu === $route ? ' class="active"' : '';
			$out .= '>';

			if (isset($item['var_route']))
			{
				$v_route = $this->{$item['var_route']};
			}
			else
			{
				$v_route = $route;
			}

			$out .= $this->link->link_fa($v_route, $this->pp_ary,
				[], $item['label'], [], $item['fa']);

			$out .= '</li>';
		}

		$out .= '</ul>';

		$out .= '</div>';

		$out .= '<div id="wrap">';
		$out .= '<div id="main" ';
		$out .= 'class="container-fluid clear-top';
		$out .= $this->pp_admin ? ' admin' : '';
		$out .= '">';

		$out .= $this->alert->get();

		$out .= '<div class="row">';
		$out .= '<div class="col-md-12 top-buttons">';

		$out .= '<div class="visible-xs pull-left ';
		$out .= 'button-offcanvas menu-button">';
		$out .= '<button type="button" class="btn btn-primary" ';
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
		$out .= $this->heading->get_sub();

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

	public function get_route_from_menu(string $menu):string
	{
		if (isset(cnst_menu::SIDEBAR[$menu]['var_route']))
		{
			$var_route = cnst_menu::SIDEBAR[$menu]['var_route'];
			return $this->{$var_route};
		}

		return $menu;
	}
}
