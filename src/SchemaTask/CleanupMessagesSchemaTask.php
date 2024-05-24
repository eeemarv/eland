<?php declare(strict_types=1);

namespace App\SchemaTask;

use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use App\Service\ConfigService;
use Doctrine\DBAL\ArrayParameterType;

class CleanupMessagesSchemaTask implements SchemaTaskInterface
{
	public function __construct(
		protected Db $db,
		protected LoggerInterface $logger,
		protected ConfigService $config_service
	)
	{
	}

	public static function get_default_index_name():string
	{
		return 'cleanup_messages';
	}

	public function run(string $schema, bool $update):void
	{
		$after_days = $this->config_service->get_int('messages.cleanup.after_days', $schema);
		$msgs = '';
		$testdate = gmdate('Y-m-d H:i:s', time() - ($after_days * 86400));

		$stmt = $this->db->prepare('select id, subject
			from ' . $schema . '.messages
			where expires_at < ?');

		$stmt->bindValue(1, $testdate);
		$res = $stmt->executeQuery();

		while ($row = $res->fetchAssociative())
		{
			$msgs .= $row['id'] . ': ' . $row['subject'] . ', ';
		}

		$msgs = trim($msgs, '\n\r\t ,;:');

		if ($msgs)
		{
			$this->logger->info('Expired and deleted Messages ' . $msgs,
				['schema' => $schema]);

			$this->db->executeStatement('delete from ' . $schema . '.messages
				where expires_at < ?', [$testdate]);
		}

		$users = '';
		$user_ids = [];

		$stmt = $this->db->prepare('select u.id, u.code, u.name
			from ' . $schema . '.users u,
				' . $schema . '.messages m
			where u.status = 0
				and m.user_id = u.id');

		$res = $stmt->executeQuery();

		while ($row = $res->fetchAssociative())
		{
			$user_ids[] = $row['id'];
			$users .= '(id: ' . $row['id'] . ') ' . $row['code'] . ' ' . $row['name'] . ', ';
		}

		$users = trim($users, '\n\r\t ,;:');

		if (count($user_ids))
		{
			$this->logger->info('Cleanup messages from users: ' . $users,
				['schema' => $schema]);

			echo 'Cleanup messages from users: ' . $users;

			$this->db->executeStatement('delete
				from ' . $schema . '.messages
				where user_id in (?)',
				[$user_ids],
				[ArrayParameterType::INTEGER]);
		}
	}

	public function is_enabled(string $schema):bool
	{
		return $this->config_service->get_bool('messages.cleanup.enabled', $schema);
	}

	public function get_interval(string $schema):int
	{
		return 86400;
	}
}
