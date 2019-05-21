<?php

namespace render;

class heading
{
	protected $str = '';
	protected $fa;
	protected $btn_filter = false;
	protected $filtered = false;

	const BTN_FILTER = [
		'<div class="pull-right">',
		'&nbsp;<button class="btn btn-default hidden-xs" ',
		'title="Filters" ',
		'data-toggle="collapse" data-target="#filter"',
		'<span class="hidden-xs hidden-sm"> ',
		'Filters</span></button>',
		'</div>',
	];

	public function get():string
	{
		$out = isset($this->fa) ? '<i class="fa fa-' . $this->fa . '"></i>&nbsp;' : '';
		$out .= $this->str;
		$out .= $this->filtered ? '&nbsp;<small>Gefilterd</small>' : '';
		$out .= $this->btn_filter ? implode('', self::BTN_FILTER) : '';

		return $out;
	}

	public function get_h1():string
	{
		return '<h1>' . $this->get() . '</h1>';
	}

	public function get_h3():string
	{
		return '<h3>' . $this->get() . '</h3>';
	}

	public function add_inline_btn(string $str):void
	{
		$this->str .= '<span class="inline-buttons">';
		$this->str .= $str;
		$this->str .= '</span>';
	}

	public function fa(string $fa):void
	{
		$this->fa = $fa;
	}

    public function add(
		string $str
	):void
    {
		$this->str .= $str;
	}

	public function add_filtered(bool $filtered):void
	{
		$this->filtered = $filtered;
	}

	public function btn_filter():void
	{
		$this->btn_filter = true;
	}
}
