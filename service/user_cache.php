<?php

namespace service;

use service\xdb;
use Doctrine\DBAL\Connection as db;
use Predis\Client as predis;

class user_cache
{
	protected $db;
	protected $xdb;
	protected $predis;
	protected $ttl = 2592000;
	protected $is_cli;

	protected $local;

	public function __construct(db $db, xdb $xdb, predis $predis)
	{
		$this->db = $db;
		$this->xdb = $xdb;
		$this->predis = $predis;

		$this->is_cli = php_sapi_name() === 'cli' ? true : false;
	}

	public function clear(int $id, string $schema):void
	{
		if (!$id)
		{
			return;
		}

		$redis_key = $schema . '_user_' . $id;

		$this->predis->del($redis_key);
		unset($this->local[$schema][$id]);

		return;
	}

	public function get(int $id, string $schema):array
	{
		if (!$id)
		{
			return [];
		}

		$redis_key = $schema . '_user_' . $id;

		if (isset($this->local[$schema][$id]) && !$this->is_cli)
		{
			return $this->local[$schema][$id];
		}

		if ($this->predis->exists($redis_key))
		{
			return $this->local[$schema][$id] = unserialize($this->predis->get($redis_key));
		}

		$user = $this->read_from_db($id, $schema);

		if (isset($user))
		{
			$this->predis->set($redis_key, serialize($user));
			$this->predis->expire($redis_key, $this->ttl);
			$this->local[$schema][$id] = $user;
		}

		return $user;
	}

	protected function read_from_db(int $id, string $schema):array
	{
		$user = $this->db->fetchAssoc('select *
			from ' . $schema . '.users
			where id = ?', [$id]);

		if (!is_array($user))
		{
			return [];
		}

		// hack eLAS compatibility (in eLAND limits can be null)
		$user['minlimit'] = $user['minlimit'] == -999999999 ? '' : $user['minlimit'];
		$user['maxlimit'] = $user['maxlimit'] == 999999999 ? '' : $user['maxlimit'];

		$row = $this->xdb->get('user_fullname_access',
			$id, $schema);

		if ($row)
		{
			$user += ['fullname_access' => $row['data']['fullname_access']];
		}
		else
		{
			$user += ['fullname_access' => 'admin'];

			$this->xdb->set('user_fullname_access',
				$id, ['fullname_access' => 'admin'], $schema);
		}

		return $user;
	}

	/**
	 * for periodic process for when cache gets out sync
	 */
	public function sync(int $id, string $schema):void
	{
		$user = $this->read_from_db($id, $schema);

		if (!count($user))
		{
			return;
		}

		$user = serialize($user);

		$redis_key = $schema . '_user_' . $id;

		if ($this->predis->exists($redis_key))
		{
			if ($this->predis->get($redis_key) === $user)
			{
				return;
			}
		}

		$this->predis->set($redis_key, $user);
		$this->predis->expire($redis_key, $this->ttl);
		unset($this->local[$schema][$id]);
	}
}
