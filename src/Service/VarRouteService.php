<?php declare(strict_types=1);

namespace App\Service;

use App\Cnst\PagesCnst;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class VarRouteService
{
	protected RequestStack $request_stack;
	protected Request $request;
	protected SessionInterface $session;
	protected PageParamsService $pp;

	protected array $var_route_ary;
	protected bool $is_admin;

	public function __construct(
		RequestStack $request_stack,
		SessionInterface $session,
		PageParamsService $pp,
		ConfigService $config_service
	)
	{
		$this->request_stack = $request_stack;
		$this->session = $session;
		$this->pp = $pp;
		$this->config_service = $config_service;

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
