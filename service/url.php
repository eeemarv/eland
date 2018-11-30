<?php

namespace service;

use service\this_group;
use service\groups;
use service\alert;

class url
{
	protected $this_group;
	protected $groups;
	protected $alert;
	protected $rootpath;

	public function __construct(this_group $this_group, groups $groups, alert $alert,
		string $rootpath, string $protocol, array $s_user_params_own_group, string $s_id,
		string $s_schema)
	{
		$this->this_group = $this_group;
		$this->groups = $groups;
		$this->alert = $alert;
		$this->rootpath = $rootpath;
	}

	public function get($entity = 'messages', $params = [], $sch = false)
	{
		if ($this->alert->is_set())
		{
			$params['a'] = '1';
		}

		$params = array_merge($params, $this->get_session_query_param($sch));

		$params = http_build_query($params);

		$params = ($params) ? '?' . $params : '';

		$path = ($sch) ? $this->protocol . $this->groups->get_host($sch) . '/' : $this->rootpath;

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
