<?php

namespace eland\task;

use eland\base_task;
use Predis\Client as Redis;
use eland\cache;
use Doctrine\DBAL\Connection as db;
use Monolog\Logger;
use eland\s3;
use eland\groups;

class cleanup_image_files extends base_task
{
	protected $days = 365;
	protected $cache;
	protected $db;
	protected $monolog;
	protected $s3;
	protected $groups;

	public function __construct(cache $cache, db $db, Logger $monolog, s3 $s3, groups $groups)
	{
		$this->cache = $cache;
		$this->db = $db;
		$this->monolog = $monolog;
		$this->s3 = $s3;
		$this->groups = $groups;
	}

/*
	public function run()
	{
		// $schema is not used, files of all schemas are scanned

		$marker = $this->redis->get('cleanup_image_files_marker');

		$marker = ($marker) ? $marker : '0';

		$time_treshold = time() - (84600 * $this->days);

		$objects = $this->s3->img_list($marker);

		$reset = true;

		foreach ($objects as $k => $object)
		{
			if ($k > 4)
			{
				$reset = false;
				break;
			}

			$delete = $del_str = false;

			$object_time = strtotime($object['LastModified']);

			$old = ($object_time < $time_treshold) ? true : false;

			$str_log = $k . ' ' .  $object['Key'] . ' ' . $object['LastModified'] . ' ';

			$str_log .= ($old) ? 'OLD' : 'NEW';

			error_log($str_log);

			if (!$old)
			{
				continue;
			}

			list($sch, $type, $id, $hash) = explode('_', $object['Key']);

			if (ctype_digit((string) $sch))
			{
				error_log('-> elas import image. DELETE');
				$delete = true;
			} 


			if (!$delete && $this->groups->get_host($sch))
			{
				error_log('-> unknown schema');
				continue;
			}

			// dokku 7.2 bug: unset config var still exists

			// begin

			if ($this->schema_manager->tablesExist([$sch . '.users', $sch . '.msgpictures']) !== true)
			{
				error_log('-> table not present for schema ' . $sch);
				continue;
			}

			// end

			if (!$delete && !in_array($type, ['u', 'm']))
			{
				error_log('-> unknown type');
				continue;
			}

			if (!$delete && $type == 'u' && ctype_digit((string) $id))
			{
				$user = $this->db->fetchAssoc('select id, "PictureFile" from ' . $sch . '.users where id = ?', [$id]);

				if (!$user)
				{
					$del_str = '->User does not exist.';
					$delete = true;
				}
				else if ($user['PictureFile'] != $object['Key'])
				{
					$del_str = '->does not match db key ' . $user['PictureFile'];
					$delete = true;
				}
			}

			if (!$delete && $type == 'm' && ctype_digit((string) $id))
			{
				$msgpict = $this->db->fetchAssoc('select * from ' . $sch . '.msgpictures
					where msgid = ?
						and "PictureFile" = ?', [$id, $object['Key']]);

				if (!$msgpict)
				{
					$del_str = '->is not present in db.';
					$delete = true;
				}
			}

			if (!$delete)
			{
				continue;
			}

			$this->s3->img_del($object['Key']);

			if ($del_str)
			{
				$this->monolog->info('(cron) image file ' . $object['Key'] . ' deleted ' . $del_str, ['schema' => $sch]);
			}
		}

		$this->redis->set('cleanup_image_files_marker', $reset ? '0' : $object['Key']);
	}
*/

	public function run()
	{
		// $schema is not used, files of all schemas are scanned

		$cached = $this->cache->get('cleanup_image_files_marker');

		$marker = $cached['marker'] ?? '0';

		$time_treshold = time() - (84600 * $this->days);

		$object = $this->s3->find_img($marker);

		if (!$object)
		{
			$this->cache->set('cleanup_image_files_marker', ['marker' => '0']);
			error_log('-- no image file found --- reset marker --');
			return;
		}

		$this->cache->set('cleanup_image_files_marker', ['marker' => $object['Key']]);

		$object_time = strtotime($object['LastModified']);

		$old = ($object_time < $time_treshold) ? true : false;

		$str_log = $object['Key'] . ' ' . $object['LastModified'] . ' ';
		$str_log .= ($old) ? 'OLD' : 'NEW';

		error_log($str_log);

		if (!$old)
		{
			return;
		}

		list($sch, $type, $id, $hash) = explode('_', $object['Key']);

		if (!$this->groups->get_host($sch))
		{
			error_log('-> unknown schema. (no delete)');
			return;
		}

		if (!$this->table_exists('users', $sch))
		{
			error_log('-> table not present for schema ' . $sch . '.users (no delete)');
			return;
		}

		if (!$this->table_exists('msgpictures', $sch))
		{
			error_log('-> table not present for schema ' . $sch . '.msgpictures (no delete)');
			return;
		}

		if (!in_array($type, ['u', 'm']))
		{
			error_log('-> unknown type u, m (no delete)');
			return;
		}

		if ($type == 'u' && ctype_digit((string) $id))
		{
			$user = $this->db->fetchAssoc('select id, "PictureFile" from ' . $sch . '.users where id = ?', [$id]);

			if (!$user)
			{
				$del_str = '->User does not exist.';
			}
			else if ($user['PictureFile'] != $object['Key'])
			{
				$del_str = '->does not match db key ' . $user['PictureFile'];
			}
			else
			{
				error_log(' user image is still present in db ');
				return;
			}
		}
		else if ($type == 'm' && ctype_digit((string) $id))
		{
			$msgpict = $this->db->fetchAssoc('select * from ' . $sch . '.msgpictures
				where msgid = ?
					and "PictureFile" = ?', [$id, $object['Key']]);

			if (!$msgpict)
			{
				$del_str = '->is not present in db.';
			}
			else
			{
				error_log(' msg image is still present in db ');
				return;
			}
		}

		error_log(' -- delete img --');
		$this->s3->img_del($object['Key']);

		if ($del_str)
		{
			$this->monolog->info('(cron) image file ' . $object['Key'] . ' deleted ' . $del_str, ['schema' => $sch]);
		}
	}

	private function table_exists(string $table, string $schema)
	{
		return $this->db->fetchColumn('
			select 1 
			from   pg_catalog.pg_class c
			join   pg_catalog.pg_namespace n ON n.oid = c.relnamespace
			where  n.nspname = \'' . $schema . '\'
			and    c.relname = \'' . $table . '\'
			and    c.relkind = \'r\'') ? true : false;
	}

	public function get_interval()
	{
		return 100;
	}
}
