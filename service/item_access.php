<?php

namespace render;

use Symfony\Component\HttpFoundation\Request;
use service\assets;
use cnst\access as cnst_access;

class item_access
{
	protected $request;
	protected $s_role;
	protected $intersystem_en;

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
		Request $request,
		string $s_role,
		bool $intersystem_en
	)
	{
		$this->request = $request;
		$this->s_role = $s_role;
		$this->intersystem_en = $intersystem_en;

		if (!$this->intersystem_en)
		{
			unset($this->input_ary['interlets']);
			$this->label_ary['interlets'] = 'users';
		}
	}

	public function is_visible($role_or_level)
	{
		$level = $this->get_level($role_or_level);

		return $level >= $this->access_level;
	}

	public function get_access_ary():array
	{
		$ary = cnst_access::ARY;

		return $ary;
	}

	public function get_role($access)
	{
		if (isset($this->acc_ary_search[$access]))
		{
			return $this->acc_ary_search[$access];
		}

		return $access;
	}

	public function get_level($access)
	{
		if (isset($this->acc_ary[$access]))
		{
			return $this->acc_ary[$access]['level'];
		}

		return $access;
	}

	public function get_label(
		string $access
	)
	{
		$access = $access === 'guest' && !$this->intersystem_en ? 'user' : $access;

		$out = '<span class="btn btn-';
		$out .= cnst_access::LABEL[$access]['class'];
		$out .= ' btn-xs';
		$out .= '">';
		$out .= cnst_access::LABEL[$access]['lbl'];
		$out .= '</span>';

		return $out;
	}

	public function get_post(string $name = 'access'):string
	{
		return $this->request->request->get($name, '');
	}

	public function get_post_value(
		$name
	):string
	{
		return $this->request->request->get($name);


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

	public function get_post_error(string $name = 'access'):string
	{
		if (!$this->request->request->get($name))
		{
			return 'Kies een zichtbaarheid.';
		}

		return '';
	}

	public function get_radio_buttons(
		string $name,
		string $value,
		array $keys,
		string $schema,
		string $cache_id = '',
		string $label = 'Zichtbaarheid'
	)
	{
		if (count($keys) === 0)
		{
			return '';
		}
		else if (count($keys) === 1)
		{
			return '<input type="hidden" name="' . $name . '" value="' . key($acc_ary) . '">';
		}

		$out = '<div class="form-group">';
		$out .= '<label for="' . $name . '" class="control-label">';
		$out .= $label;
		$out .= '</label>';
		$out .= '<div';

		if ($cache_id)
		{
			$this->assets->add(['access_input_cache.js']);

			$out .= ' data-access-cache-id="';
			$out .= $schema . '_' . $cache_id . '"';
		}

		$out .= ' id="' . $name . '">';

		foreach ($keys as $key)
		{
			$out .= '<label class="radio-inline">';
			$out .= '<input type="radio" name="' . $name . '"';
			$out .= $key === $selected ? ' checked="checked"' : '';
			$out .= ' value="' . $key . '" ';
			$out .= 'required> ';
			$out .= $this->get_label($key);
			$out .= '</label>';
		}

		$out .= '</div>';
		$out .= '</div>';

		return $out;
	}
}
