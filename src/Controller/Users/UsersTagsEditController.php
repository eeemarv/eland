<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersTagsCommand;
use App\Form\Type\Users\UsersTagsType;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Service\ConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersTagsEditController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/users/{id}/tags/edit',
    name: 'users_tags_edit',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
      'id'            => '%assert.id%',
    ],
    defaults: [
      'module'        => 'users',
      'is_self'       => false,
    ],
  )]

  #[Route(
    '/{schema}/{role_short}/users/self/tags/edit',
    name: 'users_tags_edit_self',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'users',
      'is_self'       => true,
      'id'            => 0,
    ],
  )]

  public function __invoke(
    Request $request,
    int $id,
    bool $is_self,
    UserRepository $user_repository,
    TagRepository $tag_repository,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'users.tags.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createAccessDeniedException(
        'Tags submodule not enabled.'
      );
    }

    if (!$is_self
      && $su->is_owner($id))
    {
      return $this->redirectToRoute(
        route: 'users_tags_edit_self',
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
      $command = $form->getData();
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
            'key' => 'users_tags_edit.flash.success',
            'params'  => [
              'is_self' => $is_self,
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

    return $this->render('users/users_tags_edit.html.twig', [
      'form'              => $form->createView(),
      'user'              => $user,
      'is_self'           => $is_self,
      'id'                => $id,
      'is_intersystem'    => $is_intersystem,
    ]);
  }
}
