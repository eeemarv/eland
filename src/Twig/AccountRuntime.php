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

	public function get(
    array $context,
    int $user_id,
    string|null $schema = null,
  ):string
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
		$user = $this->user_cache_service->get($user_id, $sch_str);
		$code = $user['code'] ?? '***';
		$name = $user['name'] ?? '***';
		return $code . ' ' . $name;
	}

	public function get_full_name(
    array $context,
    int $user_id,
    string|null $schema = null,
  ):string
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
		$user = $this->user_cache_service->get($user_id, $sch_str);
		return $user['full_name'];
	}

	public function get_name(
    array $context,
    int $user_id,
    string|null $schema = null,
  ):string
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
		$user = $this->user_cache_service->get($user_id, $sch_str);
		return $user['name'];
	}

	public function get_code(
    array $context,
    int $user_id,
    string|null $schema = null,
  ):string
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
		$user = $this->user_cache_service->get($user_id, $sch_str);
		return $user['code'];
	}

	public function get_balance(
    array $context,
    int $user_id,
    string|null $schema = null
  ):int
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
		return $this->account_repository->get_balance($user_id, $sch_str);
	}

	public function get_status(
    array $context,
    int $user_id,
    string|null $schema = null
  ):string
	{
    $sch_str = $schema ?? $context['schema'] ?? null;

		$user = $this->user_cache_service->get($user_id, $sch_str);
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
