<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersPasswordEditCommand;
use App\Form\Type\Users\UsersPasswordEditType;
use App\Repository\UserRepository;
use App\Security\User;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
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
    '/{system}/{role_short}/users/{id}/password-edit',
    name: 'users_password_edit',
    methods: ['GET', 'POST'],
    requirements: [
      'id'            => '%assert.id%',
      'system'        => '%assert.system%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'is_self'       => false,
      'module'        => 'users',
    ],
  )]

  #[Route(
    '/{system}/{role_short}/users/{id}/password-edit-self',
    name: 'users_password_edit_self',
    methods: ['GET', 'POST'],
    requirements: [
      'system'        => '%assert.system%',
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
      PageParamsService $pp,
      SessionUserService $su
  ):Response
  {
    if ($is_self)
    {
      $id = $su->id();
    }

    $form_options = [
      'validation_groups' => [$pp->role()],
    ];

    $command = new UsersPasswordEditCommand();
    $form = $this->createForm(UsersPasswordEditType::class,
      $command, $form_options);
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $command = $form->getData();
      $password_hasher = $password_hasher_factory->getPasswordHasher(new User());
      $hashed_password = $password_hasher->hash($command->password);
      $user_repository->set_password(
        id: $id,
        password: $hashed_password,
        schema: $pp->schema_o(),
      );

      $this->addFlash('success', 'Paswoord opgeslagen.');

      if ($is_self)
      {
        return $this->redirectToRoute('users_show_self', $pp->ary());
      }

      return $this->redirectToRoute('users_show', [
        ...$pp->ary(),
        'id' => $id,
      ]);
    }

    return $this->render('users/users_password_edit.html.twig', [
      'form'              => $form->createView(),
      'is_self'           => $is_self,
      'id'                => $id,
    ]);
  }
}
