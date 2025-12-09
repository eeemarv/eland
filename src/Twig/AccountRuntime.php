<?php declare(strict_types=1);

namespace App\Twig;

use App\Cnst\StatusCnst;
use App\DTO\Schema;
use App\Repository\AccountRepository;
use App\Service\ConfigService;
use App\Service\UserCacheService;
use Twig\Extension\RuntimeExtensionInterface;

class AccountRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		private readonly AccountRepository $account_repository,
		private readonly UserCacheService $user_cache_service,
		private readonly ConfigService $config_service
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
    $schema_o = new Schema($sch_str);
		return $this->account_repository->get_balance(
      account_id: $user_id,
      schema: $schema_o,
    );
	}

	public function get_status(
    array $context,
    int $user_id,
    string|null $schema = null
  ):string
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
    $schema_o = new Schema($sch_str);

		$user = $this->user_cache_service->get($user_id, $sch_str);
		$status_id = $user['status'];

    if (isset($user['adate'])
      && $status_id === 1
		)
    {
      $new_users_enabled = $this->config_service->get_bool(
        config_id: 'users.new.enabled',
        schema: $schema_o,
      );

      if ($new_users_enabled)
      {
        $new_user_treshold = $this->config_service->get_new_user_treshold(
          schema: $schema_o,
        );

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
			$leaving_users_enabled = $this->config_service->get_bool(
        config_id: 'users.leaving.enabled',
        schema: $schema_o,
      );

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

	public function is_new(
    array $context,
    int $user_id,
    string|null $schema = null
  ):bool
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
    $schema_o = new Schema($sch_str);
		$user = $this->user_cache_service->get($user_id, $sch_str);

    if ($user['status'] !== 1)
    {
      return false;
    }
    if (!isset($user['adate']))
    {
      return false;
    }
    if (!$this->config_service->get_bool(
      config_id: 'users.new.enabled',
      schema: $schema_o,
    ))
    {
      return false;
    }
    $new_user_treshold = $this->config_service->get_new_user_treshold(
      schema: $schema_o,
    );

    if ($new_user_treshold->getTimestamp() < strtotime($user['adate'] . ' UTC'))
    {
      return true;
    }
    return false;
	}

	public function is_leaving(
    array $context,
    int $user_id,
    string|null $schema = null
  ):bool
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
    $schema_o = new Schema($sch_str);
		$user = $this->user_cache_service->get($user_id, $sch_str);

		if ($user['status'] !== 2)
		{
			return false;
		}
    if (!$this->config_service->get_bool(
      config_id: 'users.leaving.enabled',
      schema: $schema_o,
    ))
    {
      return false;
    }
    return true;
	}

	public function is_inactive(
    array $context,
    int $user_id,
    string|null $schema = null
  ):bool
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
		$user = $this->user_cache_service->get($user_id, $sch_str);

		if ($user['status'] === 0)
		{
			return true;
		}
    return false;
	}

	public function is_ip(
    array $context,
    int $user_id,
    string|null $schema = null
  ):bool
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
		$user = $this->user_cache_service->get($user_id, $sch_str);

		if ($user['status'] === 5)
		{
			return true;
		}
    return false;
	}

	public function is_im(
    array $context,
    int $user_id,
    string|null $schema = null
  ):bool
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
		$user = $this->user_cache_service->get($user_id, $sch_str);

		if ($user['status'] === 6)
		{
			return true;
		}
    return false;
	}

	public function is_extern(
    array $context,
    int $user_id,
    string|null $schema = null
  ):bool
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
		$user = $this->user_cache_service->get($user_id, $sch_str);

		if ($user['status'] === 7)
		{
			return true;
		}
    return false;
	}

	public function is_active(
    array $context,
    int $user_id,
    string|null $schema = null
  ):bool
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
		$user = $this->user_cache_service->get($user_id, $sch_str);

		if ($user['status'] === 1)
		{
			return true;
		}
		if ($user['status'] === 2)
		{
			return true;
		}
    return false;
	}
}
