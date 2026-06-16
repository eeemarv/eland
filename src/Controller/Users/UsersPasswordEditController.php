<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersPasswordEditCommand;
use App\Form\Type\Users\UsersPasswordEditType;
use App\Repository\UserRepository;
use App\Security\User;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersPasswordEditController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/users/{id}/password-edit',
    name: 'users_password_edit',
    methods: ['GET', 'POST'],
    requirements: [
      'id'            => '%assert.id%',
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'is_self'       => false,
      'module'        => 'users',
    ],
  )]

  #[Route(
    '/{schema}/{role_short}/users/self/password-edit',
    name: 'users_password_edit_self',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.user%',
    ],
    defaults: [
      'id'            => 0,
      'is_self'       => true,
      'module'        => 'users',
    ],
  )]

  public function __invoke(
    Request $request,
    PasswordHasherFactoryInterface $password_hasher_factory,
    int $id,
    bool $is_self,
    UserRepository $user_repository,
    UserCacheService $user_cache_service,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {
    if ($is_self)
    {
      $id = $su->id();
    }

    $user = $user_repository->get(
      id: $id,
      schema: $pp->schema_o(),
    );

    if ($user === false)
    {
      throw $this->createNotFoundException(
        'User with id ' . $id . ' not found'
      );
    }

    $form_options = [
      'validation_groups' => [$pp->role()],
    ];

    $command = new UsersPasswordEditCommand();
    $form = $this->createForm(
      type: UsersPasswordEditType::class,
      data: $command,
      options: $form_options,
    );
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $password_hasher = $password_hasher_factory->getPasswordHasher(new User());
      $hashed_password = $password_hasher->hash($command->password);
      $user_repository->set_password(
        id: $id,
        password: $hashed_password,
        schema: $pp->schema_o(),
      );

		  $user_cache_service->clear(
        id: $id,
        schema: $pp->schema(),
      );

      $this->addFlash(
        type: 'success',
        message: [
          'key' => 'users_password_edit.flash.success',
          'params'  => [
            'self'  => $is_self ? 'yes' : 'no',
            'user'  => $user['name'],
          ]
        ],
      );

      if ($is_self)
      {
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

    return $this->render('users/users_password_edit.html.twig', [
      'user'              => $user,
      'form'              => $form->createView(),
      'is_self'           => $is_self,
      'id'                => $id,
    ]);
  }
}
