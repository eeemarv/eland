<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersAccountLeavingCommand;
use App\Form\Type\Users\UsersAccountLeavingType;
use App\Repository\AccountRepository;
use App\Repository\UserLogRepository;
use App\Repository\UserRepository;
use App\Service\ConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use App\Service\UserCacheService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersAccountLeavingEditController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/users/{id}/account-leaving/edit',
    name: 'users_account_leaving_edit',
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
    '/{schema}/{role_short}/users/self/account-leaving/edit',
    name: 'users_account_leaving_edit_self',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
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
    AccountRepository $account_repository,
    UserCacheService $user_cache_service,
    UserLogRepository $user_log_repository,
    TypeaheadService $typeahead_service,
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

    if (!$config_service->get_bool(
      config_id: 'users.leaving.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException(
        '"Leaving" functionality not enabled in the configuration.'
      );
    }

    if (!$is_self
      && $su->is_owner($id))
    {
      return $this->redirectToRoute(
        route: 'users_account_leaving_edit_self',
        parameters: $pp->ary()
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

    if (!isset($user['code']) || $user['code'] === '')
    {
      throw $this->createAccessDeniedException(
        'No account code set for this user, leaving status can not be edited.'
      );
    }

    $code = $user['code'];
    //$is_leaving = $user['is_leaving'];
    $is_leaving = $user['status'] === 2;

    $balance = $account_repository->get_balance(
      account_id: $id,
      schema: $pp->schema_o(),
    );

    $command = new UsersAccountLeavingCommand();

    $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);

    $command->is_leaving = $is_leaving;
    $old_data = (array) $command;

    $form = $this->createForm(
      type: UsersAccountLeavingType::class,
      data: $command,
      options: [
        'log_comment_enabled' => true,
      ]
    );

    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $log_comment = $form->get('log_comment')->getData();

      if ($command->is_leaving === $is_leaving)
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
        $user_repository->set_is_leaving(
          id: $id,
          is_leaving: $command->is_leaving,
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
            'key' => 'users_account_leaving_edit.flash.success',
            'params'  => [
              'code'  => $code,
              'leaving' => $command->is_leaving ? 'yes' : 'no',
            ]
          ]
        );
      }

      if ($is_self)
      {
        return $this->redirectToRoute('users_show_self', $pp->ary());
      }

      return $this->redirectToRoute('users_show', [
        ... $pp->ary(),
        'id' => $id,
      ]);
    }

    return $this->render('users/users_account_leaving_edit.html.twig', [
      'form'              => $form->createView(),
      'user'              => $user,
      'id'                => $id,
      'is_self'           => $is_self,
      'is_intersystem'    => $is_intersystem,
      'balance'           => $balance,
    ]);
  }
}
