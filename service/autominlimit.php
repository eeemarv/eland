<?php

namespace service;

use service\xdb;
use Monolog\Logger;
use Doctrine\DBAL\Connection as db;
use service\this_group;
use service\config;
use service\user_cache;

class autominlimit
{
	private $monolog;
	private $xdb;
	private $db;
	private $this_group;
	private $config;
	private $user_cache;

	private $exclusive;
	private $trans_exclusive;
	private $enabled = false;
	private $trans_percentage;
	private $group_minlimit;
	private $schema;

	public function __construct(Logger $monolog, xdb $xdb, db $db,
		this_group $this_group, config $config, user_cache $user_cache)
	{
		$this->monolog = $monolog;
		$this->xdb = $xdb;
		$this->db = $db;
		$this->this_group = $this_group;
		$this->user_cache = $user_cache;
		$this->config = $config;
	}

	public function init(string $schema = '')
	{
		$this->schema = $schema === '' ? $this->this_group->get_schema() : $schema;

		$row = $this->xdb->get('setting', 'autominlimit', $this->schema);

		$a = $row['data'];

		$exclusive = explode(',', $a['exclusive']);
		$trans_exclusive = explode(',', $a['trans_exclusive']);

		array_walk($exclusive, function(&$val){ return strtolower(trim($val)); });
		array_walk($trans_exclusive, function(&$val){ return strtolower(trim($val)); });

		$this->exclusive = array_fill_keys($exclusive, true);
		$this->trans_exclusive = array_fill_keys($trans_exclusive, true);

		$this->enabled = $a['enabled'];
		$this->trans_percentage = $a['trans_percentage'];

		$this->group_minlimit = $this->config->get('minlimit', $this->schema);

		return $this;
	}

	public function process(int $from_id, int $to_id, int $amount)
	{
		if (!$this->enabled)
		{
			$this->monolog->debug('autominlimit not enabled', ['schema' => $this->schema]);
			return;
		}

		if (!$this->trans_percentage)
		{
			$this->monolog->debug('autominlimit percentage is zero.', ['schema' => $this->schema]);
			return;
		}

		$user = $this->user_cache->get($to_id, $this->schema);

		if (!$user || !is_array($user))
		{
			$this->monolog->debug('autominlimit: to user not found', ['schema' => $this->schema]);
			return;
		}

		if ($user['status'] != 1)
		{
			$this->monolog->debug('autominlimit: to user not active. ' .
				link_user($user, $this->schema, false), ['schema' => $this->schema]);
			return;
		}

		if ($user['minlimit'] === '')
		{
			$this->monolog->debug('autominlimit: to user has no minlimit. ' .
				link_user($user, $this->schema, false), ['schema' => $this->schema]);
			return;
		}

		if ($this->group_minlimit !== '' && $user['minlimit'] < $this->group_minlimit)
		{
			$this->monolog->debug('autominlimit: to user minlimit is lower than group minlimit. ' .
				link_user($user, $this->schema, false), ['schema' => $this->schema]);
			return;
		}

		$from_user = $this->user_cache->get($from_id, $this->schema);

		if (!$from_user || !is_array($from_user))
		{
			$this->monolog->debug('autominlimit: from user not found.', ['schema' => $this->schema]);
			return;
		}

		if (!$from_user['letscode'])
		{
			$this->monolog->debug('autominlimit: from user has no letscode.', ['schema' => $this->schema]);
			return;
		}

		$extract = round(($this->trans_percentage / 100) * $amount);

		if (!$extract)
		{
			$debug = 'autominlimit: (extract = 0) ';
			$debug .= 'no new minlimit for user ' . link_user($user, $this->schema, false);
			$this->monolog->debug($debug, ['schema' => $this->schema]);
			return;
		}

		$new_minlimit = $user['minlimit'] - $extract;

		if ($this->group_minlimit !== '' && $new_minlimit <= $this->group_minlimit)
		{
			$this->xdb->set('autominlimit', $to_id, ['minlimit' => '', 'erased' => true], $this->schema);
			$this->db->update($this->schema . '.users', ['minlimit' => -999999999], ['id' => $to_id]);
			$this->user_cache->clear($to_id, $this->schema);

			$debug = 'autominlimit: minlimit reached group minlimit, ';
			$debug .= 'individual minlimit erased for user ' . link_user($user, $this->schema, false);
			$this->monolog->debug($debug, ['schema' => $this->schema]);
			return;
		}

		$this->xdb->set('autominlimit', $to_id, ['minlimit' => $new_minlimit]);

		$this->db->update($this->schema . '.users', ['minlimit' => $new_minlimit], ['id' => $to_id]);

		$this->user_cache->clear($to_id, $this->schema);

		$this->monolog->info('autominlimit: new minlimit : ' . $new_minlimit .
			' for user ' . link_user($user, $this->schema, false, true), ['schema' => $this->schema]);

		return;
	}
}
