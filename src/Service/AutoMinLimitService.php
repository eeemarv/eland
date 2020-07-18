<?php declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Doctrine\DBAL\Connection as Db;
use App\Service\ConfigService;
use App\Service\UserCacheService;
use App\Render\AccountRender;

class AutoMinLimitService
{
	protected LoggerInterface $logger;
	protected Db $db;
	protected ConfigService $config_service;
	protected UserCacheService $user_cache_service;
	protected AccountRender $account_render;
	protected SessionUserService $su;

	protected array $exclude_to = [];
	protected array $exclude_from = [];
	protected bool $enabled = false;
	protected ?int $global_min_limit;
	protected ?int $percentage;
	protected string $schema;

	public function __construct(
		LoggerInterface $logger,
		Db $db,
		ConfigService $config_service,
		UserCacheService $user_cache_service,
		SessionUserService $su,
		AccountRender $account_render
	)
	{
		$this->logger = $logger;
		$this->db = $db;
		$this->user_cache_service = $user_cache_service;
		$this->config_service = $config_service;
		$this->su = $su;
		$this->account_render = $account_render;
	}

	public function init(string $schema):self
	{
		$this->schema = $schema;

		$this->global_min_limit = $this->config_service->get_int('accounts.limits.global.min', $this->schema);
		$this->enabled = $this->config_service->get_bool('accounts.limits.auto_min.enabled', $this->schema);
		$this->percentage = $this->config_service->get_int('accounts.limits.auto_min.percentage', $this->schema);
		$exclude_to_str = $this->config_service->get_str('accounts.limits.auto_min.exclude.to', $this->schema);
		$exclude_from_str = $this->config_service->get_str('accounts.limits.auto_min.exclude.from', $this->schema);

		$exclude_to_ary = explode(',', $exclude_to_str);
		$exclude_from_ary = explode(',', $exclude_from_str);

		$this->exclude_to = [];
		$this->exclude_from = [];

		foreach($exclude_to_ary as $ex_to)
		{
			$this->exclude_to[trim(strtolower($ex_to))] = true;
		}

		foreach($exclude_from_ary as $ex_from)
		{
			$this->exclude_from[trim(strtolower($ex_from))] = true;
		}

		return $this;
	}

	public function process(
		int $from_id,
		int $to_id,
		int $amount
	):void
	{
		if (!$this->enabled)
		{
			$this->logger->debug('autominlimit not enabled',
				['schema' => $this->schema]);
			return;
		}

		if (!isset($this->percentage))
		{
			$this->logger->debug('autominlimit percentage not set',
				['schema' => $this->schema]);
			return;
		}

		if ($this->percentage < 1)
		{
			$this->logger->debug('autominlimit percentage zero or negative',
				['schema' => $this->schema]);
			return;
		}

		if (!isset($this->percentage) || !$this->percentage)
		{
			$this->logger->debug('autominlimit percentage is not set or zero.',
				['schema' => $this->schema]);
			return;
		}

		$user = $this->user_cache_service->get($to_id, $this->schema);

		if (!$user || !is_array($user))
		{
			$this->logger->debug('autominlimit: to user not found',
				['schema' => $this->schema]);
			return;
		}

		if ($user['status'] != 1)
		{
			$this->logger->debug('autominlimit: to user not active. ' .
				$this->account_render->str_id($user['id'], $this->schema),
				['schema' => $this->schema]);
			return;
		}

		if (isset($this->exclude_to[strtolower($user['code'])]))
		{
			$this->logger->debug('autominlimit: to user is excluded ' .
				$this->account_render->str_id($to_id, $this->schema),
				['schema' => $this->schema]);
			return;
		}

		if (!isset($user['minlimit']))
		{
			$this->logger->debug('autominlimit: to user has no minlimit. ' .
				$this->account_render->str_id($user['id'], $this->schema),
				['schema' => $this->schema]);
			return;
		}

		if (isset($this->global_min_limit)
			&& $user['minlimit'] < $this->global_min_limit)
		{
			$this->logger->debug('autominlimit: to user minlimit is lower than global system min limit. ' .
				$this->account_render->str_id($user['id'], $this->schema),
				['schema' => $this->schema]);
			return;
		}

		$from_user = $this->user_cache_service->get($from_id, $this->schema);

		if (!$from_user || !is_array($from_user))
		{
			$this->logger->debug('autominlimit: from user not found.',
				['schema' => $this->schema]);
			return;
		}

		if (!$from_user['code'])
		{
			$this->logger->debug('autominlimit: from user has no code.',
				['schema' => $this->schema]);
			return;
		}

		if (isset($this->exclude_from[strtolower($from_user['code'])]))
		{
			$this->logger->debug('autominlimit: from user is excluded ' .
				$this->account_render->str_id($from_id, $this->schema),
				['schema' => $this->schema]);
			return;
		}

		$extract = round(($this->percentage / 100) * $amount);

		if (!$extract)
		{
			$debug = 'autominlimit: (extract = 0) ';
			$debug .= 'no new minlimit for user ';
			$debug .= $this->account_render->str_id($user['id'], $this->schema);
			$this->logger->debug($debug, ['schema' => $this->schema]);
			return;
		}

		$new_minlimit = $user['minlimit'] - $extract;

		$insert = [
			'account_id'	=> $to_id,
			'is_auto'		=> 't',
			'created_by'	=> $this->su->id(),
		];

		if (isset($this->global_min_limit)
			&& $new_minlimit <= $this->global_min_limit)
		{
			$insert['min_limit'] = null;

			$this->db->update($this->schema . '.users', [
				'minlimit'		=> null,
			], ['id' => $to_id]);

			$debug = 'autominlimit: minlimit reached global min limit, ';
			$debug .= 'individual min limit erased for user ';
			$debug .= $this->account_render->str_id($user['id'], $this->schema);
			$this->logger->debug($debug, ['schema' => $this->schema]);
		}
		else
		{
			$insert['min_limit'] = $new_minlimit;

			$this->db->update($this->schema . '.users',
				['minlimit' => $new_minlimit],
				['id' => $to_id]);

			$this->logger->info('autominlimit: new minlimit : ' .
				$new_minlimit .
				' for user ' .
				$this->account_render->str_id($user['id'], $this->schema),
				['schema' => $this->schema]);
		}

		$this->user_cache_service->clear($to_id, $this->schema);

		$this->db->insert($this->schema . '.min_limit', $insert);

		return;
	}
}
