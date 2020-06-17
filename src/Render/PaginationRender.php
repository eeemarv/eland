<?php declare(strict_types=1);

namespace App\Render;

use App\Render\LinkRender;
use App\Render\SelectRender;

class PaginationRender
{
	protected LinkRender $link_render;
	protected SelectRender $select_render;

	protected string $route;
	protected array $pp_ary;

	protected int $start;
	protected int $limit;
	protected int $page = 0;

	protected int $adjacent_num = 2;
	protected int $row_count = 0;
	protected int $page_num = 0;
	protected array $params = [];
	protected string $out = '';

	protected $limit_options = [
		'10' 		=> '10',
		'25' 		=> '25',
		'50' 		=> '50',
		'100'		=> '100',
		'250'		=> '250',
		'500'		=> '500',
		'1000'		=> '1000',
	];

	public function __construct(
		SelectRender $select_render,
		LinkRender $link_render
	)
	{
		$this->select_render = $select_render;
		$this->link_render = $link_render;
	}

	public function init(
		string $route,
		array $pp_ary,
		int $row_count,
		array $params = []
	):void
	{
		$this->limit = (int) $params['p']['limit'] ?? 25;
		$this->start = (int) $params['p']['start'] ?? 0;
		$this->row_count = $row_count;
		$this->route = $route;
		$this->pp_ary = $pp_ary;
		$this->params = $params;

		$this->page_num = (int) ceil($this->row_count / $this->limit);
		$this->page = (int) floor($this->start / $this->limit);

		if (!isset($this->limit_options[$this->limit]))
		{
			$this->limit_options[(string) $this->limit] = (string) $this->limit;
			ksort($this->limit_options);
		}
	}

	public function get():string
	{
		if ($this->out !== '')
		{
			return $this->out;
		}

		$this->out = '<div class="row mb-2">';
		$this->out .= '<div class="col-12">';
		$this->out .= '<nav aria-label="Page navigation">';
		$this->out .= '<ul class="pagination float-left">';

		$min_adjacent = $this->page - $this->adjacent_num;
		$max_adjacent = $this->page + $this->adjacent_num;

		$min_adjacent = $min_adjacent < 0 ? 0 : $min_adjacent;
		$max_adjacent = $max_adjacent > ($this->page_num - 1) ? $this->page_num - 1 : $max_adjacent;

		if ($min_adjacent)
		{
			$this->out .= $this->get_link(0);
		}

		for($page = $min_adjacent; $page < $max_adjacent + 1; $page++)
		{
			$this->out .= $this->get_link($page);
		}

		if ($max_adjacent != $this->page_num - 1)
		{
			$this->out .= $this->get_link($this->page_num - 1);
		}

		$this->out .= '</ul>';

		$this->out .= '<div class="float-right hidden-xs">';
		$this->out .= '<div>';
		$this->out .= 'Totaal ';
		$this->out .= $this->row_count;
		$this->out .= ', Pagina ';
		$this->out .= $this->page + 1;
		$this->out .= ' van ';
		$this->out .= $this->page_num;
		$this->out .= '</div>';

		$this->out .= '<div>';
		$this->out .= '<form action="';
		$this->out .= $this->link_render->path($this->route, $this->pp_ary);
		$this->out .= '">';

		$this->out .= 'Per pagina: ';
		$this->out .= '<select name="p[limit]" ';
		$this->out .= 'onchange="this.form.submit();">';
		$this->out .= $this->select_render->get_options($this->limit_options, (string) $this->limit);
		$this->out .= '</select>';

		$action_params = array_merge($this->pp_ary, $this->params);
		unset($action_params['p']['limit'], $action_params['role_short'], $action_params['system']);
		$action_params['p']['start'] = 0;

		$action_params = http_build_query($action_params, 'prefix', '&');
		$action_params = urldecode($action_params);
		$action_params = explode('&', $action_params);

		foreach ($action_params as $param)
		{
			[$name, $value] = explode('=', $param);

			if (!isset($value) || $value === '')
			{
				continue;
			}

			$this->out .= '<input name="' . $name . '" ';
			$this->out .= 'value="' . $value . '" type="hidden">';
		}

		$this->out .= '</form>';

		$this->out .= '</div>';
		$this->out .= '</div>';
		$this->out .= '</nav>';
		$this->out .= '</div>';
		$this->out .= '</div>';

		return $this->out;
	}

	protected function get_link(int $page):string
	{
		$params = $this->params;

		$params['p'] = [
			'start'	=> $page * $this->limit,
			'limit'	=> $this->limit,
		];

		$out = '<li class="page-item';
		$out .= $page == $this->page ? ' active' : '';
		$out .= '">';

		$out .= $this->link_render->link($this->route, $this->pp_ary,
			$params, (string) ($page + 1), ['class' => 'page-link']);

		$out .= '</li>';

		return $out;
	}
}
