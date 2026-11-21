<?php declare(strict_types=1);

namespace App\Controller\UniqueCheck;

use App\Repository\UserRepository;
use App\Routing\AccessLevel;
use App\Routing\SchemaRoute;
use App\Routing\UsersSubModule;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class UniqueCheckAccountCodesController extends AbstractController
{
  use EtagJsonResponseTrait;

  #[SchemaRoute(
    access_level: AccessLevel::User,
    module: UsersSubModule::Accounts,
    path: '/unique-check/account-codes',
    name: 'unique_check_account_codes',
    methods: ['GET'],
  )]

  public function __invoke(
    UserRepository $user_repository,
    Request $request,
    PageParamsService $pp,
  ):Response
  {
    $account_codes = $user_repository->get_all_account_codes(
      schema: $pp->schema_o(),
    );

    return $this->etagJsonResponse($request, $account_codes);
  }
}
