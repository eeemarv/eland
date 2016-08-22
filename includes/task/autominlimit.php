<?php

namespace eland\task;

use eland\queue;
use Monolog\Logger;

class autominlimit
{
	protected $queue;
	protected $monolog;

	public function __construct(queue $queue, Logger $monolog)
	{
		$this->queue = $queue;
		$this->monolog = $monolog;
	}

	public function process(array $data)
	{
		$to_id = $q['to_id'];
		$from_id = $q['from_id'];
		$amount = $q['amount'];
		$sch = $q['schema'];

		if (!$to_id || !$from_id || !$amount || !$sch)
		{
			error_log('autominlimit 1');
			return;
		}

		$user = readuser($to_id, false, $sch);
		$from_user = readuser($from_id, false, $sch);

		if (!$user
			|| !$amount
			|| !is_array($user)
			|| !in_array($user['status'], [1, 2])
			|| !$from_user
			|| !is_array($from_user)
			|| !$from_user['letscode']
		)
		{
			error_log('autominlimit 2');
			return;
		}

		$row = $app['eland.xdb']->get('setting', 'autominlimit', $sch);

		$a = $row['data'];

		$new_user_time_treshold = time() - readconfigfromdb('newuserdays', $sch) * 86400;

		$user['status'] = ($new_user_time_treshold < strtotime($user['adate'] && $user['status'] == 1)) ? 3 : $user['status'];

		$inclusive = explode(',', $a['inclusive']);
		$exclusive = explode(',', $a['exclusive']);
		$trans_exclusive = explode(',', $a['trans_exclusive']);

		array_walk($inclusive, function(&$val){ return strtolower(trim($val)); });	
		array_walk($exclusive, function(&$val){ return strtolower(trim($val)); });
		array_walk($trans_exclusive, function(&$val){ return strtolower(trim($val)); });

		$inclusive = array_fill_keys($inclusive, true);
		$exclusive = array_fill_keys($exclusive, true);
		$trans_exclusive = array_fill_keys($trans_exclusive, true);

		$inc = (isset($inclusive[strtolower($user['letscode'])])) ? true :false; 

		if (!is_array($a)
			|| !$a['enabled']
			|| ($user['status'] == 1 && !$a['active_no_new_or_leaving'] && !$inc)
			|| ($user['status'] == 2 && !$a['leaving'] && !$inc)
			|| ($user['status'] == 3 && !$a['new'] && !$inc) 
			|| (isset($exclusive[trim(strtolower($user['letscode']))]))
			|| (isset($trans_exclusive[trim(strtolower($from_user['letscode']))]))
			|| ($a['min'] >= $user['minlimit'])
			|| ($a['account_base'] >= $user['saldo']) 
		)
		{
			$this->monolog->debug('autominlimit: no new minlimit for user ' . link_user($user, $sch, false), ['schema' => $schema]);
			return;
		}

		$extract = round(($a['trans_percentage'] / 100) * $amount);

		if (!$extract)
		{
			return;
		}

		$new_minlimit = $user['minlimit'] - $extract;
		$new_minlimit = ($new_minlimit < $a['min']) ? $a['min'] : $new_minlimit;

		$app['eland.xdb']->set('autominlimit', $to_id, ['minlimit' => $new_minlimit], $sch);

		$app['db']->update($sch . '.users', ['minlimit' => $new_minlimit], ['id' => $to_id]);
		readuser($to_id, true, $sch);

//				echo 'new minlimit ' . $new_minlimit . ' for user ' . link_user($user, $sch, false) .  $r;

		$this->monolog->info('(cron) autominlimit: new minlimit : ' . $new_minlimit .
			' for user ' . link_user($user, $sch, false) . ' (id:' . $to_id . ') ', ['schema' => $sch]);
	}

	public function queue(array $data)
	{
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
}
