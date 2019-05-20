<?php

namespace render;

class h1
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
		if ($this->str === '')
		{
			return '';
		}

		$out = '<h1>';
		$out .= isset($this->fa) ? '<i class="fa fa-' . $this->fa . '"></i>&nbsp;' : '';
		$out .= $this->str;
		$out .= $this->filtered ? '&nbsp;<small>Gefilterd</small>' : '';
		$out .= $this->btn_filter ? implode('', self::BTN_FILTER) : '';
		$out .= '</h1>';

		return $out;
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
