<?php

/*
                            Table "eland_extra.events"
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

                             Table "eland_extra.aggs"
   Column    |            Type             |              Modifiers               
-------------+-----------------------------+--------------------------------------
 agg_id      | character varying(255)      | not null
 agg_version | integer                     | not null
 data        | jsonb                       | 
 user_id     | integer                     | default 0
 user_schema | character varying(60)       | default ''::character varying
 ts          | timestamp without time zone | default timezone('utc'::text, now())
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

class eland_extra_db
{
	private $ip;

	public function __construct()
	{
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
	 *
	 */

	public function set($agg_type = '', $eland_id = '', $data = [], $sch = false)
	{
		global $schema, $s_schema, $s_id, $db;

		$sch = ($sch) ?: $schema;

		if (!strlen($agg_type))
		{
			return 'No agg type set';
		}

		if (!strlen($eland_id))
		{
			return 'No eland id set';
		}

		if (!isset($sch) || !$sch)
		{
			return 'No schema set';
		}

		$user_id = ctype_digit((string) $s_id) ? $s_id : 0;
		$user_schema = $s_schema;
		$agg_schema = $sch;

		$agg_id = $agg_schema . '_' . $agg_type . '_' . $eland_id;

		$row = $db->fetchAssoc('select data, agg_version
			from eland_extra.aggs
			where agg_id = ?', [$agg_id]);

		if ($row)
		{
			$data = array_diff_assoc($data, json_decode($row['data'], true));
			$agg_version = $row['agg_version'] + 1;
			$event = $agg_type . '_updated';
			$ev = 'updated';
		}
		else
		{
			$agg_version = 1;
			$ev = 'created';
		}

		if (!count($data))
		{
			return;
		}

		$event = $agg_type . '_' . $ev;

		$insert = [
			'user_id'		=> $user_id,
			'user_schema'	=> $user_schema,
			'agg_id'		=> $agg_id,
			'agg_type'		=> $agg_type,
			'agg_schema'	=> $agg_schema,
			'eland_id'		=> $eland_id,
			'agg_version'	=> $agg_version,
			'event'			=> $agg_type . '_' . $ev,
			'data'			=> json_encode($data),
			'ip'			=> $this->ip,
		];

		try
		{
			$db->beginTransaction();

			$db->insert('eland_extra.events', $insert);

			if ($agg_version == 1)
			{
				$db->insert('eland_extra.aggs', $insert);
			}
			else
			{
				unset($insert['data']);
				$update = $insert;
				$update['data'] = array_merge($row['data'], $data);
				$db->update('eland_extra.aggs', $update);
			}

			$db->commit();
		}
		catch(Exception $e)
		{
			$db->rollback();
			error_log('error transaction eland extra db: ' . $e->getMessage());
			echo 'Database transactie niet gelukt.';
			log_event('debug', 'Database transactie niet gelukt. ' . $e->getMessage());
			throw $e;
			exit;
		}
	}

	/*
	 *
	 */

	public function del($agg_type = '', $eland_id = '', $sch = false)
	{
		global $schema, $s_schema, $s_id, $db;

		$sch = ($sch) ?: $schema;

		if (!strlen($agg_type))
		{
			return 'No agg type set';
		}

		if (!strlen($eland_id))
		{
			return 'No eland id set';
		}

		if (!isset($sch) || !$sch)
		{
			return 'No schema set';
		}

		$user_id = ctype_digit((string) $s_id) ? $s_id : 0;
		$user_schema = $s_schema;
		$agg_schema = $sch;

		$agg_id = $agg_schema . '_' . $agg_type . '_' . $eland_id;

		$agg_version = $db->fetchColumn('select agg_version
			from eland_extra.aggs
			where agg_id = ?', [$agg_id]);

		if (!$agg_version)
		{
			return 'Not found: ' . $agg_id . ', could not delete';
		}

		$insert = [
			'user_id'		=> $user_id,
			'user_schema'	=> $user_schema,
			'agg_id'		=> $agg_id,
			'agg_type'		=> $agg_type,
			'agg_schema'	=> $agg_schema,
			'eland_id'		=> $eland_id,
			'agg_version'	=> $agg_version + 1,
			'event'			=> $agg_type . '_deleted',
			'data'			=> json_encode([]),
			'ip'			=> $this->ip,
		];

		try
		{
			$db->beginTransaction();

			$db->insert('eland_extra.events', $insert);

			$db->delete('eland_extra.aggs', ['agg_id' => $agg_id]);

			$db->commit();
		}
		catch(Exception $e)
		{
			$db->rollback();
			$alert->error('Database transactie niet gelukt.');
			echo 'Database transactie niet gelukt.';
			event_log('debug', 'Database transactie niet gelukt. ' . $e->getMessage());
			throw $e;
			exit;
		}
	}

	/*
	 *
	 */

	public function get($agg_type = '', $eland_id = '', $sch = false)
	{
		global $schema, $s_schema, $s_id, $db;

		$sch = ($sch) ?: $schema;

		if (!strlen($agg_type))
		{
			return [];
		}

		if (!strlen($eland_id))
		{
			return [];
		}

		if (!isset($sch) || !$sch)
		{
			return [];
		}

		$agg_id = $sch . '_' . $agg_type . '_' . $eland_id;

		$row = $db->fetchAssoc('select * from eland_extra.aggs where agg_id = ?', [$agg_id]);

		if (!$row)
		{
			return false;
		}

		$row['data'] = json_decode($row['data'], true);

		return $row;
	}

	/**
	 *
	 */

	public function get_many($filters = [])
	{
		global $db;

		$sql_where = [];
		$sql_params = [];
		$sql_types = [];

		if (isset($filters['agg_type']))
		{
			$sql_where[] = 'agg_type = ?';
			$sql_params[] = $filters['agg_type'];
			$sql_types[] = \PDO::PARAM_STR;
		}

		if (isset($filters['agg_id_ary']))
		{
			$sql_where[] = 'agg_id in (?)';
			$sql_params[] = $filters['agg_id_ary'];
			$sql_types[] = \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
		}

		if (isset($filers['schema']))
		{
			$sql_where[] = 'agg_schema = ?';
			$sql_params[] = $filers['schema'];
			$sql_types[] = \PDO::PARAM_STR;
		}

		$query = 'select * from eland_extra.aggs';

		if (count($sql_where))
		{
			$query .= ' where ' . implode(' and ', $sql_where);
		}

		$rows = $db->executeQuery($query, $sql_params, $sql_types);

		$ary = [];

		foreach ($rows as $row)
		{
			$row['data'] = json_decode($row['data'], true);

			$ary[$row['agg_id']] = $row;
		}

		return $ary;
	}
}

