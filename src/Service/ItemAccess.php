<?php declare(strict_types=1);

namespace App\Service;

use App\Service\Assets;
use App\Cnst\AccessCnst;

class ItemAccess
{
	protected $assets;
	protected $pp_schema;
	protected $pp_role;
	protected $intersystem_en;

	public function __construct(
		Assets $assets,
		string $pp_schema,
		string $pp_role,
		bool $intersystem_en
	)
	{
		$this->assets = $assets;
		$this->pp_schema = $pp_schema;
		$this->pp_role = $pp_role;
		$this->intersystem_en = $intersystem_en;
	}

	public function is_visible(string $access):bool
	{
		if ($access === 'anonymous')
		{
			if ($this->pp_role === 'anonymous')
			{
				return true;
			}

			return false;
		}

		if ($this->pp_role === 'admin')
		{
			return true;
		}
		else if ($this->pp_role === 'user')
		{
			if ($access === 'user' || $access === 'guest')
			{
				return true;
			}
		}
		else if ($this->pp_role === 'guest')
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
		if (!isset(AccessCnst::FROM_XDB[$access_xdb]))
		{
			return false;
		}

		return $this->is_visible(AccessCnst::FROM_XDB[$access_xdb]);
	}

	public function is_visible_flag_public(int $access_flag_public):bool
	{
		if (!isset(AccessCnst::FROM_FLAG_PUBLIC[$access_flag_public]))
		{
			return false;
		}

		return $this->is_visible(AccessCnst::FROM_FLAG_PUBLIC[$access_flag_public]);
	}

	public function is_visible_local(bool $local):bool
	{
		return $this->is_visible($local ? 'user' : 'guest');
	}

	public function get_visible_ary():array
	{
		$ary = AccessCnst::ARY;

		if (!isset($ary[$this->pp_role]))
		{
			return [];
		}

		if ($this->pp_role !== 'admin')
		{
			unset($ary['admin']);
		}

		if ($this->pp_role === 'guest')
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
			$ary[] = AccessCnst::TO_XDB[$role];
		}

		return $ary;
	}

	public function get_visible_ary_flag_public():array
	{
		$ary = [];

		foreach ($this->get_visible_ary() as $role)
		{
			$ary[] = AccessCnst::TO_FLAG_PUBLIC[$role];
		}

		return $ary;
	}

	public function get_label(
		string $access
	):string
	{
		$access = $access === 'guest' && !$this->intersystem_en ? 'user' : $access;

		$out = '<span class="btn btn-';
		$out .= AccessCnst::LABEL[$access]['class'];
		$out .= '">';
		$out .= AccessCnst::LABEL[$access]['lbl'];
		$out .= '</span>';

		return $out;
	}

	public function get_label_xdb(string $access_xdb):string
	{
		return $this->get_label(AccessCnst::FROM_XDB[$access_xdb]);
	}

	public function get_label_flag_public(int $flag_public):string
	{
		return $this->get_label(AccessCnst::FROM_FLAG_PUBLIC[$flag_public]);
	}

	public function get_value_from_flag_public($flag_public):string
	{
		if (!isset($flag_public) || !isset(AccessCnst::FROM_FLAG_PUBLIC[$flag_public]))
		{
			return '';
		}

		return AccessCnst::FROM_FLAG_PUBLIC[$flag_public];
	}

	public function get_radio_buttons(
		string $name,
		string $selected = '',
		string $cache_id = '',
		bool $omit_admin = false,
		string $label = 'Zichtbaarheid'
	):string
	{
		$ary = AccessCnst::ARY;

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
			$out .= $this->pp_schema . '_' . $cache_id . '"';
		}

		$out .= '>';

		foreach ($ary as $key)
		{
			$out .= '<label class="radio-inline">';
			$out .= '<input type="radio" name="' . $name . '"';
			$out .= $key === $selected ? ' checked="checked"' : '';
			$out .= ' value="' . $key . '" ';
			$out .= 'id="' . $name . '" ';
			$out .= 'required> ';
			$out .= $this->get_label($key);
			$out .= '</label>';
		}

		$out .= '</div>';
		$out .= '</div>';

		return $out;
	}
}
