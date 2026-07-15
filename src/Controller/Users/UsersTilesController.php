<?php declare(strict_types=1);

namespace App\Controller\Users;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Form\Type\Filter\QTextSearchFilterType;
use App\Repository\UserRepository;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersTilesController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/users/tiles/{status}',
    name: 'users_tiles',
    methods: ['GET'],
    priority: 20,
    requirements: [
      'status'        => '%assert.account.status2%',
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.guest%',
    ],
    defaults: [
      'status'        => 'active',
      'module'        => 'users',
    ],
  )]

  public function __invoke(
    Request $request,
    string $status,
    UserRepository $user_repository,
    PageParamsService $pp,
  ):Response
  {
    if (!$pp->is_admin() && !in_array($status, ['active', 'new', 'leaving']))
    {
      throw $this->createAccessDeniedException(
        'No access for status: ' . $status
      );
    }

    $filter_form = $this->createForm(QTextSearchFilterType::class);
    $filter_form->handleRequest($request);

    $users = $user_repository->get_all_by_status(
      status: $status,
      schema: $pp->schema_o(),
    );

    return $this->render('users/users_tiles.html.twig', [
      'users'         => $users,
      'filter_form'   => $filter_form->createView(),
    ]);
  }
}
