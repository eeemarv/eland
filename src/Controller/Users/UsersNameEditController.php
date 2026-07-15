<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersNameCommand;
use App\Form\Type\Users\UsersNameType;
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
class UsersNameEditController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/users/{id}/name/edit',
    name: 'users_name_edit',
    methods: ['GET', 'POST'],
    requirements: [
      'schema' => '%assert.schema%',
      'role_short' => '%assert.role_short.admin%',
      'id' => '%assert.id%',
    ],
    defaults: [
      'is_self' => false,
      'mode' => 'edit',
      'module' => 'users',
    ],
  )]

  #[Route(
    '/{schema}/{role_short}/users/self/name/edit',
    name: 'users_name_edit_self',
    methods: ['GET', 'POST'],
    requirements: [
      'schema' => '%assert.schema%',
      'role_short' => '%assert.role_short.user%',
      'id' => '%assert.id%',
    ],
    defaults: [
      'id' => 0,
      'is_self' => true,
      'mode' => 'edit',
      'module' => 'users',
    ],
  )]

  public function __invoke(
    Request $request,
    int $id,
    bool $is_self,
    UserRepository $user_repository,
    UserLogRepository $user_log_repository,
    UserCacheService $user_cache_service,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
  ): Response {
    if (
      !$is_self
      && $su->is_owner($id)
    ) {
      return $this->redirectToRoute(
        route: 'users_account_edit_self',
        parameters: $pp->ary(),
      );
    }

    if ($is_self) {
      $id = $su->id();
    }

    if (
      !$pp->is_admin()
      && !$config_service->get_bool(
        config_id: 'users.fields.name.self_edit',
        schema: $pp->schema_o(),
      )
    ) {
      throw $this->createAccessDeniedException(
        'Changing own username not accepted by configuration.'
      );
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

    $form_options = [];
    $command = new UsersNameCommand();

    $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);

    $command->id = $id;
    $command->name = $user['name'];
    $old_data = (array) $command;

    if ($pp->is_admin()) {
      $form_options['log_comment_enabled'] = true;
    }

    $form = $this->createForm(
      type: UsersNameType::class,
      data: $command,
      options: $form_options,
    );

    $form->handleRequest($request);

    if (
      $form->isSubmitted()
      && $form->isValid()
    ) {
      $log_comment = $pp->is_admin() ? $form->get('log_comment')->getData() : null;

      if ($command->name === $user['name']) {
        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'flash.no_change',
          ]
        );
      } else {
        $user_repository->set_name(
          id: $id,
          name: $command->name,
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
            'key' => 'users_name_edit.flash.success',
            'params' => [
              'self' => $is_self ? 'yes' : 'no',
              'old_name' => $user['name'],
              'new_name' => $command->name,
            ]
          ],
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

    return $this->render('users/users_name_edit.html.twig', [
      'form' => $form->createView(),
      'user' => $user,
      'id' => $id,
      'is_self' => $is_self,
      'is_intersystem' => $is_intersystem,
    ]);
  }
}
