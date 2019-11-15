<?php declare(strict_types=1);

namespace App\Service;

use App\Service\AssetsService;
use App\Cnst\AccessCnst;

class ItemAccessService
{
	protected $assets_service;
	protected $config_service;
	protected $pp;

	public function __construct(
		AssetsService $assets_service,
		PageParamsService $pp,
		ConfigService $config_service
	)
	{
		$this->assets_service = $assets_service;
		$this->pp = $pp;
		$this->config_service = $config_service;
	}

	public function is_visible(string $access):bool
	{
		if ($access === 'anonymous')
		{
			if ($this->pp->is_anonymous())
			{
				return true;
			}

			return false;
		}

		if ($this->pp->is_admin())
		{
			return true;
		}
		else if ($this->pp->is_user())
		{
			if ($access === 'user' || $access === 'guest')
			{
				return true;
			}
		}
		else if ($this->pp->is_guest())
		{
			if ($this->config_service->get_intersystem_en($this->pp->schema())
				&& $access === 'guest')
			{
				return true;
			}
		}

		return false;
	}

	public function get_visible_ary_for_role(string $role):array
	{
		return array_keys(AccessCnst::ACCESS[$role]);
	}

	public function get_visible_ary_for_page():array
	{
		return $this->get_visible_ary_for_role($this->pp->role());
	}

	public function is_visible_xdb(string $access_xdb):bool
	{
		if (!isset(AccessCnst::FROM_XDB[$access_xdb]))
		{
			return false;
		}

		return $this->is_visible(AccessCnst::FROM_XDB[$access_xdb]);
	}

	public function is_visible_local(bool $local):bool
	{
		return $this->is_visible($local ? 'user' : 'guest');
	}

	public function get_visible_ary():array
	{
		$ary = AccessCnst::ARY;

		if (!isset($ary[$this->pp->role()]))
		{
			return [];
		}

		if (!$this->pp->is_admin())
		{
			unset($ary['admin']);
		}

		if ($this->pp->is_guest())
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

	public function get_label(
		string $access
	):string
	{
		$access = $access === 'guest'
			&& !$this->config_service->get_intersystem_en($this->pp->schema()) ? 'user' : $access;

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

	public function get_radio_buttons(
		string $name,
		string $selected = '',
		string $cache_id = '',
		bool $omit_admin = false,
		string $label = 'Zichtbaarheid'
	):string
	{
		$ary = AccessCnst::ARY;

		if (!$this->config_service->get_intersystem_en($this->pp->schema()))
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
			$this->assets_service->add(['access_input_cache.js']);

			$out .= ' data-access-cache-id="';
			$out .= $this->pp->schema() . '_' . $cache_id . '"';
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
