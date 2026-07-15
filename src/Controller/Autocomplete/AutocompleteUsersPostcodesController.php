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
class AutocompleteUsersPostcodesController extends AbstractController
{
  use EtagJsonResponseTrait;

  #[Route(
    '/{schema}/{role_short}/autocomplete/users-postcodes',
    name: 'autocomplete_users_postcodes',
    methods: ['GET'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'users',
    ],
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
