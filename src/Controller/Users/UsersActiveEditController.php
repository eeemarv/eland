<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersActivateCommand;
use App\Command\Users\UsersActiveCommand;
use App\Form\Type\Users\UsersActivateType;
use App\Form\Type\Users\UsersActiveType;
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
class UsersActiveEditController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/users/{id}/active/edit',
    name: 'users_active_edit',
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
    UserCacheService $user_cache_service,
    UserRepository $user_repository,
    UserLogRepository $user_log_repository,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
  ): Response {
    $user = $user_repository->get(
      id: $id,
      schema: $pp->schema_o(),
    );

    if ($user === false) {
      throw $this->createNotFoundException(
        'User with id ' . $id . ' not found'
      );
    }

    $form_options = [
      'log_comment_enabled' => true,
    ];
    $command = new UsersActiveCommand();
    $command->is_active = $user['is_active'];

    $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);

    $form = $this->createForm(
      type: UsersActiveType::class,
      data: $command,
      options: $form_options,
    );
    $form->handleRequest($request);

    if (
      $form->isSubmitted()
      && $form->isValid()
    ) {
      $is_active = $command->is_active;
      $send_email = $command->send_email;
      $send_email_cc = $command->send_email_cc;
      $log_comment = $form->get('log_comment')->getData();

      if ($is_active === $user['is_active']) {
        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'flash.no_change',
          ]
        );
      } else {
        $user_repository->set_is_active(
          id: $id,
          is_active: $is_active,
          schema: $pp->schema_o(),
        );
        $user_cache_service->clear(
          id: $id,
          schema: $pp->schema(),
        );

        if ($send_email) {
          $email_addresses = $user_repository->get_email_addresses(
            user_id: $id,
            schema: $pp->schema_o(),
            active_only: false,
          );

          if ($email_addresses->count()) {




          }
        }






        $user_log_repository->insert(
          user_id: $id,
          old_data: [
            'is_active' => $user['is_active'],
          ],
          new_data: (array) $command,
          comment: $log_comment,
          created_by: $su->id() ?: null,
          route: $pp->route(),
          schema: $pp->schema_o(),
          meta_data: [
            'send_email' => $send_email,
            'send_email_cc' => $send_email_cc,
          ],
        );
      }

      $this->addFlash(
        type: 'success',
        message: [
          'key' => 'users_active_edit.flash.success',
          'params' => [
            'user' => $user['name'],
            'is_active' => $is_active ? 'yes' : 'no',
          ],
        ]
      );

      $this->addFlash(
        type: 'success',
        message: [
          'key' => 'flash.email.notification',
          'params' => [
            'user' => $user['name'],
            'sent' => $send_email ? 'yes' : 'no',
          ],
        ]
      );

      return $this->redirectToRoute(
        route: 'users_show',
        parameters: [
          ...$pp->ary(),
          'id' => $id,
        ],
      );
    }

    return $this->render('users/users_active_edit.html.twig', [
      'form' => $form->createView(),
      'user' => $user,
      'id' => $id,
      'is_intersystem' => $is_intersystem,
      'is_self' => false,
    ]);
  }
}
