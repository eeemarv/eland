<?php declare(strict_types=1);

namespace App\Controller\Users;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Command\UsersBulk\UsersBulkAdminCommentsCommand;
use App\Command\UsersBulk\UsersBulkCommentsCommand;
use App\Command\UsersBulk\UsersBulkEmailCommand;
use App\Command\UsersBulk\UsersBulkFullNameAccessCommand;
use App\Command\UsersBulk\UsersBulkMaxLimitCommand;
use App\Command\UsersBulk\UsersBulkMinLimitCommand;
use App\Command\UsersBulk\UsersBulkPeriodicOverviewEnCommand;
use App\Command\UsersBulk\UsersBulkRoleCommand;
use App\Command\UsersBulk\UsersBulkStatusCommand;
use App\Command\Users\UsersColsCommand;
use App\Command\UsersBulk\UsersBulkAccountLeavingCommand;
use App\Command\UsersBulk\UsersBulkActiveCommand;
use App\Email\UserBulk\Copy\EmailUserBulkCopyMessage;
use App\Email\UserBulk\Message\EmailUserBulkMessageMessage;
use App\Form\Type\Filter\QTextSearchFilterType;
use App\Form\Type\UsersBulk\UsersBulkAdminCommentsType;
use App\Form\Type\UsersBulk\UsersBulkCommentsType;
use App\Form\Type\UsersBulk\UsersBulkEmailType;
use App\Form\Type\UsersBulk\UsersBulkFullNameAccessType;
use App\Form\Type\UsersBulk\UsersBulkMaxLimitType;
use App\Form\Type\UsersBulk\UsersBulkMinLimitType;
use App\Form\Type\UsersBulk\UsersBulkPeriodicOverviewEnType;
use App\Form\Type\UsersBulk\UsersBulkRoleType;
use App\Form\Type\UsersBulk\UsersBulkStatusType;
use App\Form\Type\Users\UsersColsType;
use App\Form\Type\UsersBulk\UsersBulkAccountLeavingType;
use App\Form\Type\UsersBulk\UsersBulkActiveType;
use App\Render\AccountRender;
use App\Repository\AccountRepository;
use App\Repository\ContactRepository;
use App\Repository\LoginRepository;
use App\Repository\MessageRepository;
use App\Repository\MollieRepository;
use App\Repository\TransactionRepository;
use App\Repository\UserLogRepository;
use App\Repository\UserRepository;
use App\Service\CacheService;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersListController extends AbstractController
{
  const USER_AKEYS = [
    'id'  => true,
    'name'  => true,
    'code'  => true,
  ];

  #[Route(
    '/{schema}/{role_short}/users/{status}',
    name: 'users_list',
    methods: ['GET', 'POST'],
    priority: 20,
    requirements: [
      'status'        => '%assert.account_status%',
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.guest%',
    ],
    defaults: [
      'status'        => 'active',
      'module'        => 'users',
    ],
  )]

  public function __invoke(
    Request $request,
    string $status,
    AccountRepository $account_repository,
    UserRepository $user_repository,
    UserLogRepository $user_log_repository,
    ContactRepository $contact_repository,
    MollieRepository $mollie_repository,
    MessageRepository $message_repository,
    TransactionRepository $transaction_repository,
    LoginRepository $login_repository,
    AccountRender $account_render,
    CacheService $cache_service,
    ConfigService $config_service,
    ItemAccessService $item_access_service,
    UserCacheService $user_cache_service,
    MessageBusInterface $bus,
    SessionInterface $session,
    PageParamsService $pp,
    SessionUserService $su,
    VarRouteService $vr,
  ):Response
  {
    if (!$pp->is_admin() && !in_array($status, ['active', 'new', 'leaving']))
    {
      throw $this->createAccessDeniedException('No access for status: ' . $status);
    }

    if (!$request->isMethod('GET') && !$pp->is_admin())
    {
      throw new BadRequestHttpException('POST not allowed');
    }

    $full_name_enabled = $config_service->get_bool(
      config_id: 'users.fields.full_name.enabled',
      schema: $pp->schema_o(),
    );
    $postcode_enabled = $config_service->get_bool(
      config_id: 'users.fields.postcode.enabled',
      schema: $pp->schema_o(),
    );
    $birthdate_enabled = $config_service->get_bool(
      config_id: 'users.fields.birthdate.enabled',
      schema: $pp->schema_o(),
    );
    $hobbies_enabled = $config_service->get_bool(
      config_id: 'users.fields.hobbies.enabled',
      schema: $pp->schema_o(),
    );
    $comments_enabled = $config_service->get_bool(
      config_id: 'users.fields.comments.enabled',
      schema: $pp->schema_o(),
    );
    $admin_comments_enabled = $config_service->get_bool(
      config_id: 'users.fields.admin_comments.enabled',
      schema: $pp->schema_o(),
    );
    $periodic_mail_enabled = $config_service->get_bool(
      config_id: 'periodic_mail.enabled',
      schema: $pp->schema_o(),
    );

    $mollie_enabled = $config_service->get_bool(
      config_id: 'mollie.enabled',
      schema: $pp->schema_o(),
    );
    $messages_enabled = $config_service->get_bool(
      config_id: 'messages.enabled',
      schema: $pp->schema_o(),
    );
    $transactions_enabled = $config_service->get_bool(
      config_id: 'transactions.enabled',
      schema: $pp->schema_o(),
    );
    $limits_enabled = $config_service->get_bool(
      config_id: 'accounts.limits.enabled',
      schema: $pp->schema_o(),
    );

    $filter_form = $this->createForm(QTextSearchFilterType::class);
    $filter_form->handleRequest($request);

    /**
     * Begin bulk POST
     */

    $bulk_email_form = null;
    $bulk_full_name_access_form = null;
    $bulk_role_form = null;
    $bulk_status_form = null;
    $bulk_comments_form = null;
    $bulk_admin_comments_form = null;
    $bulk_min_limit_form = null;
    $bulk_max_limit_form = null;
    $bulk_periodic_overview_en_form = null;
    $bulk_active_form = null;
    $bulk_leaving_form = null;

    if ($pp->is_admin())
    {
      $bulk_email_command = new UsersBulkEmailCommand();
      $bulk_email_form = $this->createForm(
        type: UsersBulkEmailType::class,
        data: $bulk_email_command,
      );
      $bulk_email_form->handleRequest($request);
    }

    if (isset($bulk_email_form)
      && $bulk_email_form->isSubmitted()
      && $bulk_email_form->isValid()
      && $config_service->get_bool(
        config_id: 'mail.enabled',
        schema: $pp->schema_o(),
      )
      && !$su->is_master()
    )
    {
      $bulk_email_command = $bulk_email_form->getData();
      $selected = $bulk_email_command->selected;
      $subject = $bulk_email_command->subject;
      $content = $bulk_email_command->content;
      $copy = $bulk_email_command->copy;
      $select_ary = explode(',', $selected);
      $s_user_ids = [];
      foreach ($select_ary as $sel_id)
      {
        $s_user_ids[] = (int) trim($sel_id);
      }
      $user_ids_sent = [];
      $user_ids_not_sent = [];

      $m_users = $user_repository->get_users_with_email_addresses(
        user_ids: $s_user_ids,
        schema: $pp->schema_o(),
      );

      $flash_sent_ary = [];
      $flash_not_sent_ary = [];

      foreach ($m_users as $u)
      {
        $u_sect = array_intersect_key($u, self::USER_AKEYS);
        if (count($u['email_addresses']))
        {
          $user_ids_sent[] = $u['id'];
          $flash_sent_ary[] = $u_sect;
        }
        else
        {
          $user_ids_not_sent[] = $u['id'];
          $flash_not_sent_ary[] = $u_sect;
        }
      }

      if (count($user_ids_sent))
      {
        $m_message = new EmailUserBulkMessageMessage(
          sender_id: $su->id(),
          user_ids: $user_ids_sent,
          content: $content,
          subject: $subject,
          schema: $pp->schema_o(),
        );
        $bus->dispatch($m_message);

        if ($copy)
        {
          $m_copy = new EmailUserBulkCopyMessage(
            sender_id: $su->id(),
            user_ids_sent: $user_ids_sent,
            user_ids_not_sent: $user_ids_not_sent,
            content: $content,
            subject: $subject,
            schema: $pp->schema_o(),
          );
          $bus->dispatch($m_copy);
        }
      }
      $this->addFlash(
        type: 'success',
        message: [
          'key'   => 'flash.email.sent_to',
          'params'  => [
            'count' => count($flash_sent_ary),
          ],
      ]);
      foreach($flash_sent_ary as $u_sect)
      {
        $this->addFlash(
          type: 'success',
          message: [
            'user'  => $u_sect,
          ],
        );
      }
      if (count($flash_not_sent_ary))
      {
        $this->addFlash(
          type: 'warning',
          message: [
            'key'   => 'flash.email.not_sent_to',
            'params'  => [
              'count' => count($flash_not_sent_ary),
          ],
        ]);
      }
      foreach($flash_not_sent_ary as $u_sect)
      {
        $this->addFlash(
          type: 'warning',
          message: [
            'user'  => $u_sect,
          ],
        );
      }
      return $this->redirectToRoute(
        route: $vr->get('users'),
        parameters: $pp->ary(),
      );
    }

    /**
     * Bulk actions
     */

    if ($full_name_enabled
      && $pp->is_admin()
    )
    {
      $bulk_full_name_access_command = new UsersBulkFullNameAccessCommand();
      $bulk_full_name_access_form = $this->createForm(
        type: UsersBulkFullNameAccessType::class,
        data: $bulk_full_name_access_command,
      );
      $bulk_full_name_access_form->handleRequest($request);
    }

    if (isset($bulk_full_name_access_form)
      && $bulk_full_name_access_form->isSubmitted()
      && $bulk_full_name_access_form->isValid()
    )
    {
      $bulk_full_name_access_command = $bulk_full_name_access_form->getData();
      $selected = $bulk_full_name_access_command->selected;
      $access = $bulk_full_name_access_command->access;
      $select_ary = explode(',', $selected);
      $s_user_ids = [];

      foreach ($select_ary as $sel_id)
      {
        $s_user_ids[] = (int) trim($sel_id);
      }

      $s_user_ary = $user_repository->get_selected(
        user_ids: $s_user_ids,
        schema: $pp->schema_o(),
      );

      $change_ary = [];
      $no_change_ary = [];

      foreach($s_user_ary as $uid => $user)
      {
        if ($user['full_name_access'] === $access)
        {
          $no_change_ary[] = $uid;
        }
        else
        {
          $change_ary[] = $uid;
        }
      }

      if (count($change_ary))
      {
        $user_repository->set_bulk_full_name_access(
          full_name_access: $access,
          user_ids: $change_ary,
          schema: $pp->schema_o(),
        );
        foreach ($change_ary as $uid)
        {
          $user_cache_service->clear(
            id: $uid,
            schema: $pp->schema(),
          );
        }

        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'users_list.bulk.full_name_access.flash.success',
            'params'  => [
              'count' => count($change_ary),
            ],
          ],
        );
        foreach ($change_ary as $uid)
        {
          $this->addFlash(
            type: 'success',
            message: [
              'user'  => array_intersect_key(
                $s_user_ary[$uid],
                self::USER_AKEYS,
              ),
            ],
          );
        }
      }

      if (count($no_change_ary))
      {
        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'flash.no_change_for',
            'params'  => [
              'count' => count($no_change_ary),
            ],
          ],
        );
        foreach ($no_change_ary as $uid)
        {
          $this->addFlash(
            type: 'warning',
            message: [
              'user'  => array_intersect_key(
                $s_user_ary[$uid],
                self::USER_AKEYS
              ),
            ],
          );
        }
      }

      return $this->redirectToRoute(
        route: $vr->get('users'),
        parameters: $pp->ary(),
      );
    }

    if ($comments_enabled
      && $pp->is_admin())
    {
      $bulk_comments_command = new UsersBulkCommentsCommand();
      $bulk_comments_form = $this->createForm(
        type: UsersBulkCommentsType::class,
        data: $bulk_comments_command,
      );
      $bulk_comments_form->handleRequest($request);
    }

    if ($bulk_comments_form
      && $bulk_comments_form->isSubmitted()
      && $bulk_comments_form->isValid())
    {
      $bulk_comments_command = $bulk_comments_form->getData();
      $selected = $bulk_comments_command->selected;
      $comments = $bulk_comments_command->comments;
      $select_ary = explode(',', $selected);
      $s_user_ids = [];
      foreach ($select_ary as $sel_id)
      {
        $s_user_ids[] = (int) trim($sel_id);
      }
      $s_user_ary = $user_repository->get_selected(
        user_ids: $s_user_ids,
        schema: $pp->schema_o(),
      );

      $change_ary = [];
      $no_change_ary = [];

      foreach($s_user_ary as $uid => $user)
      {
        if ($user['comments'] === $comments)
        {
          $no_change_ary[] = $uid;
        }
        else
        {
          $change_ary[] = $uid;
        }
      }

      $user_repository->set_bulk_comments(
        comments: $comments,
        user_ids: $s_user_ids,
        schema: $pp->schema_o(),
      );
      foreach ($s_user_ids as $uid)
      {
        $user_cache_service->clear(
          id: $uid,
          schema: $pp->schema(),
        );
      }
      $this->addFlash(
        type: 'success',
        message: [
          'key' => 'users_list.bulk.comments.flash.success',
          'params'  => [
            'count' => count($s_user_ids),
          ],
        ],
      );
      foreach ($s_user_ids as $uid)
      {
        $this->addFlash(
          type: 'success',
          message: $account_render->link($uid, $pp->ary()),
        );
      }
      return $this->redirectToRoute(
        route: $vr->get('users'),
        parameters: $pp->ary(),
      );
    }

    if ($admin_comments_enabled
      && $pp->is_admin())
    {
      $bulk_admin_comments_command = new UsersBulkAdminCommentsCommand();
      $bulk_admin_comments_form = $this->createForm(
        type: UsersBulkAdminCommentsType::class,
        data: $bulk_admin_comments_command,
      );
      $bulk_admin_comments_form->handleRequest($request);
    }

    if ($bulk_admin_comments_form
      && $bulk_admin_comments_form->isSubmitted()
      && $bulk_admin_comments_form->isValid())
    {
      $bulk_admin_comments_command = $bulk_admin_comments_form->getData();
      $selected = $bulk_admin_comments_command->selected;
      $admin_comments = $bulk_admin_comments_command->admin_comments;
      $select_ary = explode(',', $selected);
      $s_user_ids = [];

      foreach ($select_ary as $sel_id)
      {
        $s_user_ids[] = (int) trim($sel_id);
      }

      $s_user_ary = $user_repository->get_selected(
        user_ids: $s_user_ids,
        schema: $pp->schema_o(),
      );

      $change_ary = [];
      $no_change_ary = [];

      foreach($s_user_ary as $uid => $user)
      {
        if ($user['admin_comments'] === $admin_comments)
        {
          $no_change_ary[] = $uid;
        }
        else
        {
          $change_ary[] = $uid;
        }
      }

      $user_repository->set_bulk_admin_comments(
        admin_comments: $admin_comments,
        user_ids: $s_user_ids,
        schema: $pp->schema_o(),
      );
      foreach ($s_user_ids as $uid)
      {
        $user_cache_service->clear(
          id: $uid,
          schema: $pp->schema(),
        );
      }
      $this->addFlash(
        type: 'success',
        message: [
          'key' => 'users_list.bulk.admin_comments.flash.success',
          'params'  => [
            'count' => count($s_user_ids),
          ],
        ],
      );
      foreach ($s_user_ids as $uid)
      {
        $this->addFlash(
          type: 'success',
          message: $account_render->link($uid, $pp->ary()),
        );
      }
      return $this->redirectToRoute(
        route: $vr->get('users'),
        parameters: $pp->ary(),
      );
    }

    if ($pp->is_admin())
    {
      $bulk_status_command = new UsersBulkStatusCommand();
      $bulk_status_form = $this->createForm(
        type: UsersBulkStatusType::class,
        data: $bulk_status_command,
      );
      $bulk_status_form->handleRequest($request);
    }

    if ($bulk_status_form
      && $bulk_status_form->isSubmitted()
      && $bulk_status_form->isValid())
    {
      $bulk_status_command = $bulk_status_form->getData();
      $selected = $bulk_status_command->selected;
      $status = $bulk_status_command->status;
      $select_ary = explode(',', $selected);
      $s_user_ids = [];
      foreach ($select_ary as $sel_id)
      {
        $s_user_ids[] = (int) trim($sel_id);
      }

      $s_user_ary = $user_repository->get_selected(
        user_ids: $s_user_ids,
        schema: $pp->schema_o(),
      );

      $change_ary = [];
      $no_change_ary = [];

      foreach($s_user_ary as $uid => $user)
      {
        if ($user['status'] === $status)
        {
          $no_change_ary[] = $uid;
        }
        else
        {
          $change_ary[] = $uid;
        }
      }


      $user_repository->set_bulk_status(
        status: $status,
        user_ids: $s_user_ids,
        schema: $pp->schema_o(),
      );
      foreach ($s_user_ids as $uid)
      {
        $user_cache_service->clear(
          id: $uid,
          schema: $pp->schema(),
        );
      }
      $this->addFlash(
        type: 'success',
        message: [
          'key' => 'users_list.bulk.status.flash.success',
          'params'  => [
            'count' => count($s_user_ids),
          ],
        ],
      );
      foreach ($s_user_ids as $uid)
      {
        $this->addFlash(
          type: 'success',
          message: $account_render->link($uid, $pp->ary(),
        ));
      }
      return $this->redirectToRoute(
        route: $vr->get('users'),
        parameters: $pp->ary(),
      );
    }

    /*

    if ($pp->is_admin())
    {
      $bulk_role_command = new UsersBulkRoleCommand();
      $bulk_role_form = $this->createForm(
        type: UsersBulkRoleType::class,
        data: $bulk_role_command,
        options: [
          'log_comment_enabled' => true,
        ],
      );
      $bulk_role_form->handleRequest($request);
    }

    if ($bulk_role_form
      && $bulk_role_form->isSubmitted()
      && $bulk_role_form->isValid())
    {
      $bulk_role_command = $bulk_role_form->getData();
      $selected = $bulk_role_command->selected;
      $role = $bulk_role_command->role;
      $log_comment = $bulk_role_command->get('log_comment')->getData();
      $select_ary = explode(',', $selected);
      $s_user_ids = [];

      foreach ($select_ary as $sel_id)
      {
        $s_user_ids[] = (int) trim($sel_id);
      }

      $s_user_ary = $user_repository->get_selected(
        user_ids: $s_user_ids,
        schema: $pp->schema_o(),
      );

      $change_ary = [];
      $no_change_ary = [];

      foreach($s_user_ary as $uid => $user)
      {
        if ($user['role'] === $role)
        {
          $no_change_ary[] = $uid;
        }
        else
        {
          $change_ary[] = $uid;
        }
      }

      $user_repository->set_bulk_role(
        role: $role,
        user_ids: $s_user_ids,
        schema: $pp->schema_o(),
      );
      foreach ($s_user_ids as $uid)
      {
        $user_cache_service->clear(
          id: $uid,
          schema: $pp->schema(),
        );
      }
      $this->addFlash(
        type: 'success',
        message: [
          'key' => 'users_list.bulk.role.flash.success',
          'params'  => [
            'count' => count($s_user_ids),
          ],
        ],
      );
      foreach ($s_user_ids as $uid)
      {
        $this->addFlash(
          type: 'success',
          message: $account_render->link($uid, $pp->ary()),
        );
      }
      return $this->redirectToRoute(
        route: $vr->get('users'),
        parameters: $pp->ary(),
      );
    }
    */

    if ($pp->is_admin())
    {
      $bulk_periodic_overview_en_command = new UsersBulkPeriodicOverviewEnCommand();
      $bulk_periodic_overview_en_form = $this->createForm(
        type: UsersBulkPeriodicOverviewEnType::class,
        data: $bulk_periodic_overview_en_command,
      );
      $bulk_periodic_overview_en_form->handleRequest($request);
    }

    if ($bulk_periodic_overview_en_form
      && $bulk_periodic_overview_en_form->isSubmitted()
      && $bulk_periodic_overview_en_form->isValid())
    {
      $bulk_periodic_overview_en_command = $bulk_periodic_overview_en_form->getData();
      $selected = $bulk_periodic_overview_en_command->selected;
      $periodic_overview_en = $bulk_periodic_overview_en_command->periodic_overview_en;
      $select_ary = explode(',', $selected);
      $s_user_ids = [];
      foreach ($select_ary as $sel_id)
      {
        $s_user_ids[] = (int) trim($sel_id);
      }

      $s_user_ary = $user_repository->get_selected(
        user_ids: $s_user_ids,
        schema: $pp->schema_o(),
      );

      $change_ary = [];
      $no_change_ary = [];

      foreach($s_user_ary as $uid => $user)
      {
        if ($user['periodic_overview_en'] === $periodic_overview_en)
        {
          $no_change_ary[] = $uid;
        }
        else
        {
          $change_ary[] = $uid;
        }
      }

      $user_repository->set_bulk_periodic_overview_en(
        periodic_overview_en: $periodic_overview_en,
        user_ids: $s_user_ids,
        schema: $pp->schema_o(),
      );
      foreach ($s_user_ids as $uid)
      {
        $user_cache_service->clear(
          id: $uid,
          schema: $pp->schema(),
        );
      }
      $this->addFlash(
        type: 'success',
        message: [
          'key' => 'users_list.bulk.periodic_overview_en.flash.success',
          'params'  => [
            'count' => count($s_user_ids),
          ],
        ],
      );
      foreach ($s_user_ids as $uid)
      {
        $this->addFlash(
          type: 'success',
          message: $account_render->link($uid, $pp->ary()),
        );
      }
      return $this->redirectToRoute(
        route: $vr->get('users'),
        parameters: $pp->ary(),
      );
    }

    if ($transactions_enabled
      && $limits_enabled
      && $pp->is_admin())
    {
      $bulk_min_limit_command = new UsersBulkMinLimitCommand();
      $bulk_min_limit_form = $this->createForm(
        type: UsersBulkMinLimitType::class,
        data: $bulk_min_limit_command,
      );
      $bulk_min_limit_form->handleRequest($request);
    }

    if ($bulk_min_limit_form
      && $bulk_min_limit_form->isSubmitted()
      && $bulk_min_limit_form->isValid())
    {
      $bulk_min_limit_command = $bulk_min_limit_form->getData();
      $selected = $bulk_min_limit_command->selected;
      $min_limit = $bulk_min_limit_command->min_limit;
      $select_ary = explode(',', $selected);
      $s_user_ids = [];
      foreach ($select_ary as $sel_id)
      {
        $s_user_ids[] = (int) trim($sel_id);
      }

      $s_user_ary = $user_repository->get_selected_min_limit(
        account_ids: $s_user_ids,
        schema: $pp->schema_o(),
      );

      $change_ary = [];
      $no_change_ary = [];

      foreach($s_user_ary as $uid => $user)
      {
        if ($user['min_limit'] === $min_limit)
        {
          $no_change_ary[] = (int) $uid;
        }
        else
        {
          $change_ary[] = (int) $uid;
        }
      }

      if (count($change_ary))
      {
        $account_repository->set_bulk_min_limit(
          account_ids: $change_ary,
          min_limit: $min_limit,
          created_by: $su->id() ?: null,
          schema: $pp->schema_o(),
        );

        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'users_list.bulk.min_limit.flash.success',
            'params'  => [
              'count' => count($change_ary),
            ],
          ],
        );
        foreach ($change_ary as $uid)
        {
          $this->addFlash(
            type: 'success',
            message: [
              'user'  => $s_user_ary[$uid],
            ],
          );
        }
      }

      if (count($no_change_ary))
      {
        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'flash.no_change_for',
            'params'  => [
              'count' => count($no_change_ary),
            ],
          ],
        );
        foreach ($no_change_ary as $uid)
        {
          $this->addFlash(
            type: 'warning',
            message: [
              'user'  => $s_user_ary[$uid],
            ],
          );
        }
      }

      return $this->redirectToRoute(
        route: $vr->get('users'),
        parameters: $pp->ary(),
      );
    }

    if ($transactions_enabled
      && $limits_enabled
      && $pp->is_admin())
    {
      $bulk_max_limit_command = new UsersBulkMaxLimitCommand();
      $bulk_max_limit_form = $this->createForm(
        type: UsersBulkMaxLimitType::class,
        data: $bulk_max_limit_command,
      );
      $bulk_max_limit_form->handleRequest($request);
    }

    if ($bulk_max_limit_form
      && $bulk_max_limit_form->isSubmitted()
      && $bulk_max_limit_form->isValid())
    {
      $bulk_max_limit_command = $bulk_max_limit_form->getData();
      $selected = $bulk_max_limit_command->selected;
      $max_limit = $bulk_max_limit_command->max_limit;
      $select_ary = explode(',', $selected);
      $s_user_ids = [];
      foreach ($select_ary as $sel_id)
      {
        $s_user_ids[] = (int) trim($sel_id);
      }

      $s_user_ary = $user_repository->get_selected_max_limit(
        account_ids: $s_user_ids,
        schema: $pp->schema_o(),
      );

      $change_ary = [];
      $no_change_ary = [];

      foreach($s_user_ary as $uid => $user)
      {
        if ($user['max_limit'] === $max_limit)
        {
          $no_change_ary[] = (int) $uid;
        }
        else
        {
          $change_ary[] = (int) $uid;
        }
      }

      if (count($change_ary))
      {
        $account_repository->set_bulk_max_limit(
          account_ids: $change_ary,
          max_limit: $max_limit,
          created_by: $su->id() ?: null,
          schema: $pp->schema_o(),
        );

        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'users_list.bulk.max_limit.flash.success',
            'params'  => [
              'count' => count($change_ary),
            ],
          ],
        );
        foreach ($change_ary as $uid)
        {
          $this->addFlash(
            type: 'success',
            message: [
              'user'  => $s_user_ary[$uid],
            ],
          );
        }
      }

      if (count($no_change_ary))
      {
        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'flash.no_change_for',
            'params'  => [
              'count' => count($no_change_ary),
            ],
          ],
        );
        foreach ($no_change_ary as $uid)
        {
          $this->addFlash(
            type: 'warning',
            message: [
              'user'  => $s_user_ary[$uid],
            ],
          );
        }
      }

      return $this->redirectToRoute(
        route: $vr->get('users'),
        parameters: $pp->ary(),
      );
    }

    if ($pp->is_admin())
    {
      $bulk_active_command = new UsersBulkActiveCommand();
      $bulk_active_form = $this->createForm(
        type: UsersBulkActiveType::class,
        data: $bulk_active_command,
        options: [
          'log_comment_enabled' => true,
        ],
      );
      $bulk_active_form->handleRequest($request);
    }

    if ($bulk_active_form
      && $bulk_active_form->isSubmitted()
      && $bulk_active_form->isValid())
    {
      $bulk_active_command = $bulk_active_form->getData();
      $selected = $bulk_active_command->selected;
      $is_active = $bulk_active_command->is_active;
      $send_email = $bulk_active_command->send_email;
      $send_email_cc = $bulk_active_command->send_email_cc;
      $log_comment = $bulk_active_form->get('log_comment')->getData();
      $select_ary = explode(',', $selected);
      $s_user_ids = [];
      foreach ($select_ary as $sel_id)
      {
        $s_user_ids[] = (int) trim($sel_id);
      }

      $s_user_ary = $user_repository->get_selected(
        user_ids: $s_user_ids,
        schema: $pp->schema_o(),
      );

      $change_ary = [];
      $no_change_ary = [];

      foreach($s_user_ary as $uid => $user)
      {
        if ($user['is_active'] === $is_active)
        {
          $no_change_ary[] = (int) $uid;
        }
        else
        {
          $change_ary[] = (int) $uid;
        }
      }

      if (count($change_ary))
      {
        $user_repository->set_bulk_active(
          is_active: $is_active,
          user_ids: $change_ary,
          schema: $pp->schema_o(),
        );

        $user_log_repository->bulk_insert(
          users_old_data_ary: [],
          new_data: [],
          comment: $log_comment,
          created_by: $su->id() ?: null,
          route: $pp->route(),
          action: 'active_edit',
          schema: $pp->schema_o(),
        );

        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'users_list.bulk.max_limit.flash.success',
            'params'  => [
              'count' => count($change_ary),
            ],
          ],
        );
        foreach ($change_ary as $uid)
        {
          $this->addFlash(
            type: 'success',
            message: [
              'user'  => $s_user_ary[$uid],
            ],
          );
        }
      }

      if (count($no_change_ary))
      {
        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'flash.no_change_for',
            'params'  => [
              'count' => count($no_change_ary),
            ],
          ],
        );
        foreach ($no_change_ary as $uid)
        {
          $this->addFlash(
            type: 'warning',
            message: [
              'user'  => $s_user_ary[$uid],
            ],
          );
        }
      }

      return $this->redirectToRoute(
        route: $vr->get('users'),
        parameters: $pp->ary(),
      );
    }

    if ($pp->is_admin()
      && $transactions_enabled
      && !in_array($status, ['pre-active', 'post-active', 'ip', 'im', 'extern', 'inactive'])
    )
    {
      $bulk_leaving_command = new UsersBulkAccountLeavingCommand();
      $bulk_leaving_form = $this->createForm(
        type: UsersBulkAccountLeavingType::class,
        data: $bulk_leaving_command,
        options: [
          'log_comment_enabled' => true,
        ],
      );
      $bulk_leaving_form->handleRequest($request);
    }

    if ($bulk_leaving_form
      && $bulk_leaving_form->isSubmitted()
      && $bulk_leaving_form->isValid())
    {
      $bulk_leaving_command = $bulk_leaving_form->getData();
      $selected = $bulk_leaving_command->selected;
      $is_leaving = $bulk_leaving_command->is_leaving;
      $send_email = $bulk_active_command->send_email;
      $send_email_cc = $bulk_active_command->send_email_cc;
      $log_comment = $bulk_active_form->get('log_comment')->getData();
      $select_ary = explode(',', $selected);
      $s_user_ids = [];
      foreach ($select_ary as $sel_id)
      {
        $s_user_ids[] = (int) trim($sel_id);
      }

      $s_user_ary = $user_repository->get_selected(
        user_ids: $s_user_ids,
        schema: $pp->schema_o(),
      );

      $change_ary = [];
      $no_change_ary = [];
      $users_old_data_ary = [];

      foreach($s_user_ary as $uid => $user)
      {
        if ($user['is_leaving'] === $is_leaving
          || !$user['is_active']
          || isset($user['remote_schema'])
          || isset($user['remote_email'])
        )
        {
          $no_change_ary[] = (int) $uid;
        }
        else
        {
          $change_ary[] = (int) $uid;
          $users_old_data_ary[$uid] = [
            'is_leaving'  => $is_leaving,
          ];
        }
      }

      if (count($change_ary))
      {
        $user_repository->set_bulk_leaving(
          is_leaving: $is_leaving,
          user_ids: $change_ary,
          schema: $pp->schema_o(),
        );

        $meta_data = array_intersect_key((array) $bulk_leaving_command, [
          'send_email'  => true,
          'send_email_cc' => true,
        ]);

        $user_log_repository->bulk_insert(
          users_old_data_ary: $users_old_data_ary,
          new_data: ['is_leaving' => $is_leaving],
          comment: $log_comment,
          created_by: $su->id() ?: null,
          route: $pp->route(),
          action: 'active_edit',
          schema: $pp->schema_o(),
          meta_data: $meta_data,
        );

        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'users_list.bulk.leaving.flash.success',
            'params'  => [
              'count' => count($change_ary),
            ],
          ],
        );

        foreach ($change_ary as $uid)
        {
          $this->addFlash(
            type: 'success',
            message: [
              'user'  => $s_user_ary[$uid],
            ],
          );
        }
      }

      if (count($no_change_ary))
      {
        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'flash.no_change_for',
            'params'  => [
              'count' => count($no_change_ary),
            ],
          ],
        );

        foreach ($no_change_ary as $uid)
        {
          $this->addFlash(
            type: 'warning',
            message: [
              'user'  => $s_user_ary[$uid],
            ],
          );
        }
      }

      return $this->redirectToRoute(
        route: $vr->get('users'),
        parameters: $pp->ary(),
      );
    }

    /**
     * restore checkboxes after form error
     */
    $sel = [];

    if ($request->isMethod('POST'))
    {
      $all_posted = $request->request->all();
      foreach ($all_posted as $form_data)
      {
        if (is_array($form_data) && isset($form_data['selected']))
        {
          $sel_ids = array_filter(explode(',', $form_data['selected']));
          $sel = array_fill_keys($sel_ids, true);
          break;
        }
      }
    }

    /**
     * End bulk POST
     */

    /**
     * Columns select form
     */
    $cols_session_key = 'users_list.cols.' . $pp->role();
    $cols_session_ary = $session->get($cols_session_key, []);

    $cols_session_checked_ary = array_filter($cols_session_ary, fn($v) => $v === true);

    if (empty($cols_session_checked_ary))
    {
      $cols_session_ary['code'] = true;
      $cols_session_ary['name'] = true;
      $cols_session_ary['postcode'] = true;
      $cols_session_ary['balance'] = true;
    }

    if (!isset($cols_session_ary['transactions_days'])){
      $cols_session_ary['transactions_days'] = 365;
    }

    $contact_types = $contact_repository->get_all_contact_types(
      schema: $pp->schema_o(),
    );
    $ct_abbrev_key_ary = [];
    foreach ($contact_types as $ct)
    {
      $ct_abbrev_key_ary[$ct['abbrev']] = true;
    }

    $cols_command = new UsersColsCommand();
    $cols_command->populate_from_array($cols_session_ary);

    $cols_form = $this->createForm(
      type: UsersColsType::class,
      data: $cols_command,
      options: [
        'contact_types' => $contact_types,
      ]
    );
    $cols_form->handleRequest($request);

    if ($cols_form->isSubmitted()
      && $cols_form->isValid())
    {
      $cols_s_ary = [];
      /** @var Form $cols_form */
      $submit_btn_name = $cols_form->getClickedButton()->getName();
      if ($submit_btn_name === 'submit')
      {
        $cols_command = $cols_form->getData();
        $cols_s_ary = $cols_command->get_clean_array();
        $session->set($cols_session_key, $cols_s_ary);
      }
      else if ($submit_btn_name === 'reset')
      {
        // recreate command and form.
        $cols_command = new UsersColsCommand();
        $cols_command->code = true;
        $cols_command->name = true;
        $cols_command->postcode = true;
        $cols_command->balance = true;
        $cols_command->transactions_days = 365;
        $cols_form = $this->createForm(
          type: UsersColsType::class,
          data: $cols_command,
          options: [
            'contact_types' => $contact_types,
          ]
        );
        $cols_s_ary = $cols_command->get_clean_array();
        $session->set($cols_session_key, $cols_s_ary);
      }
    }

    /* begin remove columns disabled by configuration */

    if (!$full_name_enabled)
    {
      $cols_command->full_name = false;
      $cols_command->full_name_access = false;
    }

    if (!$postcode_enabled)
    {
      $cols_command->postcode = false;
    }

    if (!$comments_enabled)
    {
      $cols_command->comments = false;
    }

    if (!$hobbies_enabled)
    {
      $cols_command->hobbies = false;
    }

    if (!$birthdate_enabled)
    {
      $cols_command->birthdate = false;
    }

    if (!$admin_comments_enabled)
    {
      $cols_command->admin_comments = false;
    }

    if (!$periodic_mail_enabled)
    {
      $cols_command->periodic_overview = false;
    }

    if ($pp->is_guest())
    {
      $cols_command->distance = false;
    }

    if (!$mollie_enabled)
    {
      $cols_command->mollie = false;
    }

    if (!$transactions_enabled)
    {
      $cols_command->balance = false;
      $cols_command->balance_on_date = false;
      $cols_command->min_limit = false;
      $cols_command->max_limit = false;
      $cols_command->transactions_in = false;
      $cols_command->transactions_out = false;
      $cols_command->transactions_total = false;
      $cols_command->amount_in = false;
      $cols_command->amount_out = false;
      $cols_command->amount_total = false;
    }

    if (!$limits_enabled)
    {
      $cols_command->min_limit = false;
      $cols_command->max_limit = false;
    }

    if (!$messages_enabled)
    {
      $cols_command->wants = false;
      $cols_command->offers = false;
      $cols_command->offers_and_wants = false;
    }

    $checked_contacts_ary = $cols_command->contacts ?? [];

    foreach($checked_contacts_ary as $abbrev_key => $ch)
    {
      if (!isset($ct_abbrev_key_ary[$abbrev_key]))
      {
        unset($cols_command->contacts[$abbrev_key]);
      }
    }

    if (!$pp->is_admin())
    {
      $cols_command->admin_comments = false;
      $cols_command->created_at = false;
      $cols_command->activated_at = false;
      $cols_command->last_edit_at = false;
      $cols_command->last_login_at = false;
      $cols_command->periodic_overview = false;
      $cols_command->mollie = false;
    }

    /**
     * end remove columns disabled by configuration
     */

    /**
     * End columns form
     */

    /**
     * Fetch data
     */

    $users = $user_repository->get_all_by_status(
      status: $status,
      schema: $pp->schema_o(),
    );

    /** @var UsersColsCommand $cols_command */
    if ($cols_command->balance_on_date)
    {
      if (isset($cols_command->balance_date)
        && $cols_command->balance_date !== '')
      {
        $datetime = new \DateTimeImmutable($cols_command->balance_date, new \DateTimeZone('UTC'));

        $balance_on_date_ary = $account_repository->get_balance_ary_on_date(
          datetime: $datetime,
          schema: $pp->schema_o(),
        );
      }
    }

    /**
     * always: injected as data-attr to
     * calculate the total
     */
    $balance_ary = $account_repository->get_balance_ary(
      schema: $pp->schema_o(),
    );

    if ($cols_command->min_limit)
    {
      $min_limit_ary = $account_repository->get_min_limit_ary(
        schema: $pp->schema_o(),
      );
    }

    if ($cols_command->max_limit)
    {
      $max_limit_ary = $account_repository->get_max_limit_ary(
        schema: $pp->schema_o(),
      );
    }

    if ($cols_command->last_login_at)
    {
      $last_login_ary = $login_repository->get_last_login_ary(
        schema: $pp->schema_o(),
      );
    }

    if ($cols_command->contacts || $cols_command->distance)
    {
      $contacts_ary = $user_repository->get_contacts_ary(
        current_user_id: $su->id(),
        current_user_schema: $su->schema_o(),
        schema: $pp->schema_o(),
      );
      error_log('====CONTACTS_ARY++++');
      error_log(json_encode($contacts_ary));

      if ($cols_command->distance)
      {
        $distance_ary = [];
        foreach ($contacts_ary as $uid => $c_ary)
        {
          foreach ($c_ary as $c_abbrev => $adr_ary)
          {
            if ($c_abbrev !== 'adr')
            {
              continue;
            }
            foreach ($adr_ary as $adr)
            {
              if (!isset($adr['distance']))
              {
                continue;
              }
              if (!$item_access_service->is_visible($adr['access']))
              {
                $distance_ary[$uid] = 'hidden';
                continue;
              }
              // distance in meters to last edited address of each user
              $distance_ary[$uid] = (int) $adr['distance'];
            }
          }
        }
        error_log('====DISTANCE_ARY++++');
        error_log(json_encode($distance_ary));
      }
    }

    if ($cols_command->mollie && $pp->is_admin())
    {
      $mollie_ary = $mollie_repository->get_last_status_ary(
        schema: $pp->schema_o(),
      );
    }

    if ($cols_command->offers
      || $cols_command->wants
      || $cols_command->offers_and_wants
    )
    {
      $messages_ary = $message_repository->get_counts_for_each_user(
        schema: $pp->schema_o(),
      );
    }

    if ($cols_command->transactions_days
      && ($cols_command->transactions_in
        || $cols_command->transactions_out
        || $cols_command->transactions_total
        || $cols_command->amount_in
        || $cols_command->amount_out
        || $cols_command->amount_total
    ))
    {
      $since_unix = time() - ($cols_command->transactions_days * 86400);
      $since = \DateTimeImmutable::createFromFormat('U', (string) $since_unix);
      $transactions_from_date = $since->format('Y-m-d H:i:s');
      $trans_ary = $transaction_repository->get_activity_for_each_user(
        since: $since,
        exclude_account_id: $cols_command->transactions_exclude_code,
        schema: $pp->schema_o(),
      );
    }

    return $this->render('users/users_list.html.twig', [
      'sel'               => $sel,
      'users'             => $users,
      'cols'              => $cols_command,
      'balance_ary'       => $balance_ary ?? [],
      'balance_on_date_ary' => $balance_on_date_ary ?? [],
      'min_limit_ary'     => $min_limit_ary ?? [],
      'max_limit_ary'     => $max_limit_ary ?? [],
      'last_login_ary'    => $last_login_ary ?? [],
      'contacts_ary'      => $contacts_ary ?? [],
      'distance_ary'      => $distance_ary ?? [],
      'ref_geo'           => $ref_geo ?? null,
      'mollie_ary'        => $mollie_ary ?? [],
      'messages_ary'      => $messages_ary ?? [],
      'trans_ary'         => $trans_ary ?? [],
      'transactions_from_date'  => $transactions_from_date ?? null,
      'filter_form'       => $filter_form->createView(),
      'row_count'         => count($users),
      'bulk_email_form'   => $bulk_email_form?->createView(),
      'bulk_full_name_access_form' => $bulk_full_name_access_form?->createView(),
      'bulk_role_form' => $bulk_role_form?->createView(),
      'bulk_status_form'  => $bulk_status_form?->createView(),
      'bulk_comments_form'    => $bulk_comments_form?->createView(),
      'bulk_admin_comments_form' => $bulk_admin_comments_form?->createView(),
      'bulk_min_limit_form'     => $bulk_min_limit_form?->createView(),
      'bulk_max_limit_form'     => $bulk_max_limit_form?->createView(),
      'bulk_periodic_overview_en_form'     => $bulk_periodic_overview_en_form?->createView(),
      'bulk_active_form'     => $bulk_active_form?->createView(),
      'bulk_leaving_form'     => $bulk_leaving_form?->createView(),
      'cols_form'   => $cols_form->createView(),
    ]);
  }

  static public function get_status_def_ary(
    ConfigService $config_service,
    ItemAccessService $item_access_service,
    PageParamsService $pp
  ):array
  {
    $new_user_treshold = $config_service->get_new_user_treshold(
      schema: $pp->schema_o(),
    );

    $status_def_ary = [];

    $status_def_ary['active'] = [
      'lbl'	=> $pp->is_admin() ? 'Actief' : 'Alle',
      'sql'	=> [
        'where'     => ['u.status in (1, 2)'],
      ],
      'st'	=> [1, 2],
    ];

    if ($config_service->get_bool(
      config_id: 'users.new.enabled',
      schema: $pp->schema_o(),
    ))
    {
      $new_users_access_pane = $config_service->get_str(
        config_id: 'users.new.access_pane',
        schema: $pp->schema_o(),
      );

      if ($item_access_service->is_visible($new_users_access_pane))
      {
        $status_def_ary['new'] = [
          'lbl'	=> 'Instappers',
          'sql'	=> [
            'where'     => ['u.status = 1 and u.adate > ?'],
            'params'    => [$new_user_treshold],
            'types'     => [Types::DATETIME_IMMUTABLE],
          ],
          'cl'	=> 'success',
          'st'	=> 3,
        ];
      }
    }

    if ($config_service->get_bool(
      config_id: 'users.leaving.enabled',
      schema: $pp->schema_o(),
    ))
    {
      $leaving_users_access_pane = $config_service->get_str(
        config_id: 'users.leaving.access_pane',
        schema: $pp->schema_o(),
      );

      if ($item_access_service->is_visible($leaving_users_access_pane))
      {
        $status_def_ary['leaving'] = [
          'lbl'	=> 'Uitstappers',
          'sql'	=> [
            'where'     => ['u.status = 2'],
          ],
          'cl'	=> 'danger',
          'st'	=> 2,
        ];
      }
    }

    if ($pp->is_admin())
    {
      $status_def_ary['inactive'] = [
        'lbl'	=> 'Inactief',
        'sql'	=> [
          'where'     => ['u.status = 0'],
        ],
        'cl'	=> 'inactive',
        'st'	=> 0,
      ];

      $status_def_ary['ip'] = [
        'lbl'	=> 'Info-pakket',
        'sql'	=> [
          'where'     => ['u.status = 5'],
        ],
        'cl'	=> 'warning',
        'st'	=> 5,
      ];

      $status_def_ary['im'] = [
        'lbl'	=> 'Info-moment',
        'sql'	=> [
          'where'     => ['u.status = 6'],
        ],
        'cl'	=> 'info',
        'st'	=> 6
      ];

      $status_def_ary['extern'] = [
        'lbl'	=> 'Extern',
        'sql'	=> [
          'where'     => ['u.status = 7'],
        ],
        'cl'	=> 'extern',
        'st'	=> 7,
      ];

      $status_def_ary['all'] = [
        'lbl'	=> 'Alle',
        'sql'	=> [],
      ];
    }

    return $status_def_ary;
  }

  static public function get_tab_selector(
    array $params,
    LinkRender $link_render,
    ItemAccessService $item_access_service,
    ConfigService $config_service,
    PageParamsService $pp,
    VarRouteService $vr,
  ):string
  {
    $status_def_ary = self::get_status_def_ary($config_service, $item_access_service, $pp);

    $out = '<div class="pull-right hidden-xs hidden-sm print-hide">';
    $out .= 'Totaal: <span id="total"></span>';
    $out .= '</div>';

    if (count($status_def_ary) < 2)
    {
      return $out;
    }

    $out .= '<ul class="nav nav-tabs" id="nav-tabs">';

    $nav_params = $params;

    foreach ($status_def_ary as $k => $tab)
    {
      $nav_params['status'] = $k;

      $out .= '<li';
      $out .= $params['status'] === $k ? ' class="active"' : '';
      $out .= '>';

      $class_ary = isset($tab['cl']) ? ['class' => 'bg-' . $tab['cl']] : [];

      $out .= $link_render->link(
        $vr->get('users'),
        $pp->ary(),
        $nav_params,
        $tab['lbl'],
        $class_ary
      );

      $out .= '</li>';
    }

    $out .= '</ul>';

    return $out;
  }
}
