<?php declare(strict_types=1);

namespace App\Service;

use App\Cache\ConfigCache;
use App\Cnst\PagesCnst;
use App\Service\PageParamsService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;

class VarRouteService
{
	protected Session $session;
	protected array $var_route_ary;
	protected bool $is_admin;
	protected string $route;

	public function __construct(
		protected RequestStack $request_stack,
		protected PageParamsService $pp,
		protected ConfigCache $config_cache
	)
	{
		$this->session = $this->request_stack->getSession();
		$this->init();
	}

	private function init():void
	{
		$this->is_admin = $this->pp->is_admin();
		$request = $this->request_stack->getCurrentRequest();
		$this->route = $request->attributes->get('_route');

		$view_ary = $this->session->get('view') ?? PagesCnst::DEFAULT_VIEW;

		if (isset(PagesCnst::ROUTE_TO_VIEW[$this->route]))
		{
			[$menu, $view] = PagesCnst::ROUTE_TO_VIEW[$this->route];

			if ($view_ary[$menu] !== $view)
			{
				$view_ary[$menu] = $view;
				$this->session->set('view', $view_ary);
			}
		}

		$this->var_route_ary = [
			'users'			=> 'users_' . $view_ary['users'],
			'messages'		=> 'messages_' . $view_ary['messages'],
			'messages_self'	=> 'messages_' . $view_ary['messages'] . '_self',
			'news'			=> 'news_' . $view_ary['news'],
		];

		$default = $this->config_cache->get_str('system.default_landing_page', $this->pp->schema());

		if (!$this->config_cache->get_bool($default . '.enabled', $this->pp->schema()))
		{
			$default = 'users';
		}

		$this->var_route_ary['default'] = $this->get($default);
	}

	public function get(string $menu_route):string
	{
		return $this->var_route_ary[$menu_route] ?? $menu_route;
	}

	public function get_fallback_route(string $active_menu, string $schema):string
	{
		if (isset(PagesCnst::LANDING[$active_menu]))
		{
			$route_enabled = $this->config_cache->get_bool($active_menu . '.enabled', $schema);
		}
		else
		{
			$route_enabled = false;
		}

		if (!$route_enabled)
		{
			$default_route = $this->config_cache->get_str('system.default_landing_page', $schema);
			$default_enabled = $this->config_cache->get_bool($default_route . '.enabled', $schema);
			$active_menu = $default_enabled ? $default_route : 'users';
		}

		return $this->var_route_ary[$active_menu] ?? $active_menu;
	}

	public function get_inter(string $menu_route, string $remote_schema):string
	{
		if (isset(PagesCnst::LANDING[$menu_route]))
		{
			$route_enabled = $this->config_cache->get_bool($menu_route . '.enabled', $remote_schema);
		}
		else
		{
			$route_enabled = false;
		}

		if (!$route_enabled)
		{
			$default_route = $this->config_cache->get_str('system.default_landing_page', $remote_schema);
			$default_enabled = $this->config_cache->get_bool($default_route . '.enabled', $remote_schema);
			$menu_route = $default_enabled ? $default_route : 'users';
		}

		return $this->var_route_ary[$menu_route] ?? $menu_route;
	}
}
