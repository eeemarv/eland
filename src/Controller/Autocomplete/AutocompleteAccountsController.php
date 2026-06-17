<?php declare(strict_types=1);

namespace App\Controller\Autocomplete;

use App\Repository\UserRepository;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

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
      'schema'        => '%assert.schema%',
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
    PageParamsService $pp,
  ):Response
  {
    //assert.account.group: 'all|active|active-users|users|intersystems|email-intersystems|inactive'

    $active_users_included = false;
    $active_eland_intersystems_included = false;
    $active_email_intersystems_included = false;
    $inactive_users_included = false;
    $inactive_intersystems_included = false;

    switch ($group)
    {
      case 'all':
        $active_users_included = true;
        $active_eland_intersystems_included = true;
        $active_email_intersystems_included = true;
        $inactive_users_included = true;
        $inactive_intersystems_included = true;
        break;
      case 'active':
        $active_users_included = true;
        $active_eland_intersystems_included = true;
        $active_email_intersystems_included = true;
        break;
      case 'active-users':
        $active_users_included = true;
        break;
      case 'users':
        $active_users_included = true;
        $inactive_users_included = true;
        break;
      case 'intersystems':
        $active_eland_intersystems_included = true;
        $active_email_intersystems_included = true;
        break;
      case 'email-intersystems':
        $active_email_intersystems_included = true;
        break;
      case 'inactive':
        $inactive_users_included = true;
        $inactive_intersystems_included = true;
        break;
      default:
        throw $this->createNotFoundException('Invalid group: ' . $group);
        break;
    }

    if (!$pp->is_admin() && $inactive_users_included)
    {
      throw $this->createAccessDeniedException('No access.');
    }
    if (!$pp->is_admin() && $inactive_intersystems_included)
    {
      throw $this->createAccessDeniedException('No access.');
    }

    $accounts = $user_repository->get_for_autocomplete(
      active_users_included: $active_users_included,
      active_eland_intersystems_included: $active_eland_intersystems_included,
      active_email_intersystems_included: $active_email_intersystems_included,
      inactive_users_included: $inactive_users_included,
      inactive_intersystems_included: $inactive_intersystems_included,
      schema: $pp->schema_o(),
    );

    return $this->etagJsonResponse($request, $accounts);
  }
}
