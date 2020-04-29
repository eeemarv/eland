<?php declare(strict_types=1);

namespace App\SchemaTask;

use Doctrine\DBAL\Connection as Db;
use App\Queue\MailQueue;
use App\Service\ConfigService;
use App\Service\UserCacheService;
use App\Service\MailAddrUserService;

class UserExpMsgsSchemaTask implements SchemaTaskInterface
{
	protected Db $db;
	protected MailQueue $mail_queue;
	protected ConfigService $config_service;
	protected UserCacheService $user_cache_service;
	protected MailAddrUserService $mail_addr_user_service;

	public function __construct(
		Db $db,
		MailQueue $mail_queue,
		ConfigService $config_service,
		UserCacheService $user_cache_service,
		MailAddrUserService $mail_addr_user_service
	)
	{
		$this->db = $db;
		$this->mail_queue = $mail_queue;
		$this->config_service = $config_service;
		$this->user_cache_service = $user_cache_service;
		$this->mail_addr_user_service = $mail_addr_user_service;
	}

	public static function get_default_index_name():string
	{
		return 'user_exp_msgs';
	}

	public function run(string $schema, bool $update):void
	{
		$now = gmdate('Y-m-d H:i:s');

		$warn_messages  = $this->db->fetchAll('select m.*
			from ' . $schema . '.messages m, ' .
				$schema . '.users u
			where m.exp_user_warn = \'f\'
				and u.id = m.user_id
				and u.status in (1, 2)
				and m.expires_at < ?', [$now]);

		foreach ($warn_messages as $message)
		{
			$user = $this->user_cache_service->get($message['user_id'], $schema);

			if (!($user['status'] == 1 || $user['status'] == 2))
			{
				continue;
			}

			$message['offer_want'] = $message['is_offer'] ? 'offer' : 'want';

			$vars = [
				'message' 		=> $message,
				'user_id'		=> $user['id'],
			];

			$mail_template = 'message_extend/';
			$mail_template .= $message['is_offer'] ? 'offer' : 'want';

			$this->mail_queue->queue([
				'to' 				=> $this->mail_addr_user_service->get_active((int) $message['user_id'], $schema),
				'schema' 			=> $schema,
				'template' 			=> $mail_template,
				'vars' 				=> $vars
			], random_int(0, 5000));
		}

		if ($update)
		{
			$this->db->executeUpdate('update ' . $schema . '.messages
				set exp_user_warn = \'t\'
				where expires_at < ?', [$now]);
		}
	}

	public function is_enabled(string $schema):bool
	{
		return $this->config_service->get('msgexpwarnenabled', $schema) ? true : false;
	}

	public function get_interval(string $schema):int
	{
		return 86400;
	}
}
