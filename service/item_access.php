<?php

namespace service;

use service\assets;
use cnst\access as cnst_access;

class item_access
{
	protected $assets;
	protected $tschema;
	protected $s_role;
	protected $intersystem_en;

	public function __construct(
		assets $assets,
		string $tschema,
		string $s_role,
		bool $intersystem_en
	)
	{
		$this->assets = $assets;
		$this->tschema = $tschema;
		$this->s_role = $s_role;
		$this->intersystem_en = $intersystem_en;
	}

	public function is_visible(string $access):bool
	{
		if ($this->s_role === 'admin')
		{
			return true;
		}
		else if ($this->s_role === 'user')
		{
			if ($access === 'user' || $access === 'guest')
			{
				return true;
			}

			return false;
		}
		else if ($this->s_role === 'guest')
		{
			if ($this->intersystem_en && $access === 'guest')
			{
				return true;
			}
		}

		return false;
	}

	public function is_visible_xdb(string $access_xdb):bool
	{
		if (!isset(cnst_access::FROM_XDB[$access_xdb]))
		{
			return false;
		}

		return $this->is_visible(cnst_access::FROM_XDB[$access_xdb]);
	}

	public function is_visible_flag_public(int $access_flag_public):bool
	{
		if (!isset(cnst_access::FROM_FLAG_PUBLIC[$access_flag_public]))
		{
			return false;
		}

		return $this->is_visible(cnst_access::FROM_FLAG_PUBLIC[$access_flag_public]);
	}

	public function is_visible_local(bool $local):bool
	{
		return $this->is_visible($local ? 'user' : 'guest');
	}

	public function get_visible_ary():array
	{
		$ary = cnst_access::ARY;

		if (!isset($ary[$this->s_role]))
		{
			return [];
		}

		if ($this->s_role !== 'admin')
		{
			unset($ary['admin']);
		}

		if ($this->s_role === 'guest')
		{
			unset($ary['user']);
		}

		return $ary;
	}

	public function get_visible_ary_xdb():array
	{
		$ary = [];

		foreach ($this->get_visible_ary() as $role)
		{
			$ary[] = cnst_access::TO_XDB[$role];
		}

		return $ary;
	}

	public function get_visible_ary_flag_public():array
	{
		$ary = [];

		foreach ($this->get_visible_ary() as $role)
		{
			$ary[] = cnst_access::TO_FLAG_PUBLIC[$role];
		}

		return $ary;
	}

	public function get_label(
		string $access
	)
	{
		$access = $access === 'guest' && !$this->intersystem_en ? 'user' : $access;

		$out = '<span class="btn btn-';
		$out .= cnst_access::LABEL[$access]['class'];
		$out .= ' btn-md';
		$out .= '">';
		$out .= cnst_access::LABEL[$access]['lbl'];
		$out .= '</span>';

		return $out;
	}

	public function get_label_xdb(string $access_xdb):string
	{
/*
		if (!isset(cnst_access::FROM_XDB[$access_xdb]))
		{
			return '';
		}
*/
		return $this->get_label(cnst_access::FROM_XDB[$access_xdb]);
	}

	public function get_label_flag_public(int $flag_public):string
	{
/*
		if (!isset(cnst_access::FROM_FLAG_PUBLIC[$flag_public]))
		{
			return '';
		}
*/
		return $this->get_label(cnst_access::FROM_FLAG_PUBLIC[$flag_public]);
	}

	public function get_value_from_flag_public($flag_public):string
	{
		if (!isset($flag_public) || !isset(cnst_access::FROM_FLAG_PUBLIC[$flag_public]))
		{
			return '';
		}

		return cnst_access::FROM_FLAG_PUBLIC[$flag_public];
	}

	public function get_radio_buttons(
		string $name,
		string $selected = '',
		string $cache_id = '',
		bool $omit_admin = false,
		string $label = 'Zichtbaarheid'
	)
	{
		$ary = cnst_access::ARY;

		if (!$this->intersystem_en)
		{
			unset($ary['guest']);
			$selected = $selected === 'guest' ? 'user' : $selected;
		}

		if ($omit_admin)
		{
			unset($ary['admin']);
			$selected = $selected === 'admin' ? 'user' : $selected;
		}

		if (count($ary) === 0)
		{
			return '';
		}
		else if (count($ary) === 1)
		{
			return '<input type="hidden" name="' . $name . '" value="user">';
		}

		$out = '<div class="form-group">';
		$out .= '<label for="' . $name;
		$out .= '" class="control-label">';
		$out .= $label;
		$out .= '</label>';
		$out .= '<div';

		if ($cache_id)
		{
			$this->assets->add(['access_input_cache.js']);

			$out .= ' data-access-cache-id="';
			$out .= $this->tschema . '_' . $cache_id . '"';
		}

		$out .= ' id="' . $name . '">';

		foreach ($ary as $key)
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
