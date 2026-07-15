<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersAdminCommentsCommand;
use App\Form\Type\Users\UsersAdminCommentsType;
use App\Repository\UserLogRepository;
use App\Repository\UserRepository;
use App\Service\ConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersAdminCommentsEditController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/users/{id}/admin-comments/edit',
    name: 'users_admin_comments_edit',
    methods: ['GET', 'POST'],
    requirements: [
      'schema' => '%assert.schema%',
      'role_short' => '%assert.role_short.admin%',
      'id' => '%assert.id%',
    ],
    defaults: [
      'is_self' => false,
      'module' => 'users',
    ],
  )]

  #[Route(
    '/{schema}/{role_short}/users/self/admin-comments/edit',
    name: 'users_admin_comments_edit_self',
    methods: ['GET', 'POST'],
    requirements: [
      'schema' => '%assert.schema%',
      'role_short' => '%assert.role_short.admin%',
    ],
    defaults: [
      'is_self' => true,
      'id' => 0,
      'module' => 'users',
    ],
  )]

  public function __invoke(
    Request $request,
    int $id,
    bool $is_self,
    UserCacheService $user_cache_service,
    UserRepository $user_repository,
    UserLogRepository $user_log_repository,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
  ): Response {
    if (
      !$config_service->get_bool(
        config_id: 'users.fields.admin_comments.enabled',
        schema: $pp->schema_o(),
      )
    ) {
      throw $this->createAccessDeniedException(
        'Admin comments submodule not enabled.'
      );
    }

    if (
      !$is_self
      && $su->is_owner($id)
    ) {
      return $this->redirectToRoute(
        route: 'users_admin_comments_edit_self',
        parameters: $pp->ary(),
      );
    }

    if ($is_self) {
      $id = $su->id();
    }

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

    $command = new UsersAdminCommentsCommand();

    $command->admin_comments = $user['admin_comments'];
    $old_data = (array) $command;

    $form = $this->createForm(
      type: UsersAdminCommentsType::class,
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

      if ($command->admin_comments === $user['admin_comments']) {
        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'flash.no_change',
          ]
        );
      } else {
        $user_repository->set_admin_comments(
          id: $id,
          admin_comments: $command->admin_comments,
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
            'key' => 'users_admin_comments_edit.flash.success',
            'params' => [
              'self' => $is_self ? 'yes' : 'no',
              'user' => $user['name'],
            ],
          ]
        );
      }

      if ($is_self) {
        return $this->redirectToRoute(
          route: 'users_show_self',
          parameters: $pp->ary(),
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

    return $this->render('users/users_admin_comments_edit.html.twig', [
      'form' => $form->createView(),
      'is_self' => $is_self,
      'user' => $user,
      'id' => $id,
      'is_intersystem' => $is_intersystem,
    ]);
  }
}
