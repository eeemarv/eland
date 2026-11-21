<?php declare(strict_types=1);

namespace App\Controller\Autocomplete;

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
class AutocompleteUsersPostcodesController extends AbstractController
{
  use EtagJsonResponseTrait;

  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: UsersSubModule::Postcode,
    path: '/autocomplete/users-postcodes',
    name: 'autocomplete_users_postcodes',
    methods: ['GET'],
  )]

  public function __invoke(
    UserRepository $user_repository,
    Request $request,
    PageParamsService $pp,
  ):Response
  {
    $postcodes = $user_repository->get_used_postcodes(
      schema: $pp->schema_o(),
    );

    return $this->etagJsonResponse($request, $postcodes);
  }
}
