<?php declare(strict_types=1);

namespace App\SchemaTask;

use Doctrine\DBAL\Connection as Db;
use App\Queue\MailQueue;
use App\Service\ConfigService;
use App\Service\UserCacheService;
use App\Service\MailAddrUserService;
use Doctrine\DBAL\Types\Types;

class UserExpMsgsSchemaTask implements SchemaTaskInterface
{
	public function __construct(
		protected Db $db,
		protected MailQueue $mail_queue,
		protected ConfigService $config_service,
		protected UserCacheService $user_cache_service,
		protected MailAddrUserService $mail_addr_user_service
	)
	{
	}

	public static function get_default_index_name():string
	{
		return 'user_exp_msgs';
	}

	public function run(string $schema, bool $update):void
	{
		$now = \DateTimeImmutable::createFromFormat('U', (string) time());

		$warn_messages  = $this->db->fetchAllAssociative('select m.*
			from ' . $schema . '.messages m, ' .
				$schema . '.users u
			where m.exp_user_warn = \'f\'
				and u.id = m.user_id
				and u.status in (1, 2)
				and m.expires_at < ?',
				[$now], [Types::DATETIME_IMMUTABLE]);

		foreach ($warn_messages as $message)
		{
			$user = $this->user_cache_service->get($message['user_id'], $schema);

			if (!($user['status'] === 1 || $user['status'] === 2))
			{
				continue;
			}

			if (!($user['role'] === 'admin' || $user['role'] === 'user'))
			{
				continue;
			}

			$vars = [
				'message' 		=> $message,
				'user_id'		=> $user['id'],
			];

			$mail_template = 'message_extend/' . $message['offer_want'];

			$this->mail_queue->queue([
				'to' 				=> $this->mail_addr_user_service->get_active((int) $message['user_id'], $schema),
				'schema' 			=> $schema,
				'template' 			=> $mail_template,
				'vars' 				=> $vars
			], random_int(0, 5000));
		}

		if ($update)
		{
			$this->db->executeStatement('update ' . $schema . '.messages
				set exp_user_warn = \'t\'
				where expires_at < ?',
				[$now], [Types::DATETIME_IMMUTABLE]);
		}
	}

	public function is_enabled(string $schema):bool
	{
		return $this->config_service->get_bool('messages.fields.expires_at.enabled', $schema)
			&& $this->config_service->get_bool('messages.expire.notify', $schema);
	}

	public function get_interval(string $schema):int
	{
		return 86400;
	}
}
