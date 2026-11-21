<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersTagsCommand;
use App\Form\Type\Users\UsersTagsType;
use App\Repository\TagRepository;
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
class UsersTagsAssignController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: UsersSubModule::Tags,
    is_self: false,
    path: '/users/{id}/tags/assign',
    name: 'users_tags_assign',
    methods: ['GET', 'POST'],
  )]

  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: UsersSubModule::Tags,
    is_self: true, 
    path: '/users/self/tags/assign',
    name: 'users_tags_assign_self',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    Request $request,
    bool $_is_self,
    UserRepository $user_repository,
    TagRepository $tag_repository,
    PageParamsService $pp,
    SessionUserService $su,
    int|null $id = null,    
  ):Response
  {

    if (!$_is_self
      && $su->is_owner($id))
    {
      return $this->redirectToRoute(
        route: 'users_tags_assign_self',
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

    $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);

    $tags = $tag_repository->get_id_ary_for_user(
      user_id: $id,
      schema: $pp->schema_o(),
      active_only: true,
    );

    $command = new UsersTagsCommand();

    $command->tags = $tags;

    $form = $this->createForm(
      type: UsersTagsType::class,
      data: $command,
      options: [
        'log_comment_enabled' => true,
      ],
    );
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $log_comment = $form->get('log_comment')->getData();

      $count_changes = $tag_repository->update_for_user(
        new_tag_id_ary: $command->tags,
        user_id: $id,
        comment: $log_comment,
        route: $pp->route(),
        created_by: $su->id() ?: null,
        schema: $pp->schema_o(),
      );

      if ($count_changes === 0)
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
        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'users_tags_assign.flash.success',
            'params'  => [
              'self' => $_is_self ? 'self' : 'other',
              'count_changes' => $count_changes,
              'user'  => $user['name'],
            ],
          ]
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

    return $this->render('users/users_tags_assign.html.twig', [
      'form'              => $form->createView(),
      'user'              => $user,
      'is_self'           => $_is_self,
      'id'                => $id,
      'is_intersystem'    => $is_intersystem,
    ]);
  }
}
