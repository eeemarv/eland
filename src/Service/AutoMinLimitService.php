<?php declare(strict_types=1);

namespace App\Service;

use App\DTO\Schema;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use App\Service\ConfigService;
use App\Service\UserCacheService;
use App\Render\AccountRender;
use App\Repository\AccountRepository;

class AutoMinLimitService
{
	public function __construct(
		protected Db $db,
		protected LoggerInterface $logger,
		protected UserCacheService $user_cache_service,
		protected AccountRepository $account_repository,
		protected ConfigService $config_service,
		protected SessionUserService $su,
		protected AccountRender $account_render
	)
	{
	}

	public function process(
		int $from_id,
		int $to_id,
		int $amount,
		string $schema
	):void
	{
    $schema_o = new Schema($schema);

		if (!$this->config_service->get_bool(
      config_id: 'accounts.limits.auto_min.enabled',
      schema: $schema_o,
    ))
		{
			$this->logger->debug('autominlimit not enabled',
				['schema' => $schema]);
			return;
		}

		if (!$this->config_service->get_bool(
      config_id: 'accounts.limits.enabled',
      schema: $schema_o,
    ))
		{
			$this->logger->debug('no autominlimit: limits not enabled',
				['schema' => $schema]);
			return;
		}

		$global_min_limit = $this->config_service->get_int('accounts.limits.global.min', $schema);
		$percentage = $this->config_service->get_int('accounts.limits.auto_min.percentage', $schema);

		if (!isset($percentage))
		{
			$this->logger->debug('autominlimit percentage not set',
				['schema' => $schema]);
			return;
		}

		if ($percentage < 1)
		{
			$this->logger->debug('autominlimit percentage zero or negative',
				['schema' => $schema]);
			return;
		}

		if (!isset($percentage) || !$percentage)
		{
			$this->logger->debug('autominlimit percentage is not set or zero.',
				['schema' => $schema]);
			return;
		}

		$to_user = $this->user_cache_service->get($to_id, $schema);

		if (!$to_user)
		{
			$this->logger->debug('autominlimit: to user not found',
				['schema' => $schema]);
			return;
		}

		if ($to_user['status'] != 1)
		{
			$this->logger->debug('autominlimit: to user not active. ' .
				$this->account_render->str_id($to_id, $schema),
				['schema' => $schema]);
			return;
		}

		$min_limit = $this->account_repository->get_min_limit($to_id, $schema);

		if (!isset($min_limit))
		{
			$this->logger->debug('autominlimit: to user has no minlimit. ' .
				$this->account_render->str_id($to_id, $schema),
				['schema' => $schema]);
			return;
		}

		if (isset($global_min_limit)
			&& $min_limit < $global_min_limit)
		{
			$this->logger->debug('autominlimit: to user minlimit is lower than global system min limit. ' .
				$this->account_render->str_id($to_id, $schema),
				['schema' => $schema]);
			return;
		}

		$from_user = $this->user_cache_service->get($from_id, $schema);

		if (!$from_user || !is_array($from_user))
		{
			$this->logger->debug('autominlimit: from user not found.',
				['schema' => $schema]);
			return;
		}

		if (!$from_user['code'])
		{
			$this->logger->debug('autominlimit: from user has no code.',
				['schema' => $schema]);
			return;
		}

		$extract = round(($percentage / 100) * $amount);

		if (!$extract)
		{
			$debug = 'autominlimit: (extract = 0) ';
			$debug .= 'no new minlimit for user ';
			$debug .= $this->account_render->str_id($to_id, $schema);
			$this->logger->debug($debug, ['schema' => $schema]);
			return;
		}

		$new_min_limit = $min_limit - $extract;

		$insert = [
			'account_id'	=> $to_id,
			'is_auto'		=> 't',
			'created_by'	=> $this->su->id(),
		];

		if (isset($global_min_limit)
			&& $new_min_limit <= $global_min_limit)
		{
			$insert['min_limit'] = null;

			$debug = 'autominlimit: min limit reached global min limit, ';
			$debug .= 'individual min limit erased for user ';
			$debug .= $this->account_render->str_id($to_id, $schema);
			$this->logger->debug($debug, ['schema' => $schema]);
		}
		else
		{
			$insert['min_limit'] = $new_min_limit;

			$this->logger->info('autominlimit: new minlimit : ' .
				$new_min_limit .
				' for user ' .
				$this->account_render->str_id($to_id, $schema),
				['schema' => $schema]);
		}

		$this->db->insert($schema . '.min_limit', $insert);

		return;
	}
}
