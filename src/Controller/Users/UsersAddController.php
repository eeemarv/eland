<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersAddCommand;
use App\Form\Type\Users\UsersAddType;
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
class UsersAddController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/users/add',
    name: 'users_add',
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
    bool $is_self,
    string $mode,
    UserCacheService $user_cache_service,
    UserRepository $user_repository,
    UserLogRepository $user_log_repository,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
  ): Response {
    $command = new UsersAddCommand();
    $old_data = (array) $command;
    $form_options = [
      'log_comment_enabled' => true,
    ];

    $form = $this->createForm(
      type: UsersAddType::class,
      data: $command,
      options: $form_options,
    );

    $form->handleRequest($request);

    if (
      $form->isSubmitted()
      && $form->isValid()
    ) {
      $command = $form->getData();
      $log_comment = $form->get('log_comment')->getData();

      $user_id = $user_repository->add(
        name: $command->name,
        email: $command->email,
        created_by: $su->id() ?: null,
        schema: $pp->schema_o(),
      );

      $user_log_repository->insert(
        user_id: $user_id,
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
          'key' => 'users_add.flash.success',
          'params' => [
            'user' => $command->name,
            'email' => $command->email,
          ]
        ],
      );

      return $this->redirectToRoute(
        route: 'users_show',
        parameters: [
          ...$pp->ary(),
          'id' => $user_id,
        ],
      );
    }

    return $this->render('users/users_add.html.twig', [
      'form' => $form->createView(),
    ]);
  }
}
