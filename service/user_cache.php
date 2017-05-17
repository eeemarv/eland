<?php

namespace service;

use service\this_group;
use service\xdb;
use Doctrine\DBAL\Connection as db;
use Predis\Client as predis;

class user_cache
{
	private $db;
	private $xdb;
	private $predis;
	private $this_group;

	private $local;

	public function __construct(db $db, xdb $xdb, predis $predis, this_group $this_group)
	{
		$this->db = $db;
		$this->xdb = $xdb;
		$this->predis = $predis;
		$this->this_group = $this_group;
	}

	public function clear(int $id, string $schema = '')
	{
		if (!$id)
		{
			return;
		}

		$s = $schema ?: $this->this_group->get_schema();

		$redis_key = $s . '_user_' . $id;


		$this->predis->del($redis_key);
		unset($this->local[$s][$id]);

		return;
	}

	public function get(int $id, string $schema = '')
	{
		if (!$id)
		{
			return [];
		}

		$s = $schema ?: $this->this_group->get_schema();

		$redis_key = $s . '_user_' . $id;

		if (isset($this->local[$s][$id]))
		{
			return $this->local[$s][$id];
		}

		if ($this->predis->exists($redis_key))
		{
			return $this->local[$s][$id] = unserialize($this->predis->get($redis_key));
		}

		$user = $this->db->fetchAssoc('select * from ' . $s . '.users where id = ?', [$id]);

		if (!is_array($user))
		{
			return [];
		}

		// hack eLAS compatibility (in eLAND limits can be null)
		$user['minlimit'] = $user['minlimit'] == -999999999 ? '' : $user['minlimit'];
		$user['maxlimit'] = $user['maxlimit'] == 999999999 ? '' : $user['maxlimit'];

		$row = $this->xdb->get('user_fullname_access', $id, $s);

		if ($row)
		{
			$user += ['fullname_access' => $row['data']['fullname_access']];
		}
		else
		{
			$user += ['fullname_access' => 'admin'];

			$this->xdb->set('user_fullname_access', $id, ['fullname_access' => 'admin'], $s);
		}

		if (isset($user))
		{
			$this->predis->set($redis_key, serialize($user));
			$this->predis->expire($redis_key, 2592000);
			$this->local[$s][$id] = $user;
		}

		return $user;
	}
}
