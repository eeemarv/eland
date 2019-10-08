<?php declare(strict_types=1);

namespace App\Task;

use service\cache;
use Doctrine\DBAL\Connection as db;
use Psr\Log\LoggerInterface;
use service\s3;
use service\systems;

class cleanup_images
{
	const DAYS = 365;

	protected $cache;
	protected $db;
	protected $logger;
	protected $s3;
	protected $systems;

	public function __construct(
		cache $cache,
		db $db,
		LoggerInterface $logger,
		s3 $s3,
		systems $systems
	)
	{
		$this->cache = $cache;
		$this->db = $db;
		$this->logger = $logger;
		$this->s3 = $s3;
		$this->systems = $systems;
	}

	public function process():void
	{
		// $schema is not used, files of all schemas are scanned

		$cached = $this->cache->get('cleanup_image_files_marker');

		$marker = $cached['marker'] ?? '0';

		$time_treshold = time() - (84600 * self::DAYS);

		$object = $this->s3->find_next($marker);

		if (!$object)
		{
			$this->cache->set('cleanup_image_files_marker', ['marker' => '0']);
			error_log('-- no image file found --- reset marker --');
			return;
		}

		$this->cache->set('cleanup_image_files_marker',
			['marker' => $object['Key']]);

		$object_time = strtotime($object['LastModified'] . ' UTC');

		$old = $object_time < $time_treshold;

		$str_log = $object['Key'] . ' ' . $object['LastModified'] . ' ';
		$str_log .= $old ? 'OLD' : 'NEW (keep)';

		error_log($str_log);

		if (!$old)
		{
			return;
		}

		[$sch, $type, $id] = explode('_', $object['Key']);

		if (!in_array($type, ['u', 'm']))
		{
			error_log('type not in (u, m) (no delete) ' . $object['Key']);
			return;
		}

		if (!$this->systems->get_system($sch))
		{
			error_log('-> unknown schema. ' . $sch . ' (no delete)');
			return;
		}

		if (!$this->table_exists('users', $sch))
		{
			error_log('-> table not present for schema ' .
				$sch . '.users (no delete)');
			return;
		}

		if (!$this->table_exists('msgpictures', $sch))
		{
			error_log('-> table not present for schema ' .
				$sch . '.msgpictures (no delete)');
			return;
		}

		if ($type == 'u' && ctype_digit((string) $id))
		{
			$user = $this->db->fetchAssoc('select id, "PictureFile"
				from ' . $sch . '.users
				where id = ?', [$id]);

			if (!$user)
			{
				$del_str = '->User does not exist.';
			}
			else if ($user['PictureFile'] !== $object['Key'])
			{
				$del_str = '->does not match db key ' . $user['PictureFile'];
			}
			else
			{
				error_log(' user image is still present in db ');
				return;
			}
		}
		else if ($type === 'm' && ctype_digit((string) $id))
		{
			$msgpict = $this->db->fetchAssoc('select *
				from ' . $sch . '.msgpictures
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
		$this->s3->del($object['Key']);

		if ($del_str)
		{
			$this->logger->info('cleanup_images: ' . $object['Key'] .
				' deleted ' . $del_str, ['schema' => $sch]);
		}
	}

	protected function table_exists(string $table, string $schema):bool
	{
		return $this->db->fetchColumn('select 1
			from pg_catalog.pg_class c
				join pg_catalog.pg_namespace n on n.oid = c.relnamespace
			where n.nspname = \'' . $schema . '\'
				and c.relname = \'' . $table . '\'
				and c.relkind = \'r\'') ? true : false;
	}
}
