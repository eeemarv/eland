<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\UserCacheService;
use App\Service\SystemsService;
use App\Cnst\RoleCnst;
use Twig\Extension\RuntimeExtensionInterface;

class MppAryRuntime implements RuntimeExtensionInterface
{
	public function __construct(
		protected UserCacheService $user_cache_service,
		protected SystemsService $systems_service
	)
	{
	}

	private function get_ary(
		string $role_short,
		string $et,
		string $schema
	):array
	{
		$system = $this->systems_service->get_system($schema);

		$mpp_ary = [
			'system'	=> $system,
		];

		if ($et !== '')
		{
			$mpp_ary['et'] = $et;
		}

		if ($role_short !== '')
		{
			$mpp_ary['role_short'] = $role_short;
 		}

		return $mpp_ary;
	}

	public function get(array $context, int $id, string $schema):array
	{
		$role = $this->user_cache_service->get($id, $schema)['role'];
		$role_short = RoleCnst::SHORT[$role] ?? '';

		return $this->get_ary($role_short, $context['et'] ?? '', $schema);
	}

	public function get_admin(array $context, string $schema):array
	{
		return $this->get_ary('a', $context['et'] ?? '', $schema);
	}

	public function get_guest(array $context, string $schema):array
	{
		return $this->get_ary('g', $context['et'] ?? '', $schema);
	}

	public function get_anon(array $context, string $schema):array
	{
		return $this->get_ary('', $context['et'] ?? '', $schema);
	}

	public function mpp(
		array $context,
    array $params,
		string|null $role = null,
    int|null $id = null,
    string|null $rem_schema = null, // refers to route
    string|null $org_schema = null, // refers to receiver
    bool $d_role = false,
	):array
	{
		if (isset($context['schema']))
		{
      $schema = $context['schema'];

      if (isset($rem_schema)&& $rem_schema !== $schema)
      {
			  $params['system'] = $this->systems_service->get_system($rem_schema);
			  $org_system = $this->systems_service->get_system($schema);
			  $params['os'] = $org_system;
			  $params['ets'] = $org_system; // email token system

        if ($d_role)
        {
			    $params['role_short'] = RoleCnst::SHORT['guest'];
        }
      }
      else if (isset($org_schema) && $org_schema !== $schema)
      {
			  $params['system'] = $this->systems_service->get_system($schema);
			  $params['os'] = $this->systems_service->get_system($org_schema);

        if ($d_role)
        {
			    $params['role_short'] = RoleCnst::SHORT['guest'];
        }
      }
      else
      {
        if ($d_role)
        {
          $params['role_short'] = RoleCnst::SHORT['user'];
        }

			  $params['system'] = $this->systems_service->get_system($schema);
      }

      if (isset($role) && isset(RoleCnst::SHORT[$role]))
      {
        $params['role_short'] = RoleCnst::SHORT[$role];
      }
		}

		if (isset($context['email_token']))
		{
			$params['et'] = $context['email_token'];
		}

    if (isset($id))
    {
      $params['id'] = $id;
    }

		return $params;
	}
}
