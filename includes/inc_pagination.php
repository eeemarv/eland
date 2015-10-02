<?php

class pagination
{
	private $start;
	private $limit;
	private $page = 0; 
	private $table; 
	
	private $adjacent_num = 1; 	
	private $row_count = 0;
	private $page_num = 0;
	private $base_url = '';
	private $param_start = '?';

	public function __construct($param)
	{
		$this->limit = $param['limit'] ?: 25;
		$this->start = $param['start'] ?: 0;
		$this->row_count = $param['row_count'] ?: 0;
		$this->base_url = $param['base_url'] ?: '';

		$this->page_num = ceil($this->row_count / $this->limit);
		$this->page = floor($this->start / $this->limit);

		if (strpos($this->base_url, '?') !== false)
		{
			$this->param_start = '&';
		}
	}

	public function getUrlParameters()
	{
		return 'start=' . ($this->page * $this->limit) . '&limit=' . $this->limit;
	}
	
	public function getLimit()
	{
		return $this->limit;
	}
	
	public function setLimit($limit){
		$this->limit = $limit;
		return $this;
	}
	
	public function getStart(){
		return $this->start;
	}
	
	public function setStart($start)
	{
		$this->start = $start;
		return $this;
	}	
		
	
	public function render(){

		echo '<div class="row"><div class="col-md-12">';
		echo '<ul class="pagination">';

		if ($this->page)
		{
			echo $this->addLink($this->page - 1, '&#9668;');
		}
		
		$min_adjacent = $this->page - $this->adjacent_num;
		$max_adjacent = $this->page + $this->adjacent_num;
		
		$min_adjacent = ($min_adjacent < 0) ? 0 : $min_adjacent;
		$max_adjacent = ($max_adjacent > $this->page_num - 1) ? $this->page_num - 1 : $max_adjacent;
		
		if ($min_adjacent)
		{
			echo $this->addLink(0);
		}

		for($page = $min_adjacent; $page < $max_adjacent + 1; $page++)
		{
			echo $this->addLink($page);
		}

		if ($max_adjacent != $this->page_num - 1)
		{
			echo $this->addLink($this->page_num - 1);
		}

		if ($this->page < $this->page_num - 1)
		{
			echo $this->addLink($this->page + 1, '&#9658;');
		}

		echo '</ul>';

		echo '<div class="pull-right">Totaal '.$this->row_count.', Pagina ' . ($this->page + 1).' van ' . $this->page_num;
		echo '</div>';

		echo '</div></div>';
	}
	
	public function addLink($page, $text = '')
	{
		$pag_link = '<li';
		$pag_link .= ($page == $this->page) ? ' class="active"' : '';		
		$pag_link .= '><a';	
		$pag_link .= ' href="' . $this->get_link(array('start' => $page * $this->limit, 'limit' => $this->limit)).'">';
		$pag_link .= ($text == '') ? ($page + 1) : $text;
		$pag_link .= '</a></li>';
		return $pag_link;	
	}

	private function get_link($params)
	{
		$param_str = $this->param_start;
		
		foreach ($params as $key => $val)
		{
			$param_str .= $key . '=' . $val . '&';
		}

		return $this->base_url  . rtrim($param_str, '&');
	}
}
