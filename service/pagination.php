<?php

namespace service;

class pagination
{
	protected $start;
	protected $limit;
	protected $page = 0;
	protected $table;

	protected $adjacent_num = 2;
	protected $row_count = 0;
	protected $page_num = 0;
	protected $entity = '';
	protected $params = [];
	protected $inline = false;
	protected $out;

	protected $limit_options = [
		10 		=> 10,
		25 		=> 25,
		50 		=> 50,
		100 	=> 100,
		250		=> 250,
		500		=> 500,
		1000 	=> 1000,
	];

	public function __construct()
	{
	}

	public function init(
		string $entity,
		int $row_count,
		array $params = [],
		bool $inline = false
	):void
	{
		$this->out = '';
		$this->limit = $params['limit'] ?: 25;
		$this->start = $params['start'] ?: 0;
		$this->row_count = $row_count;
		$this->entity = $entity;
		$this->params = $params;
		$this->inline = $inline;

		$this->page_num = ceil($this->row_count / $this->limit);
		$this->page = floor($this->start / $this->limit);

		if (!isset($this->limit_options[$this->limit]))
		{
			$this->limit_options[$this->limit] = $this->limit;
			ksort($this->limit_options);
		}
	}

	public function get():string
	{
		if ($this->out)
		{
			return $this->out;
		}

		$this->out .= '<div class="row print-hide"><div class="col-md-12">';
		$this->out .= '<ul class="pagination">';

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

		$this->out .= '<div class="pull-right hidden-xs">';
		$this->out .= '<div>';
		$this->out .= 'Totaal ';
		$this->out .= $this->row_count;
		$this->out .= ', Pagina ';
		$this->out .= $this->page + 1;
		$this->out .= ' van ';
		$this->out .= $this->page_num;
		$this->out .= '</div>';

		if (!$this->inline)
		{
			$this->out .= '<div>';
			$this->out .= '<form action="' . $this->entity . '.php">';

			$this->out .= 'Per pagina: ';
			$this->out .= '<select name="limit" onchange="this.form.submit();">';
			$this->out .= get_select_options($this->limit_options, $this->limit);
			$this->out .= '</select>';

			$action_params = $this->params;
			unset($action_params['limit']);
			$action_params['start'] = 0;
			$action_params = array_merge($action_params,  get_session_query_param());

			$action_params = http_build_query($action_params, 'prefix', '&');
			$action_params = urldecode($action_params);
			$action_params = explode('&', $action_params);

			foreach ($action_params as $param)
			{
				[$name, $value] = explode('=', $param);
				$this->out .= '<input name="' . $name . '" value="' . $value . '" type="hidden">';
			}

			$this->out .= '</form>';
			$this->out .= '</div>';
		}
		$this->out .= '</div>';
		$this->out .= '</div></div>';

		return $this->out;
	}

	protected function get_link(int $page):string
	{
		$params = $this->params;
		$params['start'] = $page * $this->limit;
		$params['limit'] = $this->limit;

		$out = '<li';
		$out .= $page == $this->page ? ' class="active"' : '';
		$out .= '>';
		$out .= '<a href="';
		$out .= generate_url($this->entity, $params);
		$out .= '">';
		$out .= $page + 1;
		$out .= '</a></li>';

		return $out;
	}
}
