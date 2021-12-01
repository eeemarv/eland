<?php declare(strict_types=1);

namespace App\Service;

use App\Cnst\AccessCnst;
use App\Cnst\BulkCnst;

class ItemAccessService
{
	public function __construct(
		protected PageParamsService $pp,
		protected ConfigService $config_service
	)
	{
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

	public function is_visible_for_guest(string $access):bool
	{
		return in_array($access, ['guest', 'anonymous']);
	}

	public function get_visible_ary_for_role(string $role):array
	{
		return array_keys(AccessCnst::ACCESS[$role]);
	}

	public function get_visible_ary_for_page():array
	{
		return $this->get_visible_ary_for_role($this->pp->role());
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

	public function get_label(
		string $access
	):string
	{
		$access = $access === 'guest'
			&& !$this->config_service->get_intersystem_en($this->pp->schema()) ? 'user' : $access;

		$out = '<span class="btn btn-';
		$out .= AccessCnst::LABEL[$access]['class'];
		$out .= '" title="';
		$out .= AccessCnst::LABEL[$access]['title'];
		$out .= '">';
		$out .= AccessCnst::LABEL[$access]['lbl'];
		$out .= '</span>';

		return $out;
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
			$out .= ' data-access-cache-id="';
			$out .= $this->pp->schema() . '_' . $cache_id . '"';
		}

		$out .= '>';

		foreach ($ary as $key)
		{
			$attr = $key === $selected ? ' checked' : '';
			$attr .= ' required';

			$out .= strtr(BulkCnst::TPL_RADIO_INLINE, [
				'%name%'	=> $name,
				'%value%'	=> $key,
				'%attr%'	=> $attr,
				'%label%'	=> $this->get_label($key),
			]);
		}

		$out .= '</div>';
		$out .= '</div>';

		return $out;
	}
}
