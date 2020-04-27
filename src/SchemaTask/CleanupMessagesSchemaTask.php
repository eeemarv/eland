<?php declare(strict_types=1);

namespace App\SchemaTask;

use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use App\Service\ConfigService;

class CleanupMessagesSchemaTask implements SchemaTaskInterface
{
	protected Db $db;
	protected LoggerInterface $logger;
	protected ConfigService $config_service;

	public function __construct(
		Db $db,
		LoggerInterface $logger,
		ConfigService $config_service
	)
	{
		$this->db = $db;
		$this->logger = $logger;
		$this->config_service = $config_service;
	}

	public static function get_default_index_name():string
	{
		return 'cleanup_messages';
	}

	public function run(string $schema, bool $update):void
	{
		$msgs = '';
		$testdate = gmdate('Y-m-d H:i:s', time() - $this->config_service->get('msgexpcleanupdays', $schema) * 86400);

		$st = $this->db->prepare('select id, subject, id_category, msg_type
			from ' . $schema . '.messages
			where validity < ?');

		$st->bindValue(1, $testdate);
		$st->execute();

		while ($row = $st->fetch())
		{
			$msgs .= $row['id'] . ': ' . $row['subject'] . ', ';
		}

		$msgs = trim($msgs, '\n\r\t ,;:');

		if ($msgs)
		{
			$this->logger->info('Expired and deleted Messages ' . $msgs,
				['schema' => $schema]);

			$this->db->executeQuery('delete from ' . $schema . '.messages
				where validity < ?', [$testdate]);
		}

		$users = '';
		$ids = [];

		$st = $this->db->prepare('select u.id, u.code, u.name
			from ' . $schema . '.users u, ' . $schema . '.messages m
			where u.status = 0
				and m.id_user = u.id');

		$st->execute();

		while ($row = $st->fetch())
		{
			$ids[] = $row['id'];
			$users .= '(id: ' . $row['id'] . ') ' . $row['code'] . ' ' . $row['name'] . ', ';
		}

		$users = trim($users, '\n\r\t ,;:');

		if (count($ids))
		{
			$this->logger->info('Cleanup messages from users: ' . $users,
				['schema' => $schema]);

			echo 'Cleanup messages from users: ' . $users;

			if (count($ids) == 1)
			{
				$this->db->delete($schema . '.messages',
					['id_user' => $ids[0]]);
			}
			else if (count($ids) > 1)
			{
				$this->db->executeQuery('delete
					from ' . $schema . '.messages
					where id_user in (?)',
					[$ids],
					[Db::PARAM_INT_ARRAY]);
			}
		}
	}

	public function is_enabled(string $schema):bool
	{
		return true;
	}

	public function get_interval(string $schema):int
	{
		return 86400;
	}
}
