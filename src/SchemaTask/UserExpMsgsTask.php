<?php declare(strict_types=1);

namespace App\SchemaTask;

use App\Model\SchemaTask;
use Doctrine\DBAL\Connection as Db;
use App\Queue\MailQueue;

use App\Service\Schedule;
use App\Service\SystemsService;
use App\Service\ConfigService;
use App\Service\UserCacheService;
use App\Service\MailAddrUserService;

class UserExpMsgsTask extends SchemaTask
{
	protected $db;
	protected $mail_queue;
	protected $config_service;
	protected $user_cache_service;
	protected $mail_addr_user_service;

	public function __construct(
		Db $db,
		MailQueue $mail_queue,
		Schedule $schedule,
		SystemsService $systems_service,
		ConfigService $config_service,
		UserCacheService $user_cache_service,
		MailAddrUserService $mail_addr_user_service
	)
	{
		parent::__construct($schedule, $systems_service);
		$this->db = $db;
		$this->mail_queue = $mail_queue;
		$this->config_service = $config_service;
		$this->user_cache_service = $user_cache_service;
		$this->mail_addr_user_service = $mail_addr_user_service;
	}

	function process(bool $update = true):void
	{
		$now = gmdate('Y-m-d H:i:s');

		$warn_messages  = $this->db->fetchAll('select m.*
			from ' . $this->schema . '.messages m, ' .
				$this->schema . '.users u
			where m.exp_user_warn = \'f\'
				and u.id = m.id_user
				and u.status in (1, 2)
				and m.validity < ?', [$now]);

		foreach ($warn_messages as $message)
		{
			$user = $this->user_cache_service->get($message['id_user'], $this->schema);

			if (!($user['status'] == 1 || $user['status'] == 2))
			{
				continue;
			}

			$message['type'] = $message['msg_type'] ? 'offer' : 'want';

			$vars = [
				'message' 		=> $message,
				'user_id'		=> $user['id'],
			];

			$mail_template = 'message_extend/';
			$mail_template .= $message['type'] === 'offer' ? 'offer' : 'want';

			$this->mail_queue->queue([
				'to' 				=> $this->mail_addr_user_service->get_active($message['id_user'], $this->schema),
				'schema' 			=> $this->schema,
				'template' 			=> $mail_template,
				'vars' 				=> $vars
			], random_int(0, 5000));

			error_log($mail_template);
			error_log(json_encode($vars));
		}

		if ($update)
		{
			$this->db->executeUpdate('update ' . $this->schema . '.messages
				set exp_user_warn = \'t\'
				where validity < ?', [$now]);
		}
	}

	public function is_enabled():bool
	{
		return $this->config_service->get('msgexpwarnenabled', $this->schema) ? true : false;
	}

	public function get_interval():int
	{
		return 86400;
	}
}
