<?php declare(strict_types=1);

namespace App\Controller\Autocomplete;

use App\Repository\UserRepository;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SystemsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsController]
class AutocompleteAccountsController extends AbstractController
{
  use EtagJsonResponseTrait;

  #[Route(
    '/{schema}/{role_short}/autocomplete/accounts/{group}',
    name: 'autocomplete_accounts',
    methods: ['GET'],
    requirements: [
      'group'         => '%assert.account.group%',
      'role_short'    => '%assert.role_short.guest%',
    ],
    defaults: [
      'group'         => 'active',
      'module'        => 'users',
    ],
  )]

  public function __invoke(
    string $group,
    UserRepository $user_repository,
    Request $request,
    SystemsService $systems_service,
    ConfigService $config_service,
    ItemAccessService $item_access_service,
    PageParamsService $pp,
    UrlGeneratorInterface $url_generator,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'accounts.enabled',
      schema: $pp->schema_o()
    ))
    {
      throw $this->createAccessDeniedException(
        'Accounts module not enabled'
      );
    }

    $inter_ary = $systems_service->get_inter_ary(
      schema: $pp->schema(),
    );

    //assert.account.group: 'all|active|active-users|users|intersystems|email-intersystems|inactive'

    $allow_groups_for_users = [
      'active',
      'active-users',
      'intersystems',
      'email-intersystems',
    ];

    if (!$pp->is_admin() && !in_array($group, $allow_groups_for_users))
    {
      throw $this->createAccessDeniedException(
        'No access.'
      );
    }

    $limits_enabled = $config_service->get_bool(
      config_id: 'accounts.limits.enabled',
      schema: $pp->schema_o(),
    );

    $accounts = $user_repository->get_for_autocomplete(
      account_group: $group,
      limits_enabled: $limits_enabled,
      schema: $pp->schema_o(),
    );

    if (!$pp->is_guest())
    {
      foreach ($accounts as $i => $a)
      {
        if (!isset($a['remote_schema']))
        {
          continue;
        }
        if (!isset($inter_ary[$a['remote_schema']]))
        {
          continue;
        }
        $accounts[$i]['fetch_accounts'] = $url_generator->generate(
          name: 'autocomplete_accounts',
          parameters: [
            'schema'  => $a['remote_schema'],
            'role_short'  => 'g',
            'group' => 'active-users',
            'os'  => $pp->schema(),
          ],
        );
      }      
    }

    $new_users_enabled = $config_service->get_bool(
      config_id: 'users.new.enabled',
      schema: $pp->schema_o(),
    );

    $leaving_users_enabled = $config_service->get_bool(
      config_id: 'users.leaving.enabled',
      schema: $pp->schema_o(),
    );

    $show_new_status = $new_users_enabled;

    if ($show_new_status)
    {
      $new_users_access = $config_service->get_str(
        config_id: 'users.new.access',
        schema: $pp->schema_o(),
      );
      $show_new_status = $item_access_service->is_visible($new_users_access);
    }

    $show_leaving_status = $leaving_users_enabled;

    if ($show_leaving_status)
    {
      $leaving_users_access = $config_service->get_str(
        config_id: 'users.leaving.access',
        schema: $pp->schema_o(),
      );
      $show_leaving_status = $item_access_service->is_visible($leaving_users_access);
    }

    $new_treshold = $config_service->get_new_user_treshold(
        schema: $pp->schema_o(),
      )->format('Y-m-d H:i:s');

    if ($limits_enabled) 
    {
      $min_limit = $config_service->get_int(
        config_id: 'accounts.limits.global.min',
        schema: $pp->schema_o(),
      );

      $max_limit = $config_service->get_int(
        config_id: 'accounts.limits.global.max',
        schema: $pp->schema_o(),
      );      
    }

    $currency_name = $config_service->get_str(
      config_id: 'transactions.currency.name',
      schema: $pp->schema_o(),
    );

    $per_hour_ratio = $config_service->get_int(
      config_id: 'transactions.currency.per_hour_ratio',
      schema: $pp->schema_o(),
    );

    $timebased_en = $config_service->get_bool(
      config_id: 'transactions.currency.timebased_en',
      schema: $pp->schema_o(),
    );

    return $this->etagJsonResponse(
      request: $request, 
      data: [
        'accounts'  => $accounts,
        'new_treshold' => $new_treshold,
        'show_new_status' => $show_new_status,
        'show_leaving_status' => $show_leaving_status,
        'min_limit' => $min_limit ??  null,
        'max_limit' => $max_limit ?? null,
        'currency_name' => $currency_name,
        'timebased_en'  => $timebased_en,
        'per_hour_ratio' => $per_hour_ratio,
      ]
    );
  }
}
