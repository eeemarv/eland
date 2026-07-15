<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersRoleCommand;
use App\Form\Type\Users\UsersRoleType;
use App\Repository\UserLogRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersRoleEditController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/users/{id}/role/edit',
    name: 'users_role_edit',
    methods: ['GET', 'POST'],
    requirements: [
      'schema' => '%assert.schema%',
      'role_short' => '%assert.role_short.admin%',
      'id' => '%assert.id%',
    ],
    defaults: [
      'module' => 'users',
    ],
  )]

  public function __invoke(
    Request $request,
    int $id,
    UserRepository $user_repository,
    UserLogRepository $user_log_repository,
    UserCacheService $user_cache_service,
    PageParamsService $pp,
    SessionUserService $su,
  ): Response {
    if ($su->is_owner($id)) {
      throw $this->createAccessDeniedException(
        'You can\'t edit your own role'
      );
    }

    $command = new UsersRoleCommand();

    $user = $user_repository->get(
      id: $id,
      schema: $pp->schema_o(),
    );

    if ($user === false) {
      throw $this->createNotFoundException(
        'User with id ' . $id . ' not found'
      );
    }

    $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);
    $command->role = $user['role'];
    $old_data = (array) $command;

    $form = $this->createForm(
      type: UsersRoleType::class,
      data: $command,
      options: [
        'log_comment_enabled' => true,
      ],
    );
    $form->handleRequest($request);

    if (
      $form->isSubmitted()
      && $form->isValid()
    ) {
      $log_comment = $form->get('log_comment')->getData();

      if ($command->role === $user['role']) {
        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'flash.no_change',
          ]
        );
      } else {
        $user_repository->set_role(
          id: $id,
          role: $command->role,
          schema: $pp->schema_o(),
        );

        $user_cache_service->clear(
          id: $id,
          schema: $pp->schema(),
        );

        $user_log_repository->insert(
          user_id: $id,
          old_data: $old_data,
          new_data: (array) $command,
          comment: $log_comment,
          created_by: $su->id() ?: null,
          route: $pp->route(),
          schema: $pp->schema_o(),
        );

        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'users_role_edit.flash.success',
            'params' => [
              'user' => $user['name'],
              'old_role' => $user['role'],
              'new_role' => $command->role,
            ]
          ],
        );
      }

      return $this->redirectToRoute(
        route: 'users_show',
        parameters: [
          ...$pp->ary(),
          'id' => $id,
        ],
      );
    }

    return $this->render('users/users_role_edit.html.twig', [
      'form' => $form->createView(),
      'user' => $user,
      'id' => $id,
      'is_self' => false,
      'is_intersystem' => $is_intersystem,
    ]);
  }
}
