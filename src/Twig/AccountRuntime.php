<?php declare(strict_types=1);

namespace App\Twig;

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
		return $code . ' ' . $name;
	}

	public function get_full_name(int $id, string $schema):string
	{
		$user = $this->user_cache_service->get($id, $schema);
		return $user['full_name'];
	}

	public function get_name(int $id, string $schema):string
	{
		$user = $this->user_cache_service->get($id, $schema);
		return $user['name'];
	}

	public function get_code(int $id, string $schema):string
	{
		$user = $this->user_cache_service->get($id, $schema);
		return $user['code'];
	}

	public function get_balance(int $id, string $schema):int
	{
		return $this->account_repository->get_balance($id, $schema);
	}

	private function render_status(string $class, string $text):string
	{
		$out = '&nbsp;<small><span class="text-';
		$out .= $class;
		$out .= '">';
		$out .= $text;
		$out .= '</span></small>';
		return $out;
	}

	public function get_status(int $id, string $schema):string
	{

		$user = $this->user_cache_service->get($id, $schema);

		if ($user['is_active'])
		{
			if (isset($user['remote_schema']))
			{
				return $this->render_status('warning', 'InterSysteem');
			}

			if (isset($user['remote_email']))
			{
				return $this->render_status('warning', 'InterSysteem');
			}

			$leaving_users_enabled = $this->config_service->get_bool('users.leaving.enabled', $schema);

			if ($leaving_users_enabled && $user['is_leaving'])
			{
				return $this->render_status('danger', 'Uitstapper');
			}

			$new_users_enabled = $this->config_service->get_bool('users.new.enabled', $schema);

			if ($new_users_enabled)
			{
				$new_user_treshold = $this->config_service->get_new_user_treshold($schema);

				if ($new_user_treshold->getTimestamp() < strtotime($user['activated_at'] . ' UTC'))
				{
					return $this->render_status('success', 'Instapper');
				}
			}

			return '';
		}

		if (isset($user['activated_at']))
		{
			return $this->render_status('inactive', 'Post-actief');
		}

		return $this->render_status('info', 'Pre-actief');
	}
}
