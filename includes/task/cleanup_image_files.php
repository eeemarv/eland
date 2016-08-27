<?php

namespace eland\task;

use Predis\Client as Redis;
use Doctrine\DBAL\Connection as db;
use Monolog\Logger;
use eland\s3;
use eland\groups;

class cleanup_image_files
{
	protected $days = 365;
	protected $redis;
	protected $db;
	protected $monolog;
	protected $s3;
	protected $groups;

	public function __construct(Redis $redis, db $db, Logger $monolog, s3 $s3, groups $groups)
	{
		$this->redis = $redis;
		$this->db = $db;
		$this->monolog = $monolog;
		$this->s3 = $s3;
		$this->groups = $groups;
	}

	function run()
	{
		if ($this->redis->get('cron_cleanup_image_files'))
		{
			return;
		}

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
		$this->redis->set('cron_cleanup_image_files', '1');
		$this->redis->expire('cron_cleanup_image_files', 900);
	}
}
