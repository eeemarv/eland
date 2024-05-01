<?php declare(strict_types=1);

namespace App\Service;

use App\Cache\UserCache;
use App\Cache\UserInvalidateCache;
use Doctrine\DBAL\Connection as Db;
use App\Queue\MailQueue;
use Psr\Log\LoggerInterface;
use App\Service\ConfigService;
use App\Render\AccountRender;
use App\Repository\AccountRepository;

class AutoDeactivateService
{
	public function __construct(
		protected Db $db,
		protected AlertService $alert_service,
		protected LoggerInterface $logger,
		protected UserCache $user_cache,
		protected UserInvalidateCache $user_invalidate_cache,
		protected AccountRepository $account_repository,
		protected MailQueue $mail_queue,
		protected MailAddrSystemService $mail_addr_system_service,
		protected MailAddrUserService $mail_addr_user_service,
		protected ConfigService $config_service,
		protected SessionUserService $su,
		protected ResponseCacheService $response_cache_service,
		protected AccountRender $account_render
	)
	{
	}

	public function process(
		int $user_id,
		string $schema
	):void
	{
		if (!$this->config_service->get_bool('users.leaving.enabled', $schema))
		{
			return;
		}

		if (!$this->config_service->get_bool('users.leaving.auto_deactivate', $schema))
		{
			return;
		}

		$user = $this->user_cache->get($user_id, $schema);

		if ($user['status'] !== 2)
		{
			return;
		}

        $balance_equilibrium = $this->config_service->get_int('accounts.equilibrium', $schema) ?? 0;
		$balance = $this->account_repository->get_balance($user_id, $schema);

		if ($balance !== $balance_equilibrium)
		{
			return;
		}

		$this->db->update($schema . '.users', ['status'	=> 0], ['id' => $user_id]);
		$this->user_invalidate_cache->user($user_id, $schema);
		$this->response_cache_service->clear_cache($schema);

		$this->logger->info('Auto-deactivated: user ' .
			$this->account_render->str($user_id, $schema),
			['schema' => $schema]);

		$to = $this->mail_addr_user_service->get($user_id, $schema);

		$vars = [
			'user_id'			=> $user_id,
			'user_has_email'	=> count($to) > 0,
		];

		$this->mail_queue->queue([
			'schema'	=> $schema,
			'to' 		=> $to,
			'template'	=> 'auto_deactivate/user',
			'vars'		=> $vars,
		], 4000);

		$this->mail_queue->queue([
			'schema'	=> $schema,
			'to' 		=> $this->mail_addr_system_service->get_admin($schema),
			'template'	=> 'auto_deactivate/admin',
			'vars'		=> $vars,
		], 4000);

		if ($this->su->schema() === $schema)
		{
			if ($this->su->id() === $user_id)
			{
				$this->alert_service->warning('Je account heeft het
					uitstappers-saldo bereikt en werd
					automatisch gedeactiveerd.');
			}
			else
			{
				$this->alert_service->warning('Het account ' .
					$this->account_render->str($user_id, $schema) .
					' heeft het uitstappers-saldo bereikt en werd
					automatisch gedesactiveerd.');
			}
		}
	}
}
