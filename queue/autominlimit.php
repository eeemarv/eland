<?php

namespace eland\queue;

use eland\model\queue as queue_model;
use eland\model\queue_interface;
use eland\queue;
use eland\xdb;
use Monolog\Logger;
use Doctrine\DBAL\Connection as db;

class autominlimit extends queue_model implements queue_interface
{
	protected $queue;
	protected $monolog;
	protected $xdb;
	protected $db;

	public function __construct(queue $queue, Logger $monolog, xdb $xdb, db $db)
	{
		$this->queue = $queue;
		$this->monolog = $monolog;
		$this->xdb = $xdb;
		$this->db = $db;

		parent::__construct();
	}

	public function process(array $data)
	{
		$to_id = $data['to_id'];
		$from_id = $data['from_id'];
		$amount = $data['amount'];
		$sch = $data['schema'];

		if (!$to_id || !$from_id || !$amount || !$sch)
		{
			error_log('autominlimit exit: 1, to_id, from_id, amount or schema is missing.');
			return;
		}

		$user = readuser($to_id, false, $sch);
		$from_user = readuser($from_id, false, $sch);

		if (!$user
			|| !$amount
			|| !is_array($user)
			|| $user['status'] != 1
			|| !$from_user
			|| !is_array($from_user)
			|| !$from_user['letscode']
		)
		{
			error_log('autominlimit exit: 2 user status: ' . $user['status']);
			return;
		}

		if ($user['minlimit'] === '')
		{
			error_log('autominlimit exit: 3 user ' . link_user($user, $sch, false, true) .
				' has no individual minlimit');
			return;
		}

		$group_minlimit = readconfigfromdb('minlimit', $sch);

		if ($group_minlimit === '')
		{
			error_log('autominlimit exit: 3 no group minlimit');
			return;
		}

		if ($user['minlimit'] < $group_minlimit)
		{
			error_log('autominlimit exit: 4 individual minlimit of user ' .
				link_user($user, $sch, false, true) . ' ' . $user['minlimit'] . ' is lower than group minlimit ' .
				$group_minlimit);
			return;
		}

		$row = $this->xdb->get('setting', 'autominlimit', $sch);

		$a = $row['data'];

		$exclusive = explode(',', $a['exclusive']);
		$trans_exclusive = explode(',', $a['trans_exclusive']);

		array_walk($exclusive, function(&$val){ return strtolower(trim($val)); });
		array_walk($trans_exclusive, function(&$val){ return strtolower(trim($val)); });

		$exclusive = array_fill_keys($exclusive, true);
		$trans_exclusive = array_fill_keys($trans_exclusive, true);

		if (!is_array($a)
			|| !$a['enabled']
			|| !$a['trans_percentage']
			|| isset($exclusive[trim(strtolower($user['letscode']))])
			|| isset($trans_exclusive[trim(strtolower($from_user['letscode']))])
		)
		{
			$debug = 'autominlimit: ';
			$debug .= $a['enabled'] ? '' : '(not enabled) ';
			$debug .= 'no new minlimit for user ' . link_user($user, $sch, false);
			$this->monolog->debug($debug, ['schema' => $sch]);
			return;
		}

		$extract = round(($a['trans_percentage'] / 100) * $amount);

		if (!$extract)
		{
			$debug = 'autominlimit: (extract = 0) ';
			$debug .= 'no new minlimit for user ' . link_user($user, $sch, false);
			$this->monolog->debug($debug, ['schema' => $sch]);
			return;
		}

		$new_minlimit = $user['minlimit'] - $extract;

		error_log('group_minlimit : ' . $group_minlimit);
		error_log('new_minlimit : ' . $new_minlimit);

		if ($new_minlimit <= $group_minlimit)
		{
			$this->xdb->set('autominlimit', $to_id, ['minlimit' => $group_minlimit, 'erased' => true], $sch);
			$this->db->update($sch . '.users', ['minlimit' => -999999999], ['id' => $to_id]);
			readuser($to_id, true, $sch);

			$debug = 'autominlimit: minlimit reached group minlimit, ';
			$debug .= 'individual minlimit erased for user ' . link_user($user, $sch, false);
			$this->monolog->debug($debug, ['schema' => $sch]);
			return;
		}

		$this->xdb->set('autominlimit', $to_id, ['minlimit' => $new_minlimit], $sch);

		$this->db->update($sch . '.users', ['minlimit' => $new_minlimit], ['id' => $to_id]);

		readuser($to_id, true, $sch);

		$this->monolog->info('autominlimit: new minlimit : ' . $new_minlimit .
			' for user ' . link_user($user, $sch, false) . ' (id:' . $to_id . ') ', ['schema' => $sch]);
	}

	public function queue(array $data)
	{
		$this->monolog->error('the autominlimit queue is disabled.');
		return;

		if (!isset($data['schema']))
		{
			$this->monolog->debug('no schema set for autominlimit');
			return;
		}

		if (!isset($data['from_id']))
		{
			$this->monolog->debug('no from_id set for autominlimit');
			return;
		}

		if (!isset($data['to_id']))
		{
			$this->monolog->debug('no to_id set for autominlimit');
			return;
		}

		if (!isset($data['amount']))
		{
			$this->monolog->debug('no amount set for autominlimit');
			return;
		}

		$this->queue->set('autominlimit', $data);
	}

	public function get_interval()
	{
		return 10;
	}
}
