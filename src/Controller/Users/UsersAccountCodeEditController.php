<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersAccountCodeCommand;
use App\Form\Type\Users\UsersAccountCodeType;
use App\Repository\UserLogRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use App\Service\UserCacheService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersAccountCodeEditController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/users/{id}/account-code/edit',
    name: 'users_account_code_edit',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
      'id'            => '%assert.id%',
    ],
    defaults: [
      'is_self'       => false,
      'mode'          => 'edit',
      'module'        => 'users',
    ],
  )]

  #[Route(
    '/{schema}/{role_short}/users/self/account-code/edit',
    name: 'users_account_code_edit_self',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
      'id'            => '%assert.id%',
    ],
    defaults: [
      'id'            => 0,
      'is_self'       => true,
      'mode'          => 'edit',
      'module'        => 'users',
    ],
  )]

  #[Route(
    '/{schema}/{role_short}/users/{id}/account-code/add',
    name: 'users_account_code_add',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
      'id'            => '%assert.id%',
    ],
    defaults: [
      'is_self'       => false,
      'mode'          => 'add',
      'module'        => 'users',
    ],
  )]

  #[Route(
    '/{schema}/{role_short}/users/self/account-code/add',
    name: 'users_account_code_add_self',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
      'id'            => '%assert.id%',
    ],
    defaults: [
      'id'            => 0,
      'is_self'       => true,
      'mode'          => 'add',
      'module'        => 'users',
    ],
  )]

  public function __invoke(
    Request $request,
    int $id,
    bool $is_self,
    string $mode,
    UserCacheService $user_cache_service,
    TypeaheadService $typeahead_service,
    UserRepository $user_repository,
    UserLogRepository $user_log_repository,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'transactions.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException(
        'Users account edit not possible: transactions module not enabled.'
      );
    }

    if (!$is_self
      && $su->is_owner($id))
    {
      return $this->redirectToRoute(
        route: 'users_account_edit_self',
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

    $code_set_previously = false;

    if (isset($user['code']) && $user['code'] !== '')
    {
      $code_set_previously = true;
    }

    if ($code_set_previously)
    {
      if ($mode === 'add')
      {
        throw $this->createAccessDeniedException(
          'Wrong route: account already exists (use edit route instead)'
        );
      }
    }
    else
    {
      if ($mode === 'edit')
      {
        throw $this->createAccessDeniedException(
          'Wrong route: can not edit non-existing account (use add route instead)'
        );
      }
    }

    $form_options = [
      'log_comment_enabled' => true,
    ];

    $command = new UsersAccountCodeCommand();

    $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);

    $command->user_id = $id;
    $command->code = $user['code'];
    $old_data = (array) $command;

    if ($code_set_previously)
    {
      $form_options['render_omit'] = $command->code;
    }

    $form = $this->createForm(
      type: UsersAccountCodeType::class,
      data: $command,
      options: $form_options,
    );

    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $command = $form->getData();
      $log_comment = $form->get('log_comment')->getData();

      if ($command->code === $user['code'])
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
        $user_repository->set_code(
          id: $id,
          code: $command->code,
          schema: $pp->schema_o(),
        );

        $user_cache_service->clear(
          id: $id,
          schema: $pp->schema(),
        );
        $typeahead_service->clear_cache(
          schema: $pp->schema(),
        );

        $user_log_repository->insert(
          user_id: $id,
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
            'key' => 'users_account_code_edit.flash.success',
            'params' => [
              'old_code'  => $user['code'],
              'new_code'  => $command->code,
              'code'  => $command->code,
              'mode'  => $mode,
            ]
          ],
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

    return $this->render('users/users_account_code_edit.html.twig', [
      'form'              => $form->createView(),
      'user'              => $user,
      'id'                => $id,
      'is_self'           => $is_self,
      'mode'              => $mode,
      'is_intersystem'    => $is_intersystem,
    ]);
  }
}
