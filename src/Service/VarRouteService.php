<?php declare(strict_types=1);

namespace App\Service;

use App\Cnst\PagesCnst;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;

class VarRouteService
{
	protected $request_stack;
	protected $request;
	protected $session;
	protected $pp;

	protected $view_ary;
	protected $var_route_ary = [];

	public function __construct(
		RequestStack $request_stack,
		Session $session,
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
		$request = $this->request_stack->getCurrentRequest();
		$route = $request->attributes->get('_route');

		$view_ary = $this->session->get('view') ?? PagesCnst::DEFAULT_VIEW;

		if (isset(PagesCnst::ROUTE_TO_VIEW[$route]))
		{
			[$menu, $view_route] = PagesCnst::ROUTE_TO_VIEW[$this->route];

			if ($view_ary[$menu] !== $view_route)
			{
				$view_ary[$menu] = $view_route;
				$this->session->set('view', $view_ary);
			}
		}

		$r_users = 'users_' . $view_ary['users'];
		$r_users .= $r_users !== 'users_map'
			&& $this->pp->is_admin ? '_admin' : '';

		$this->var_route_ary = [
			'users'			=> $r_users,
			'users_show'	=> $this->pp->is_admin ? 'users_show_admin' : 'users_show',
			'users_edit'	=> $this->pp->is_admin ? 'users_edit_admin' : 'users_edit',
			'messages'		=> 'messages_' . $view_ary['messages'],
			'news'			=> 'messages_' . $view_ary['news'],
		];

		$default = $this->config_service->get('default_landing_page', $this->pp->schema());
		$this->var_route_ary['default'] = $this->get($default);
	}

	public function get(string $menu_route):string
	{
		return $this->var_route_ary[$menu_route] ?? $menu_route;
	}
}
