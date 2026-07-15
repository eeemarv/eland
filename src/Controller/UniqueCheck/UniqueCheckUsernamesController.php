<?php declare(strict_types=1);

namespace App\Controller\UniqueCheck;

use App\Repository\DocRepository;
use App\Repository\UserRepository;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UniqueCheckUsernamesController extends AbstractController
{
  use EtagJsonResponseTrait;

  #[Route(
    '/{schema}/{role_short}/unique-check/usernames',
    name: 'unique_check_usernames',
    methods: ['GET'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.user%',
    ],
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
