<?php declare(strict_types=1);

namespace App\Twig;

use App\Cnst\StatusCnst;
use App\Repository\AccountRepository;
use App\Service\ConfigService;
use App\Service\UserCacheService;
use Twig\Extension\RuntimeExtensionInterface;

class AccountRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected AccountRepository $account_repository,
		protected UserCacheService $user_cache_service,
		protected ConfigService $config_service
	)
	{
	}

	public function get(int $id, string $schema):string
	{
		$user = $this->user_cache_service->get($id, $schema);
		$code = $user['code'] ?? '***';
		$name = $user['name'] ?? '***';
		return htmlspecialchars($code . ' ' . $name);
	}

	public function get_full_name(int $id, string $schema):string
	{
		$user = $this->user_cache_service->get($id, $schema);
		return htmlspecialchars($user['full_name']);
	}

	public function get_name(int $id, string $schema):string
	{
		$user = $this->user_cache_service->get($id, $schema);
		return htmlspecialchars($user['name']);
	}

	public function get_code(int $id, string $schema):string
	{
		$user = $this->user_cache_service->get($id, $schema);
		return htmlspecialchars($user['code']);
	}

	public function get_balance(int $id, string $schema):int
	{
		return $this->account_repository->get_balance($id, $schema);
	}

	public function get_status(int $id, string $schema):string
	{

		$user = $this->user_cache_service->get($id, $schema);
		$status_id = $user['status'];

        if (isset($user['adate'])
            && $status_id === 1
		)
        {
			$new_users_enabled = $this->config_service->get_bool('users.new.enabled', $schema);

			if ($new_users_enabled)
			{
				$new_user_treshold = $this->config_service->get_new_user_treshold($schema);

				if ($new_user_treshold->getTimestamp() < strtotime($user['adate'] . ' UTC'))
				{
					$status_id = 3;
				}
			}
        }

		if ($status_id === 1)
		{
			return '';
		}

		if ($status_id === 2)
		{
			$leaving_users_enabled = $this->config_service->get_bool('users.leaving.enabled', $schema);

			if (!$leaving_users_enabled)
			{
				return '';
			}
		}

		$out = '&nbsp;<small><span class="text-';
		$out .= StatusCnst::CLASS_ARY[$status_id];
		$out .= '">';
		$out .= StatusCnst::RENDER_ARY[$status_id];
		$out .= '</span></small>';

		return $out;
	}
}
