<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersFullNameCommand;
use App\Form\Type\Users\UsersFullNameType;
use App\Repository\UserLogRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersFullNameEditController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/users/{id}/full-name/edit',
    name: 'users_full_name_edit',
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
    '/{schema}/{role_short}/users/self/full-name/edit',
    name: 'users_full_name_edit_self',
    methods: ['GET', 'POST'],
    requirements: [
      'schema' => '%assert.schema%',
      'role_short' => '%assert.role_short.user%',
      'id' => '%assert.id%',
    ],
    defaults: [
      'id' => 0,
      'is_self' => true,
      'module' => 'users',
    ],
  )]

  public function __invoke(
    Request $request,
    int $id,
    bool $is_self,
    UserRepository $user_repository,
    UserCacheService $user_cache_service,
    UserLogRepository $user_log_repository,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
  ): Response {
    if (
      !$config_service->get_bool(
        config_id: 'users.fields.full_name.enabled',
        schema: $pp->schema_o(),
      )
    ) {
      throw $this->createAccessDeniedException(
        'Users full name submodule not enabled.'
      );
    }

    if (
      !$is_self
      && $su->is_owner($id)
    ) {
      return $this->redirectToRoute(
        route: 'users_full_name_edit_self',
        parameters: $pp->ary(),
      );
    }

    if ($is_self) {
      $id = $su->id();
    }

    $self_edit_en = $config_service->get_bool(
      config_id: 'users.fields.full_name.self_edit',
      schema: $pp->schema_o(),
    );

    $form_options = [];
    $full_name_edit_en = true;

    if ($is_self && !$pp->is_admin() && !$self_edit_en) {
      $full_name_edit_en = false;
      $form_options['full_name_edit_en'] = false;
    }

    if ($pp->is_admin()) {
      $form_options['log_comment_enabled'] = true;
    }

    $command = new UsersFullNameCommand();

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
    $command->full_name = $user['full_name'];
    $command->full_name_access = $user['full_name_access'];
    $old_data = (array) $command;

    $form = $this->createForm(
      type: UsersFullNameType::class,
      data: $command,
      options: $form_options,
    );

    $form->handleRequest($request);

    if (
      $form->isSubmitted()
      && $form->isValid()
    ) {
      $log_comment = $pp->is_admin() ? $form->get('log_comment')->getData() : null;
      $changed_full_name = false;
      $changed_access = false;
      $full_name = $user['full_name'];
      $full_name_access = $user['full_name_access'];

      if ($full_name_edit_en) {
        if ($command->full_name !== $full_name) {
          $full_name = $command->full_name;
          $changed_full_name = true;
        }
      }

      if ($command->full_name_access !== $full_name_access) {
        $full_name_access = $command->full_name_access;
        $changed_access = true;
      }

      if ($changed_full_name || $changed_access) {
        $user_repository->set_full_name(
          id: $id,
          full_name: $full_name,
          full_name_access: $full_name_access,
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

        if ($changed_full_name) {
          $this->addFlash(
            type: 'success',
            message: [
              'key' => 'users_full_name_edit.flash.success.full_name',
              'params' => [
                'self' => $is_self ? 'yes' : 'no',
                'user' => $user['name'],
                'old_full_name' => $user['full_name'],
                'new_full_name' => $full_name,
              ]
            ]
          );
        }

        if ($changed_access) {
          $this->addFlash(
            type: 'success',
            message: [
              'key' => 'users_full_name_edit.flash.success.access',
              'params' => [
                'self' => $is_self ? 'yes' : 'no',
                'user' => $user['name'],
                'old_access' => $user['full_name_access'],
                'new_access' => $full_name_access,
              ]
            ]
          );
        }
      } else {
        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'flash.no_change',
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

    return $this->render('users/users_full_name_edit.html.twig', [
      'form' => $form->createView(),
      'user' => $user,
      'id' => $id,
      'full_name_edit_en' => $full_name_edit_en,
      'is_self' => $is_self,
      'is_intersystem' => $is_intersystem,
    ]);
  }
}
