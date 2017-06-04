<?php

namespace service;

use service\this_group;
use service\config;

class access_control
{
	private $this_group;
	private $type_template;

	private $acc_ary = [
		'admin'	=> [
			'level'	=> 0,
			'label'	=> 'admin',
			'class'	=> 'info',
		],
		'users'	=> [
			'level'	=> 1,
			'label'	=> 'leden',
			'class'	=> 'warning',
		],
		'interlets'	=> [
			'level'	=> 2,
			'label'	=> 'interlets',
			'class'	=> 'success',
		],
	];

	private $acc_ary_search = [
		0 => 'admin',
		1 => 'users',
		2 => 'interlets',
	];

	private $input_ary = [
		'admin'	=> 'admin',
		'users'	=> 'users',
		'interlets'	=> 'interlets',
	];

	private $label_ary = [
		'admin'	=> 'admin',
		'users'	=> 'users',
		'interlets' => 'interlets',
	];

	/**
	 *
	 */

	public function __construct(this_group $this_group, config $config)
	{
		$this->this_group = $this_group;
		$this->config = $config;

		if (!$this->config->get('template_lets') || !$this->config->get('interlets_en'))
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
		global $access_level;

		$level = $this->get_level($role_or_level);

		return $level >= $access_level;
	}

	/**
	 *
	 */

	public function get_visible_ary()
	{
		global $access_level;

		$ary = [];

		foreach ($this->acc_ary_search as $lvl => $role)
		{
			if ($access_level <= $lvl)
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

	public function get_label($access = 'admin', $size = 'xs')
	{
		if (isset($this->acc_ary_search[$access]))
		{
			$access = $this->acc_ary_search[$access];
		}

		$acc = $this->acc_ary[$this->label_ary[$access]];

		return '<span class="label label-' . $acc['class'] . ' label-' . $size . '">' . $acc['label'] . '</span>';
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

	/**
	 *
	 */

	public function get_post_error($name = 'access')
	{
		if ($this->acc_ary[$_POST[$name]])
		{
			return false;
		}

		return 'Kies een zichtbaarheid.';
	}

	/*
	 *
	 */

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
		$out .= '<label for="' . $name . '" class="col-sm-2 control-label">' . $label . '</label>';
		$out .= '<div class="col-sm-10"';
		$out .= ($access_cache_id) ? ' data-access-cache-id="' . $this->this_group->get_schema() . '_' . $access_cache_id . '"' : '';
		$out .= ' id="' . $name . '">';

		foreach ($acc_ary as $key => $ary)
		{
			$out .= '<label class="radio-inline">';
			$out .= '<input type="radio" name="' . $name . '"';
			$out .= ($key === $selected) ? ' checked="checked"' : '';
			$out .= ' value="' . $key . '" required> ';
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
