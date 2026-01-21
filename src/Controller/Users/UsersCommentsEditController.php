<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersCommentsCommand;
use App\Form\Type\Users\UsersCommentsType;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersCommentsEditController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/users/{id}/comments/edit',
    name: 'users_comments_edit',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
      'id'            => '%assert.id%',
    ],
    defaults: [
      'is_self'       => false,
      'module'        => 'users',
    ],
  )]

  #[Route(
    '/{schema}/{role_short}/users/self/comments/edit',
    name: 'users_comments_edit_self',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.user%',
      'id'            => '%assert.id%',
    ],
    defaults: [
      'id'            => 0,
      'is_self'       => true,
      'module'        => 'users',
    ],
  )]

  public function __invoke(
    Request $request,
    int $id,
    bool $is_self,
    UserRepository $user_repository,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'users.fields.comments.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createAccessDeniedException(
        'Users comments submodule not enabled.'
      );
    }

    if (!$is_self
      && $su->is_owner($id))
    {
      return $this->redirectToRoute(
        route: 'users_comments_edit_self',
        parameters: $pp->ary(),
      );
    }

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
      $command = $form->getData();

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
              'self'  => $is_self ? 'yes' : 'no',
              'user'  => $user['name'],
            ]
          ]
        );
      }

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
          ... $pp->ary(),
          'id' => $id,
        ],
      );
    }

    return $this->render('users/users_comments_edit.html.twig', [
      'form'              => $form->createView(),
      'user'              => $user,
      'id'                => $id,
      'is_self'           => $is_self,
      'is_intersystem'    => $is_intersystem,
    ]);
  }
}
