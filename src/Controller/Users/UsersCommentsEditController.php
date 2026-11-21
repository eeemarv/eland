<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersCommentsCommand;
use App\Form\Type\Users\UsersCommentsType;
use App\Repository\UserRepository;
use App\Routing\AccessLevel;
use App\Routing\SchemaRoute;
use App\Routing\UsersSubModule;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class UsersCommentsEditController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: UsersSubModule::Comments,
    is_self: false,
    path: '/users/{id}/comments/edit',
    name: 'users_comments_edit',
    methods: ['GET', 'POST'],
  )]

  #[SchemaRoute(
    access_level: AccessLevel::User,
    module: UsersSubModule::Comments,
    is_self: true,
    path: '/users/self/comments/edit',
    name: 'users_comments_edit_self',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    Request $request,
    bool $_is_self,
    UserRepository $user_repository,
    PageParamsService $pp,
    SessionUserService $su,
    int|null $id = null,
  ):Response
  {

    if (!$_is_self
      && $su->is_owner($id))
    {
      return $this->redirectToRoute(
        route: 'users_comments_edit_self',
        parameters: $pp->ary(),
      );
    }

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

    $command = new UsersCommentsCommand();

    $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);
    $command->comments = $user['comments'];

    $form = $this->createForm(
      type: UsersCommentsType::class,
      data: $command,
    );

    $form->handleRequest($request);

    if ($form->isSubmitted()
        && $form->isValid())
    {
      if ($command->comments === $user['comments'])
      {
        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'flash.no_change',
          ]
        );
      }
      else
      {
         $user_repository->set_comments(
          id: $id,
          comments: $command->comments,
          schema: $pp->schema_o(),
        );

        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'users_comments_edit.flash.success',
            'params'  => [
              'self'  => $_is_self ? 'yes' : 'no',
              'user'  => $user['name'],
            ]
          ]
        );
      }

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
          ... $pp->ary(),
          'id' => $id,
        ],
      );
    }

    return $this->render('users/users_comments_edit.html.twig', [
      'form'              => $form->createView(),
      'user'              => $user,
      'id'                => $id,
      'is_self'           => $_is_self,
      'is_intersystem'    => $is_intersystem,
    ]);
  }
}
