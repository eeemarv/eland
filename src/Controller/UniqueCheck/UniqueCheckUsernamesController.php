<?php declare(strict_types=1);

namespace App\Controller\UniqueCheck;

use App\Repository\UserRepository;
use App\Routing\AccessLevel;
use App\Routing\AppModule;
use App\Routing\SchemaRoute;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class UniqueCheckUsernamesController extends AbstractController
{
  use EtagJsonResponseTrait;

  #[SchemaRoute(
    access_level: AccessLevel::User,
    module: AppModule::Users,
    path: '/unique-check/usernames',
    name: 'unique_check_usernames',
    methods: ['GET'],
  )]

  public function __invoke(
    UserRepository $user_repository,
    Request $request,
    PageParamsService $pp,
  ):Response
  {
    $usernames = $user_repository->get_all_names(
      schema: $pp->schema_o(),
    );

    return $this->etagJsonResponse($request, $usernames);
  }
}
