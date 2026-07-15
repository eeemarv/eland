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

    $allow_groups_for_users = [
      'active',
      'active-users',
      'intersystems',
      'email-intersystems',
    ];

    if (!$pp->is_admin() && !in_array($group, $allow_groups_for_users))
    {
      throw $this->createAccessDeniedException('No access.');
    }

    $accounts = $user_repository->get_for_autocomplete(
      account_group: $group,
      schema: $pp->schema_o(),
    );

    return $this->etagJsonResponse($request, $accounts);
  }
}
