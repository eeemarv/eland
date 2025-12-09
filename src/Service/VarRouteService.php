<?php declare(strict_types=1);

namespace App\Service;

use App\Cnst\PagesCnst;
use App\DTO\Schema;
use App\Service\ConfigService;
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
		private readonly RequestStack $request_stack,
		private readonly PageParamsService $pp,
		private readonly ConfigService $config_service
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

		$default = $this->config_service->get_str(
      config_id: 'system.default_landing_page',
      schema: $this->pp->schema_o(),
    );

		if (!$this->config_service->get_bool(
      config_id: $default . '.enabled',
      schema: $this->pp->schema_o(),
    ))
		{
			$default = 'users';
		}

		$this->var_route_ary['default'] = $this->get($default);
	}

	public function get(
    string $menu_route
  ):string
	{
		return $this->var_route_ary[$menu_route] ?? $menu_route;
	}

	public function get_fallback_route(
    string $active_menu,
    string $schema
  ):string
	{
    $schema_o = new Schema($schema);

		if (isset(PagesCnst::LANDING[$active_menu]))
		{
			$route_enabled = $this->config_service->get_bool(
        config_id: $active_menu . '.enabled',
        schema: $schema_o,
      );
		}
		else
		{
			$route_enabled = false;
		}

		if (!$route_enabled)
		{
			$default_route = $this->config_service->get_str(
        config_id: 'system.default_landing_page',
        schema: $schema_o,
      );
			$default_enabled = $this->config_service->get_bool(
        config_id: $default_route . '.enabled',
        schema: $schema_o,
      );
			$active_menu = $default_enabled ? $default_route : 'users';
		}

		return $this->var_route_ary[$active_menu] ?? $active_menu;
	}

	public function get_inter(
    string $menu_route,
    string $remote_schema,
  ):string
	{
    $remote_schema_o = new Schema($remote_schema);

		if (isset(PagesCnst::LANDING[$menu_route]))
		{
			$route_enabled = $this->config_service->get_bool(
        config_id: $menu_route . '.enabled',
        schema: $remote_schema_o,
      );
		}
		else
		{
			$route_enabled = false;
		}

		if (!$route_enabled)
		{
			$default_route = $this->config_service->get_str(
        config_id: 'system.default_landing_page',
        schema: $remote_schema_o,
      );
			$default_enabled = $this->config_service->get_bool(
        config_id: $default_route . '.enabled',
        schema: $remote_schema_o,
      );
			$menu_route = $default_enabled ? $default_route : 'users';
		}

		return $this->var_route_ary[$menu_route] ?? $menu_route;
	}
}
