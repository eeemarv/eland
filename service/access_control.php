<?php

namespace service;

use service\config;

class access_control
{
	protected $config;
	protected $schema;
	protected $access_level;
	protected $type_template;

	protected $acc_ary = [
		'admin'	=> [
			'level'	=> 0,
			'label'	=> 'admin',
			'class'	=> 'info',
		],
		'users'	=> [
			'level'	=> 1,
			'label'	=> 'leden',
			'class'	=> 'default',
		],
		'interlets'	=> [
			'level'	=> 2,
			'label'	=> 'interSysteem',
			'class'	=> 'warning',
		],
	];

	protected $acc_ary_search = [
		0 => 'admin',
		1 => 'users',
		2 => 'interlets',
	];

	protected $input_ary = [
		'admin'	=> 'admin',
		'users'	=> 'users',
		'interlets'	=> 'interlets',
	];

	protected $label_ary = [
		'admin'	=> 'admin',
		'users'	=> 'users',
		'interlets' => 'interlets',
	];

	public function __construct(
		string $schema,
		config $config,
		string $access_level
	)
	{
		$this->config = $config;
		$this->schema = $schema;
		$this->access_level = $access_level;

		if (!$this->config->get('template_lets', $this->schema)
			|| !$this->config->get('interlets_en', $this->schema))
		{
			unset($this->input_ary['interlets']);
			$this->label_ary['interlets'] = 'users';
		}
	}

	/*
	 *
	 */

	public function is_visible($role_or_level)
	{
		$level = $this->get_level($role_or_level);

		return $level >= $this->access_level;
	}

	/**
	 *
	 */

	public function get_visible_ary()
	{
		$ary = [];

		foreach ($this->acc_ary_search as $lvl => $role)
		{
			if ($this->access_level <= $lvl)
			{
				$ary[] = $role;
			}
		}

		return $ary;
	}

	/*
	 *
	 */

	public function get_role($access)
	{
		if (isset($this->acc_ary_search[$access]))
		{
			return $this->acc_ary_search[$access];
		}

		return $access;
	}

	/*
	 *
	 */

	public function get_level($access)
	{
		if (isset($this->acc_ary[$access]))
		{
			return $this->acc_ary[$access]['level'];
		}

		return $access;
	}

	/*
	 *
	 */

	public function get_label($access = 'admin')
	{
		if (isset($this->acc_ary_search[$access]))
		{
			$access = $this->acc_ary_search[$access];
		}

		$acc = $this->acc_ary[$this->label_ary[$access]];

		$ret = '<span class="btn btn-';
		$ret .= $acc['class'] . ' btn-xs';
		$ret .= '">' . $acc['label'];
		$ret .= '</span>';

		return $ret;
	}

	/*
	 *
	 */

	public function get_post_value($name = 'access')
	{
		if (!isset($_POST[$name]))
		{
			return false;
		}

		if (isset($this->acc_ary[$_POST[$name]]))
		{
			return $this->acc_ary[$this->input_ary[$_POST[$name]]]['level'];
		}

		return false;
	}

	public function get_post_error($name = 'access')
	{
		if ($this->acc_ary[$_POST[$name]])
		{
			return false;
		}

		return 'Kies een zichtbaarheid.';
	}

	public function get_radio_buttons($access_cache_id = false, $value = false, $omit_access = false, $name = 'access', $size = 'xs', $label = 'Zichtbaarheid')
	{
		$acc_ary = $this->acc_ary;

		if ($value === false)
		{
			$selected = false;
		}
		else if (isset($this->acc_ary[$this->input_ary[$value]]))
		{
			$selected = $value;
		}
		else if (isset($this->input_ary[$this->acc_ary_search[$value]]))
		{
			$selected = $this->input_ary[$this->acc_ary_search[$value]];
		}
		else if ($value === 2 || $value === 'interlets')
		{
			$selected = 'users';
		}
		else
		{
			$selected = false;
		}

		if ($omit_access)
		{
			if (!is_array($omit_access))
			{
				$omit_access = [$omit_access];
			}

			foreach ($omit_access as $omit)
			{
				unset($acc_ary[$omit]);
			}
		}

		$acc_ary = array_intersect_key($acc_ary, $this->input_ary);

		if (count($acc_ary) === 0)
		{
			return '';
		}
		else if (count($acc_ary) === 1)
		{
			return '<input type="hidden" name="' . $name . '" value="' . key($acc_ary) . '">';
		}

		$out = '<div class="form-group">';
		$out .= '<label for="' . $name . '" class="control-label">';
		$out .= $label . '</label>';
		$out .= '<div ';

		if ($access_cache_id)
		{
			$out .= ' data-access-cache-id="';
			$out .= $this->schema . '_' . $access_cache_id . '"';
		}

		$out .= ' id="' . $name . '">';

		foreach ($acc_ary as $key => $ary)
		{
			$out .= '<label class="radio-inline">';
			$out .= '<input type="radio" name="' . $name . '"';
			$out .= $key === $selected ? ' checked="checked"' : '';
			$out .= ' value="' . $key . '" ';
			$out .= 'required> ';
			$out .= '<span class="btn btn-' . $ary['class'] . ' btn-' . $size . '">';
			$out .= $ary['label'];
			$out .= '</span>';
			$out .= '</label>';
		}

		$out .= '</div>';
		$out .= '</div>';

		return $out;
	}
}
