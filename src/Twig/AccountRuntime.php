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
    int|array $user,
    string|null $schema = null,
  ):string
	{
    if (!is_array($user))
    {
      $sch_str = $schema ?? $context['schema'] ?? null;
      $user = $this->user_cache_service->get($user, $sch_str);
    }

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
    int|array $user,
    string|null $schema = null,
  ):string
	{
    if (is_array($user))
    {
		  return $user['name'];
    }

    $sch_str = $schema ?? $context['schema'] ?? null;
		return $this->user_cache_service->get($user, $sch_str)['name'];
	}

	public function get_code(
    array $context,
    int|array $user,
    string|null $schema = null,
  ):string
	{
    if (is_array($user))
    {
      return $user['code'];
    }

    $sch_str = $schema ?? $context['schema'] ?? null;
    return $this->user_cache_service->get($user, $sch_str)['code'];
	}

	public function get_balance(
    array $context,
    int|array $user,
    string|null $schema = null
  ):int
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
    $schema_o = new Schema($sch_str);
    $user_id = is_array($user) ? $user['id'] : $user;
		return $this->account_repository->get_balance(
      account_id: $user_id,
      schema: $schema_o,
    );
	}

	public function is_new(
    array $context,
    int|array $user,
    string|null $schema = null
  ):bool
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
    $schema_o = new Schema($sch_str);
    if (is_int($user))
    {
		  $user = $this->user_cache_service->get($user, $sch_str);
    }

    if (!$user['is_active'])
    {
      return false;
    }

    if (!isset($user['activated_at']))
    {
      return false;
    }

    if (isset($user['remote_schema']))
    {
      return false;
    }

    if (isset($user['remote_email']))
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

    if ($new_user_treshold->getTimestamp() < strtotime($user['activated_at'] . ' UTC'))
    {
      return true;
    }

    return false;
	}

	public function is_leaving(
    array $context,
    int|array $user,
    string|null $schema = null
  ):bool
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
    $schema_o = new Schema($sch_str);

    if (is_int($user))
    {
		  $user = $this->user_cache_service->get($user, $sch_str);
    }

    if (!$user['is_active'])
    {
      return false;
    }

		if (!$user['is_leaving'])
		{
			return false;
		}

    if (isset($user['remote_schema']))
    {
      return false;
    }

    if (isset($user['remote_email']))
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

	public function is_intersystem(
    array $context,
    int|array $user,
    string|null $schema = null
  ):bool
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
    if (is_int($user))
    {
      $user = $this->user_cache_service->get($user, $sch_str);
    }

    if (!$user['is_active'])
    {
      return false;
    }

		if (isset($user['remote_schema']))
		{
			return true;
		}

    if (isset($user['remote_email']))
    {
      return true;
    }

    return false;
	}

	public function is_active(
    array $context,
    int|array $user,
    string|null $schema = null
  ):bool
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
    if (is_int($user))
    {
      $user = $this->user_cache_service->get($user, $sch_str);
    }

    if (!$user['is_active'])
    {
      return false;
    }

    return true;
	}

	public function is_pre_active(
    array $context,
    int|array $user,
    string|null $schema = null
  ):bool
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
    if (is_int($user))
    {
      $user = $this->user_cache_service->get($user, $sch_str);
    }

    if ($user['is_active'])
    {
      return false;
    }

    if (isset($user['activated_at']))
    {
      return false;
    }

    return true;
	}

	public function is_post_active(
    array $context,
    int|array $user,
    string|null $schema = null
  ):bool
	{
    $sch_str = $schema ?? $context['schema'] ?? null;
    if (is_int($user))
    {
      $user = $this->user_cache_service->get($user, $sch_str);
    }

    if ($user['is_active'])
    {
      return false;
    }

    if (isset($user['activated_at']))
    {
      return true;
    }

    return false;
	}
}
