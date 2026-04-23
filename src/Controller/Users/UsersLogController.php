<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersAccountLimitsCommand;
use App\Form\Type\Users\UsersAccountLimitsType;
use App\Repository\AccountRepository;
use App\Repository\UserLogRepository;
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
class UsersLogController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/users/{id}/log',
    name: 'users_log',
    methods: ['GET'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
      'id'            => '%assert.id%',
    ],
    defaults: [
      'module'        => 'users',
    ],
  )]

  public function __invoke(
    Request $request,
    int $id,
    bool $is_self,
    UserRepository $user_repository,
    UserLogRepository $user_log_repository,
    AccountRepository $account_repository,
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
      config_id: 'accounts.limits.enabled',
      schema: $pp->schema_o()))
    {
      throw $this->createNotFoundException(
        'Limits on transaction accounts are not enabled in the configuration.'
      );
    }

    if (!$is_self
      && $su->is_owner($id))
    {
      return $this->redirectToRoute(
        route: 'users_account_limits_edit_self',
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

    if (!isset($user['code']) || $user['code'] === '')
    {
      throw $this->createAccessDeniedException(
        'No account code set for this user, limits can not be edited.'
      );
    }

    $currency = $config_service->get_str(
      config_id: 'transactions.currency.name',
      schema: $pp->schema_o(),
    );

    $command = new UsersAccountLimitsCommand();

    $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);

    $min_limit = $account_repository->get_min_limit(
      account_id: $id,
      schema: $pp->schema_o(),
    );
    $max_limit = $account_repository->get_max_limit(
      account_id: $id,
      schema: $pp->schema_o(),
    );

    $command->min_limit = $min_limit;
    $command->max_limit = $max_limit;
    $old_data = (array) $command;

    $form = $this->createForm(
      type: UsersAccountLimitsType::class,
      data: $command,
      options: [
        'log_command_enabled' => true,
      ]
    );

    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $command = $form->getData();

      $changed = false;

      if ($command->min_limit !== $min_limit
      )
      {
        $account_repository->set_min_limit(
          account_id: $id,
          min_limit: $command->min_limit,
          created_by: $su->id(),
          schema: $pp->schema_o(),
        );

        $changed = true;
        $min_limit_action = 'edit';

        if ($command->min_limit === null)
        {
          $min_limit_action = 'del';
        }
        else if ($min_limit === null)
        {
          $min_limit_action = 'add';
        }

        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'users_account_limits_edit.flash.success.min',
            'params'  => [
              'action'  => $min_limit_action,
              'currency'  => $currency,
              'code'  => $user['code'],
              'old_min_limit' => $min_limit,
              'new_min_limit' => $command->min_limit,
            ],
          ]
        );
      }

      if ($command->max_limit !== $max_limit
      )
      {
        $account_repository->set_max_limit(
          account_id: $id,
          max_limit: $command->max_limit,
          created_by: $su->id(),
          schema: $pp->schema_o(),
        );

        $changed = true;
        $max_limit_action = 'edit';

        if ($command->max_limit === null)
        {
          $max_limit_action = 'del';
        }
        else if ($max_limit === null)
        {
          $max_limit_action = 'add';
        }

        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'users_account_limits_edit.flash.success.max',
            'params'  => [
              'action'  => $max_limit_action,
              'currency'  => $currency,
              'code'  => $user['code'],
              'old_max_limit' => $max_limit,
              'new_max_limit' => $command->max_limit,
            ],
          ]
        );
      }

      if ($changed)
      {
////
      }
      else
      {
        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'flash.no_change',
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

    return $this->render('users/users_account_limits_edit.html.twig', [
      'form'              => $form->createView(),
      'user'              => $user,
      'id'                => $id,
      'is_self'           => $is_self,
      'is_intersystem'    => $is_intersystem,
    ]);
  }
}
