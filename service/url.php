<?php

namespace service;

use service\this_group;
use service\groups;

class url
{
	protected $this_group;
	protected $groups;
	protected $rootpath;

	public function __construct(
		this_group $this_group,
		groups $groups,
		string $rootpath,
		string $protocol
	)
	{
		$this->this_group = $this_group;
		$this->groups = $groups;
		$this->protocol = $protocol;
		$this->rootpath = $rootpath;
	}


	/**
	 * get link with schema and role required.
	 */

	public function get_link(
		string $route,
		array $params,
		string $label,
		array $attr = [],
		array $extra = []
	):string
	{
		$out = '<a href="';
		$out .= $this->get($route, $params);
		$out .= '"';

		foreach ($attr as $name => $val)
		{
			$out .= ' ' . $name . '="' . $val . '"';
		}

		$out .= '>';

		if (count($extra))
		{
			if (isset($extra['fa']))
			{
				$out .= '<i class="fa fa-' . $extra['fa'] .'"></i>';
			}

			if ($label)
			{
				if (isset($extra['collapse']))
				{
					$out .= '<span class="hidden-xs hidden-sm"> ';
					$out .= htmlspecialchars($label, ENT_QUOTES);
					$out .= '</span>';
				}
				else
				{
					$out .= ' ';
					$out .= htmlspecialchars($label, ENT_QUOTES);
				}
			}
		}
		else
		{
			$out .= htmlspecialchars($label, ENT_QUOTES);
		}

		$out .= '</a>';

		return $out;
	}

	public function get(
		string $route,
		array $params
	):string
	{
		if (!isset($params['schema']))
		{
			throw \Exception('no schema set for route ' .
				$route . ' with params ' . json_encode($params));
		}

		if (!isset($params['access']))
		{
			throw \Exception('no access set for route ' .
				$route . ' with params ' . json_encode($params));
		}

		$params = http_build_query($params);

		$params = $params ? '?' . $params : '';

		$path = $sch ? $app['protocol'] . $app['groups']->get_host($schema) . '/' : $rootpath;

		return $path . $entity . '.php' . $params;
	}

	/**
	 * get session query param
	 */
	function get_session_query_param($sch = false)
	{
		global $p_role, $p_user, $p_schema, $access_level;
		global $s_user_params_own_group, $s_id, $s_schema;

		static $ary;

		if ($sch)
		{
			if ($sch == $this->s_schema)
			{
				return  $this->s_user_params_own_group;
			}

			if ($this->s_schema)
			{
				$param_ary = ['r' => 'guest', 'u' => $this->s_id, 's' => $this->s_schema];

				return $param_ary;
			}

			return ['r' => 'guest'];
		}

		if (isset($ary))
		{
			return $ary;
		}

		$ary = [];

		if ($p_role != 'anonymous')
		{
			$ary['r'] = $p_role;
			$ary['u'] = $p_user;

			if ($access_level == 2 && $p_schema)
			{
				$ary['s'] = $p_schema;
			}
		}

		return $ary;
	}
}
