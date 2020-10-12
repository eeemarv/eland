<?php declare(strict_types=1);

namespace App\Service;

use App\Cnst\PagesCnst;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class VarRouteService
{
	protected SessionInterface $session;
	protected PageParamsService $pp;

	protected array $var_route_ary;

	public function __construct(
		SessionInterface $session,
		PageParamsService $pp,
		ConfigService $config_service
	)
	{
		$this->session = $session;
		$this->pp = $pp;
		$this->config_service = $config_service;

		$this->init();
	}

	private function init():void
	{
		$route = $this->pp->route();

		$view_ary = $this->session->get('view') ?? PagesCnst::DEFAULT_VIEW;

		if (isset(PagesCnst::ROUTE_TO_VIEW[$$route]))
		{
			[$menu, $view] = PagesCnst::ROUTE_TO_VIEW[$$route];

			if ($view_ary[$menu] !== $view)
			{
				$view_ary[$menu] = $view;
				$this->session->set('view', $view_ary);
			}
		}

		$this->var_route_ary = [
			'users'			=> 'users_' . $view_ary['users'],
			'messages'		=> 'messages_' . $view_ary['messages'],
			'news'			=> 'news_' . $view_ary['news'],
		];

		$default = $this->config_service->get_str('system.default_landing_page', $this->pp->schema());

		if (!$this->config_service->get_bool($default . '.enabled', $this->pp->schema()))
		{
			$default = 'users';
		}

		$this->var_route_ary['default'] = $this->get($default);
	}

	public function get(string $menu_route):string
	{
		return $this->var_route_ary[$menu_route] ?? $menu_route;
	}

	public function get_inter(string $menu_route, string $remote_schema):string
	{
		if (isset(PagesCnst::LANDING[$menu_route]))
		{
			$route_enabled = $this->config_service->get_bool($menu_route . '.enabled', $remote_schema);
		}
		else
		{
			$route_enabled = false;
		}

		if (!$route_enabled)
		{
			$default_route = $this->config_service->get_str('system.default_landing_page', $remote_schema);
			$default_enabled = $this->config_service->get_bool($default_route . '.enabled', $remote_schema);
			$menu_route = $default_enabled ? $default_route : 'users';
		}

		return $this->var_route_ary[$menu_route] ?? $menu_route;
	}
}
