<?php declare(strict_types=1);

namespace App\Render;

class HeadingRender
{
	protected string $str = '';
	protected string $str_sub = '';
	protected string $fa;
	protected bool $btn_filter = false;
	protected bool $filtered = false;

	const BTN_FILTER = [
		'<div class="pull-right">',
		'&nbsp;<button class="btn btn-default hidden-xs border border-secondary" ',
		'title="Filters" ',
		'data-toggle="collapse" data-target="#filter">',
		'<i class="fa fa-caret-down"></i>&nbsp;',
		'Filters</button>',
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

	public function get_sub():string
	{
		return $this->str_sub ? '<h2>' . $this->str_sub . '</h2>' : '';
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

    public function add(string $str):void
    {
		$this->str .= htmlspecialchars($str, ENT_QUOTES);
	}

    public function add_raw(string $str):void
    {
		$this->str .= $str;
	}

    public function add_sub(string $str_sub):void
    {
		$this->str_sub .= htmlspecialchars($str_sub, ENT_QUOTES);
	}

    public function add_sub_raw(string $str_sub):void
    {
		$this->str_sub .= $str_sub;
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
