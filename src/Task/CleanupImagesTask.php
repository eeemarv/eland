<?php declare(strict_types=1);

namespace App\Task;

use App\Service\CacheService;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use App\Service\S3Service;
use App\Service\SystemsService;

class CleanupImagesTask
{
	const DAYS = 365;

	public function __construct(
		protected CacheService $cache_service,
		protected Db $db,
		protected LoggerInterface $logger,
		protected S3Service $s3_service,
		protected SystemsService $systems_service
	)
	{
	}

	public function process():void
	{
		// files of all schemas are scanned

		$cached = $this->cache_service->get('cleanup_image_files_marker');

		$marker = $cached['marker'] ?? '0';

		$time_treshold = time() - (84600 * self::DAYS);

		$object = $this->s3_service->find_next($marker);

		if (!$object)
		{
			$this->cache_service->set('cleanup_image_files_marker', ['marker' => '0']);
			error_log('-- no image file found --- reset marker --');
			return;
		}

		$this->cache_service->set('cleanup_image_files_marker',
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

		if (!$this->systems_service->get_system($sch))
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

		if (!$this->table_exists('messages', $sch))
		{
			error_log('-> table not present for schema ' .
				$sch . '.messages (no delete)');
			return;
		}

		if ($type == 'u' && ctype_digit((string) $id))
		{
			$user = $this->db->fetchAssociative('select id, image_file
				from ' . $sch . '.users
				where id = ?', [$id], [\PDO::PARAM_INT]);

			if (!$user)
			{
				$del_str = '->User does not exist.';
			}
			else if ($user['image_file'] !== $object['Key'])
			{
				$del_str = '->does not match db key ' . $user['image_file'];
			}
			else
			{
				error_log(' user image is still present in db ');
				return;
			}
		}
		else if ($type === 'm' && ctype_digit((string) $id))
		{
			$image_files = $this->db->fetchOne('select image_files
				from ' . $sch . '.messages
				where id = ?', [$id], [\PDO::PARAM_INT]);

			$image_files = $image_files ?? '[]';
			$image_files = $image_files === false ? '[]' : $image_files;

			$image_file_ary = json_decode($image_files, true);
			$key = array_search($object['Key'], $image_file_ary);

			if ($key === false)
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
		$this->s3_service->del($object['Key']);

		if ($del_str)
		{
			$this->logger->info('cleanup_images: ' . $object['Key'] .
				' deleted ' . $del_str, ['schema' => $sch]);
		}
	}

	protected function table_exists(string $table, string $schema):bool
	{
		return $this->db->fetchOne('select 1
			from pg_catalog.pg_class c
				join pg_catalog.pg_namespace n on n.oid = c.relnamespace
			where n.nspname = ?
				and c.relname = ?
				and c.relkind = \'r\'',
				[$schema, $table],
				[\PDO::PARAM_STR, \PDO::PARAM_STR]) ? true : false;
	}
}
