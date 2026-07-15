<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersHobbiesCommand;
use App\Form\Type\Users\UsersHobbiesType;
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
class UsersHobbiesEditController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/users/{id}/hobbies/edit',
    name: 'users_hobbies_edit',
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
    '/{schema}/{role_short}/users/self/hobbies/edit',
    name: 'users_hobbies_edit_self',
    methods: ['GET', 'POST'],
    requirements: [
      'schema' => '%assert.schema%',
      'role_short' => '%assert.role_short.user%',
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
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
  ): Response {
    if (
      !$config_service->get_bool(
        config_id: 'users.fields.hobbies.enabled',
        schema: $pp->schema_o(),
      )
    ) {
      throw $this->createAccessDeniedException(
        'Users hobbies submodule not enabled.'
      );
    }

    if (
      !$is_self
      && $su->is_owner($id)
    ) {
      return $this->redirectToRoute(
        route: 'users_hobbies_edit_self',
        parameters: $pp->ary(),
      );
    }

    if ($is_self) {
      $id = $su->id();
    }

    $command = new UsersHobbiesCommand();

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
    $command->hobbies = $user['hobbies'];

    $form = $this->createForm(
      type: UsersHobbiesType::class,
      data: $command,
    );

    $form->handleRequest($request);

    if (
      $form->isSubmitted()
      && $form->isValid()
    ) {
      if ($command->hobbies === $user['hobbies']) {
        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'flash.no_change',
          ]
        );
      } else {
        $user_repository->set_hobbies(
          id: $id,
          hobbies: $command->hobbies,
          schema: $pp->schema_o(),
        );

        $user_cache_service->clear(
          id: $id,
          schema: $pp->schema(),
        );

        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'users_hobbies_edit.flash.success',
            'params' => [
              'self' => $is_self ? 'yes' : 'no',
              'user' => $user['name'],
            ]
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

    return $this->render('users/users_hobbies_edit.html.twig', [
      'form' => $form->createView(),
      'user' => $user,
      'id' => $id,
      'is_self' => $is_self,
      'is_intersystem' => $is_intersystem,
    ]);
  }
}
