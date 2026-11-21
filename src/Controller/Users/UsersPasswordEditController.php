<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersPasswordEditCommand;
use App\Form\Type\Users\UsersPasswordEditType;
use App\Repository\UserRepository;
use App\Routing\AccessLevel;
use App\Routing\AppModule;
use App\Routing\SchemaRoute;
use App\Security\User;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

#[AsController]
class UsersPasswordEditController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: AppModule::Users,
    is_self: false,
    path: '/users/{id}/password-edit',
    name: 'users_password_edit',
    methods: ['GET', 'POST'],
  )]

  #[SchemaRoute(
    access_level: AccessLevel::User,
    module: AppModule::Users,
    is_self: true,
    path: '/users/self/password-edit',
    name: 'users_password_edit_self',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    Request $request,
    PasswordHasherFactoryInterface $password_hasher_factory,
    bool $_is_self,
    UserRepository $user_repository,
    UserCacheService $user_cache_service,
    PageParamsService $pp,
    SessionUserService $su,
    int|null $id = null,
  ):Response
  {
    if ($_is_self)
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
            'self'  => $_is_self ? 'yes' : 'no',
            'user'  => $user['name'],
          ]
        ],
      );

      if ($_is_self)
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
      'is_self'           => $_is_self,
      'id'                => $id,
    ]);
  }
}
