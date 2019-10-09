<?php declare(strict_types=1);

namespace App\Service;

use App\Service\XdbService;
use Psr\Log\LoggerInterface;
use Doctrine\DBAL\Connection as Db;
use App\Service\ConfigService;
use App\Service\UserCacheService;
use App\Render\AccountRender;

class AutoMinLimitService
{
	protected $logger;
	protected $xdb_service;
	protected $db;
	protected $config_service;
	protected $user_cache_service;
	protected $account_render;

	protected $exclusive;
	protected $trans_exclusive;
	protected $enabled = false;
	protected $trans_percentage;
	protected $group_minlimit;
	protected $schema;

	public function __construct(
		LoggerInterface $logger,
		XdbService $xdb_service,
		Db $db,
		ConfigService $config_service,
		UserCacheService $user_cache_service,
		AccountRender $account_render
	)
	{
		$this->logger = $logger;
		$this->xdb_service = $xdb_service;
		$this->db = $db;
		$this->user_cache_service = $user_cache_service;
		$this->config_service = $config_service;
		$this->account_render = $account_render;
	}

	public function init(string $schema):self
	{
		$this->schema = $schema;

		$row = $this->xdb_service->get('setting',
			'autominlimit',
			$this->schema);

		if (!$row)
		{
			return $this;
		}

		$data = $row['data'];

		$exclusive = explode(',', $data['exclusive']);
		$trans_exclusive = explode(',', $data['trans_exclusive']);

		array_walk($exclusive, function(&$val){ return strtolower(trim($val)); });
		array_walk($trans_exclusive, function(&$val){ return strtolower(trim($val)); });

		$this->exclusive = array_fill_keys($exclusive, true);
		$this->trans_exclusive = array_fill_keys($trans_exclusive, true);

		$this->enabled = $data['enabled'];
		$this->trans_percentage = $data['trans_percentage'];

		$this->group_minlimit = $this->config_service->get('minlimit', $this->schema);

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

		if (!$this->trans_percentage)
		{
			$this->logger->debug('autominlimit percentage is zero.',
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

		if ($user['minlimit'] === '')
		{
			$this->logger->debug('autominlimit: to user has no minlimit. ' .
				$this->account_render->str_id($user['id'], $this->schema),
				['schema' => $this->schema]);
			return;
		}

		if ($this->group_minlimit !== '' && $user['minlimit'] < $this->group_minlimit)
		{
			$this->logger->debug('autominlimit: to user minlimit is lower than group minlimit. ' .
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

		if (!$from_user['letscode'])
		{
			$this->logger->debug('autominlimit: from user has no letscode.',
				['schema' => $this->schema]);
			return;
		}

		$extract = round(($this->trans_percentage / 100) * $amount);

		if (!$extract)
		{
			$debug = 'autominlimit: (extract = 0) ';
			$debug .= 'no new minlimit for user ';
			$debug .= $this->account_render->str_id($user['id'], $this->schema);
			$this->logger->debug($debug, ['schema' => $this->schema]);
			return;
		}

		$new_minlimit = $user['minlimit'] - $extract;

		if ($this->group_minlimit !== '' && $new_minlimit <= $this->group_minlimit)
		{
			$this->xdb_service->set('autominlimit',
				(string) $to_id,
				['minlimit' => '', 'erased' => true],
				$this->schema);
			$this->db->update($this->schema . '.users',
				['minlimit' => -999999999],
				['id' => $to_id]);
			$this->user_cache_service->clear($to_id, $this->schema);

			$debug = 'autominlimit: minlimit reached group minlimit, ';
			$debug .= 'individual minlimit erased for user ';
			$debug .= $this->account_render->str_id($user['id'], $this->schema);
			$this->logger->debug($debug, ['schema' => $this->schema]);
			return;
		}

		$this->xdb_service->set('autominlimit', (string) $to_id, [
			'minlimit' => $new_minlimit,
		], $this->schema);

		$this->db->update($this->schema . '.users',
			['minlimit' => $new_minlimit],
			['id' => $to_id]);

		$this->user_cache_service->clear($to_id, $this->schema);

		$this->logger->info('autominlimit: new minlimit : ' .
			$new_minlimit .
			' for user ' .
			$this->account_render->str_id($user['id'], $this->schema),
			['schema' => $this->schema]);

		return;
	}
}
