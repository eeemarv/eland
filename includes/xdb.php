<?php

namespace eland;

use Doctrine\DBAL\Connection as db;
use Predis\Client as Redis;
use Monolog\Logger;
use eland\this_group;

/*
                            Table "xdb.events"
   Column    |            Type             |              Modifiers               
-------------+-----------------------------+--------------------------------------
 ts          | timestamp without time zone | default timezone('utc'::text, now())
 user_id     | integer                     | default 0
 user_schema | character varying(60)       | 
 agg_id      | character varying(255)      | not null
 agg_type    | character varying(60)       | 
 agg_version | integer                     | not null
 data        | jsonb                       | 
 event_time  | timestamp without time zone | default timezone('utc'::text, now())
 ip          | character varying(60)       | 
 event       | character varying(128)      | 
 agg_schema  | character varying(60)       | 
 eland_id    | character varying(40)       | 
Indexes:
    "events_pkey" PRIMARY KEY, btree (agg_id, agg_version)

                             Table "xdb.aggs"
   Column    |            Type             |              Modifiers               
-------------+-----------------------------+--------------------------------------
 agg_id      | character varying(255)      | not null
 agg_version | integer                     | not null
 data        | jsonb                       | 
 user_id     | integer                     | default 0
 user_schema | character varying(60)       | default ''::character varying
 ts          | timestamp without time zone | default timezone('utc'::text, now())
 event_time  | timestamp without time zone | default timezone('utc'::text, now())
 agg_type    | character varying(60)       | not null
 agg_schema  | character varying(60)       | not null
 ip          | character varying(60)       | 
 event       | character varying(128)      | 
 eland_id    | character varying(40)       | 
Indexes:
    "aggs_pkey" PRIMARY KEY, btree (agg_id)
    "aggs_agg_schema_idx" btree (agg_schema)
    "aggs_agg_type_agg_schema_idx" btree (agg_type, agg_schema)
    "aggs_agg_type_idx" btree (agg_type)
*/

class xdb
{
	private $ip;
	private $user_schema = '';
	private $user_id = '';
	private $db;
	private $redis;
	private $monolog;
	private $this_group;

	public function __construct(db $db, Redis $redis, Logger $monolog, this_group $this_group)
	{
		$this->db = $db;
		$this->redis = $redis;
		$this->monolog = $monolog;
		$this->this_group = $this_group;

		if (isset($_SERVER['HTTP_CLIENT_IP']))
		{
			$this->ip = $_SERVER['HTTP_CLIENT_IP'];
		}
		else if (isset($_SERVER['HTTP_X_FORWARDE‌​D_FOR']))
		{
			$this->ip = $_SERVER['HTTP_X_FORWARDE‌​D_FOR'];
		}
		else
		{
			$this->ip = $_SERVER['REMOTE_ADDR'];
		}
	}

	/*
	 */

	public function init(string $user_schema = '', $user_id = 0)
	{
		$this->user_schema = ($user_schema) ? $user_schema : $this->this_group->get_schema();
		$this->user_id = ctype_digit((string) $user_id) ? $user_id : 0;
	}

	/*
	 *
	 */

	public function set(string $agg_type = '', string $eland_id = '', array $data = [], string $agg_schema = '', string $event_time = '')
	{
		$agg_schema = ($agg_schema) ?: $this->this_group->get_schema();

		if (!strlen($agg_type))
		{
			return 'xdb: No agg type set';
		}

		if (!strlen($eland_id))
		{
			return 'xdb: No eland id set';
		}

		if (!isset($agg_schema) || !$agg_schema)
		{
			return 'xdb: No schema set';
		}

		$agg_id = $agg_schema . '_' . $agg_type . '_' . $eland_id;

		$row = $this->db->fetchAssoc('select data, agg_version
			from xdb.aggs
			where agg_id = ?', [$agg_id]);

		if ($row)
		{
			$prev_data = json_decode($row['data'], true);

			$data = array_diff_assoc($data, $prev_data);
			$agg_version = $row['agg_version'] + 1;
			$ev = 'updated';
		}
		else
		{
			$agg_version = 1;
			$ev = 'created';
		}

		if (!count($data))
		{
			return 'xdb: no (new) data';
		}

		$event = $agg_type . '_' . $ev;

		$insert = [
			'user_id'		=> $this->user_id,
			'user_schema'	=> $this->user_schema,
			'agg_id'		=> $agg_id,
			'agg_type'		=> $agg_type,
			'agg_schema'	=> $agg_schema,
			'eland_id'		=> $eland_id,
			'agg_version'	=> $agg_version,
			'event'			=> $agg_type . '_' . $ev,
			'data'			=> json_encode($data),
			'ip'			=> $this->ip,
		];

		if ($event_time)
		{
			$insert['event_time'] = $event_time;
		}

		try
		{
			$this->db->beginTransaction();

			$this->db->insert('xdb.events', $insert);

			if ($agg_version == 1)
			{
				$this->db->insert('xdb.aggs', $insert);
			}
			else
			{
				unset($insert['data']);
				$update = $insert;
				$update['data'] = json_encode(array_merge($prev_data, $data));

				$this->db->update('xdb.aggs', $update, ['agg_id' => $agg_id]);
			}

			$this->db->commit();
		}
		catch(Exception $e)
		{
			$this->db->rollback();
			echo 'Database transactie niet gelukt.';
			$this->monolog->debug('Database transactie niet gelukt. ' . $e->getMessage());
			throw $e;
			exit;
		}
	}

	/*
	 *
	 */

	public function del(string $agg_type = '', string $eland_id = '', string $agg_schema = '')
	{
		$agg_schema = ($agg_schema) ?: $this->this_group->get_schema();

		if (!strlen($agg_type))
		{
			return 'No agg type set';
		}

		if (!strlen($eland_id))
		{
			return 'No eland id set';
		}

		if (!isset($agg_schema) || !$agg_schema)
		{
			return 'No schema set';
		}

		$agg_id = $agg_schema . '_' . $agg_type . '_' . $eland_id;

		$agg_version = $this->db->fetchColumn('select agg_version
			from xdb.aggs
			where agg_id = ?', [$agg_id]);

		if (!$agg_version)
		{
			return 'Not found: ' . $agg_id . ', could not delete';
		}

		$insert = [
			'user_id'		=> $this->user_id,
			'user_schema'	=> $this->user_schema,
			'agg_id'		=> $agg_id,
			'agg_type'		=> $agg_type,
			'agg_schema'	=> $agg_schema,
			'eland_id'		=> $eland_id,
			'agg_version'	=> $agg_version + 1,
			'event'			=> $agg_type . '_deleted',
			'data'			=> '{}',
			'ip'			=> $this->ip,
		];

		try
		{
			$this->db->beginTransaction();

			$this->db->insert('xdb.events', $insert);

			$this->db->delete('xdb.aggs', ['agg_id' => $agg_id]);

			$this->db->commit();
		}
		catch(Exception $e)
		{
			$this->db->rollback();
			echo 'Database transactie niet gelukt.';
			$this->monolog->debug('Database transactie niet gelukt. ' . $e->getMessage());
			throw $e;
			exit;
		}
	}

	/*
	 *
	 */

	public function get(string $agg_type = '', string $eland_id = '', string $agg_schema = '')
	{
		$agg_schema = ($agg_schema) ?: $this->this_group->get_schema();

		if (!strlen($agg_type))
		{
			return [];
		}

		if (!strlen($eland_id))
		{
			return [];
		}

		if (!isset($agg_schema) || !$agg_schema)
		{
			return [];
		}

		$agg_id = $agg_schema . '_' . $agg_type . '_' . $eland_id;

		$row = $this->db->fetchAssoc('select * from xdb.aggs where agg_id = ?', [$agg_id]);

		if (!$row)
		{
			return false;
		}

		$row['data'] = json_decode($row['data'], true);

		error_log(' - xdb get ' . $agg_id . ' - ');

		return $row;
	}

	/**
	 *
	 */

	public function get_many(array $filters = [], string $query_extra = '')
	{
		$sql_where = [];
		$sql_params = [];
		$sql_types = [];

		if (isset($filters['agg_id_ary']))
		{
			$sql_where[] = 'agg_id in (?)';
			$sql_params[] = $filters['agg_id_ary'];
			$sql_types[] = \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
		}

		unset($filters['agg_id_ary']);

		if (isset($filters['access']))
		{
			$sql_where[] = 'data->>\'access\' in (?)';
			$sql_params[] = $filters['access'];
			$sql_types[] = \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
		}

		unset($filters['access']);

		foreach ($filters as $key => $value)
		{
			if (is_array($value))
			{
				$v = reset($value);
				$k = key($value);

				if ($k === 0)
				{
					$sql_where[] = $key . ' ' . $v;
				}
				else
				{
					$sql_where[] = $key . ' ' . $k . ' ?';
					$sql_params[] = $v;
					$sql_types[] = \PDO::PARAM_STR;
				}
			}
			else
			{
				$sql_where[] = $key . ' = ?';
				$sql_params[] = $value;
				$sql_types[] = \PDO::PARAM_STR;
			}
		}

		$query = 'select * from xdb.aggs';

		if (count($sql_where))
		{
			$query .= ' where ' . implode(' and ', $sql_where);
		}

		$query .= ($query_extra) ? ' ' . $query_extra : '';

		$rows = $this->db->executeQuery($query, $sql_params, $sql_types);

		$ary = [];

		foreach ($rows as $row)
		{
			$row['data'] = json_decode($row['data'], true);

			$ary[$row['agg_id']] = $row;
		}

		error_log(' - xdb get_many - ');

		return $ary;
	}

	/**
	 *
	 */

	public function count(string $agg_type = '', string $eland_id = '', string $agg_schema = '')
	{
		$sql_where = $sql_params = [];

		if ($agg_type)
		{
			$sql_where[] = 'agg_type = ?';
			$sql_params[] = $agg_type;
		}

		if ($eland_id)
		{
			$sql_where[] = 'eland_id = ?';
			$sql_params[] = $eland_id;
		}

		if ($agg_schema)
		{
			$sql_where[] = 'agg_schema = ?';
			$sql_params[] = $agg_schema;
		}

		$where = count($sql_where) ? ' where ' . implode(' and ', $sql_where) : '';

		return $this->db->fetchColumn('select count(*) from xdb.aggs' . $where, $sq_params);
	}
}

