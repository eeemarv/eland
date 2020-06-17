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
	protected bool $is_admin;

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
		$this->is_admin = $this->pp->is_admin();
		$route = $this->pp->route();

		$view_ary = $this->session->get('view') ?? PagesCnst::DEFAULT_VIEW;

		if (isset(PagesCnst::ROUTE_TO_VIEW[$route]))
		{
			[$menu, $view] = PagesCnst::ROUTE_TO_VIEW[$route];

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

		$default = $this->config_service->get('default_landing_page', $this->pp->schema());
		$this->var_route_ary['default'] = $this->get($default);
	}

	public function get(string $menu_route):string
	{
		$route = $this->var_route_ary[$menu_route] ?? $menu_route;
		$route .= isset(PagesCnst::ADMIN_ROUTE[$route]) && $this->is_admin ? '_admin' : '';
		return $route;
	}
}
