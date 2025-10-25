<?php declare(strict_types=1);

namespace App\Controller\Users;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Cnst\StatusCnst;
use App\Cnst\RoleCnst;
use App\Cnst\BulkCnst;
use App\Command\Users\UsersBulkAdminCommentsCommand;
use App\Command\Users\UsersBulkCommentsCommand;
use App\Command\Users\UsersBulkEmailCommand;
use App\Command\Users\UsersBulkFullNameAccessCommand;
use App\Command\Users\UsersBulkMaxLimitCommand;
use App\Command\Users\UsersBulkMinLimitCommand;
use App\Command\Users\UsersBulkPeriodicOverviewEnCommand;
use App\Command\Users\UsersBulkRoleCommand;
use App\Command\Users\UsersBulkStatusCommand;
use App\Email\UserBulk\Copy\EmailUserBulkCopyMessage;
use App\Email\UserBulk\Message\EmailUserBulkMessageMessage;
use App\Form\Type\Filter\QTextSearchFilterType;
use App\Form\Type\Users\UsersBulkAdminCommentsType;
use App\Form\Type\Users\UsersBulkCommentsType;
use App\Form\Type\Users\UsersBulkEmailType;
use App\Form\Type\Users\UsersBulkFullNameAccessType;
use App\Form\Type\Users\UsersBulkMaxLimitType;
use App\Form\Type\Users\UsersBulkMinLimitType;
use App\Form\Type\Users\UsersBulkPeriodicOverviewEnType;
use App\Form\Type\Users\UsersBulkRoleType;
use App\Form\Type\Users\UsersBulkStatusType;
use App\Render\AccountRender;
use App\Render\SelectRender;
use App\Repository\AccountRepository;
use App\Repository\UserRepository;
use App\Service\CacheService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use Doctrine\DBAL\ArrayParameterType;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersListController extends AbstractController
{
  #[Route(
    '/{system}/{role_short}/users/{status}',
    name: 'users_list',
    methods: ['GET', 'POST'],
    priority: 20,
    requirements: [
      'status'        => '%assert.account_status%',
      'system'        => '%assert.system%',
      'role_short'    => '%assert.role_short.guest%',
    ],
    defaults: [
      'status'        => 'active',
      'module'        => 'users',
    ],
  )]

  public function __invoke(
    Request $request,
    RequestStack $request_stack,
    string $status,
    Db $db,
    AccountRepository $account_repository,
    UserRepository $user_repository,
    LoggerInterface $logger,
    AccountRender $account_render,
    CacheService $cache_service,
    ConfigService $config_service,
    DateFormatService $date_format_service,
    FormTokenService $form_token_service,
    IntersystemsService $intersystems_service,
    ItemAccessService $item_access_service,
    LinkRender $link_render,
    SelectRender $select_render,
    TypeaheadService $typeahead_service,
    UserCacheService $user_cache_service,
    MessageBusInterface $bus,
    PageParamsService $pp,
    SessionUserService $su,
    VarRouteService $vr,
    #[Autowire(service: 'html_sanitizer.sanitizer.admin_email_sanitizer')]
    HtmlSanitizerInterface $html_sanitizer
  ):Response
  {
    if (!$pp->is_admin() && !in_array($status, ['active', 'new', 'leaving']))
    {
      throw new AccessDeniedHttpException('No access for status: ' . $status);
    }

    $session = $request_stack->getSession();

    $full_name_enabled = $config_service->get_bool('users.fields.full_name.enabled', $pp->schema());
    $postcode_enabled = $config_service->get_bool('users.fields.postcode.enabled', $pp->schema());
    $birthdate_enabled = $config_service->get_bool('users.fields.birthdate.enabled', $pp->schema());
    $hobbies_enabled = $config_service->get_bool('users.fields.hobbies.enabled', $pp->schema());
    $comments_enabled = $config_service->get_bool('users.fields.comments.enabled', $pp->schema());
    $admin_comments_enabled = $config_service->get_bool('users.fields.admin_comments.enabled', $pp->schema());
    $periodic_mail_enabled = $config_service->get_bool('periodic_mail.enabled', $pp->schema());

    $mollie_enabled = $config_service->get_bool('mollie.enabled', $pp->schema());
    $messages_enabled = $config_service->get_bool('messages.enabled', $pp->schema());
    $transactions_enabled = $config_service->get_bool('transactions.enabled', $pp->schema());
    $limits_enabled = $config_service->get_bool('accounts.limits.enabled', $pp->schema());

    $currency = $config_service->get_str('transactions.currency.name', $pp->schema());
    $new_users_days = $config_service->get_int('users.new.days', $pp->schema());
    $new_users_enabled = $config_service->get_bool('users.new.enabled', $pp->schema());
    $new_users_access_list = $config_service->get_str('users.new.access_list', $pp->schema());
    $leaving_users_enabled = $config_service->get_bool('users.leaving.enabled', $pp->schema());
    $leaving_users_access_list = $config_service->get_str('users.leaving.access_list', $pp->schema());

    $show_new_status = $new_users_enabled;

    if ($show_new_status)
    {
      $new_users_access = $config_service->get_str('users.new.access', $pp->schema());
      $show_new_status = $item_access_service->is_visible($new_users_access);
    }

    $show_leaving_status = $leaving_users_enabled;

    if ($show_leaving_status)
    {
      $leaving_users_access = $config_service->get_str('users.leaving.access', $pp->schema());
      $show_leaving_status = $item_access_service->is_visible($leaving_users_access);
    }

    $errors = [];

    $query_params = $request->query->all();

    $filter_form = $this->createForm(QTextSearchFilterType::class);
    $filter_form->handleRequest($request);

    $show_columns = $request->query->all('sh');

    $selected_users = $request->request->all('sel');

    $bulk_field = $request->request->all('bulk_field');
    $bulk_verify = $request->request->all('bulk_verify');
    $bulk_submit = $request->request->all('bulk_submit');

    $new_user_treshold = $config_service->get_new_user_treshold($pp->schema());

    $user_tabs = BulkCnst::USER_TABS;

    if (!$full_name_enabled)
    {
      unset($user_tabs['full_name_access']);
    }

    if (!$comments_enabled)
    {
      unset($user_tabs['comments']);
    }

    if (!$admin_comments_enabled)
    {
      unset($user_tabs['admin_comments']);
    }

    if (!$transactions_enabled || !$limits_enabled)
    {
      unset($user_tabs['min_limit'], $user_tabs['max_limit']);
    }

    if (!$periodic_mail_enabled)
    {
      unset($user_tabs['periodic_overview_en']);
    }

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
      && $config_service->get_bool('mail.enabled', $pp->schema())
      && !$su->is_master())
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
        $str = $account_render->link($u['id'], $pp->ary());
        if (count($u['email_addresses']))
        {
          $user_ids_sent[] = $u['id'];
          $flash_sent_ary[] = $str;
        }
        else
        {
          $user_ids_not_sent[] = $u['id'];
          $flash_not_sent_ary[] = $str;
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
      foreach($flash_sent_ary as $msg)
      {
        $this->addFlash(
          type: 'success',
          message: $msg,
        );
      }
      if (count($flash_not_sent_ary))
      {
        $this->addFlash(
          type: 'success',
          message: [
            'key'   => 'flash.email.not_sent_to',
            'params'  => [
              'count' => count($flash_not_sent_ary),
          ],
        ]);
      }
      foreach($flash_not_sent_ary as $msg)
      {
        $this->addFlash(
          type: 'success',
          message: $msg,
        );
      }
      return $this->redirectToRoute(
        route: $vr->get('users'),
        parameters: $pp->ary(),
      );
    }

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
      $user_repository->set_bulk_full_name_access(
        full_name_access: $access,
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
          'key' => 'users_list.bulk.full_name_access.flash.success',
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

    if ($pp->is_admin())
    {
      $bulk_role_command = new UsersBulkRoleCommand();
      $bulk_role_form = $this->createForm(
        type: UsersBulkRoleType::class,
        data: $bulk_role_command,
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
      $select_ary = explode(',', $selected);
      $s_user_ids = [];
      foreach ($select_ary as $sel_id)
      {
        $s_user_ids[] = (int) trim($sel_id);
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
        $uid = (int) trim($sel_id);
        $account_repository->update_min_limit(
          account_id: $uid,
          min_limit: $min_limit,
          created_by: $su->id() ?: null,
          schema: $pp->schema_o(),
        );
        $s_user_ids[] = $uid;
      }
      $this->addFlash(
        type: 'success',
        message: [
          'key' => 'users_list.bulk.min_limit.flash.success',
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
      $bulk_min_limit_command = $bulk_max_limit_form->getData();
      $selected = $bulk_max_limit_command->selected;
      $max_limit = $bulk_max_limit_command->max_limit;
      $select_ary = explode(',', $selected);
      $s_user_ids = [];
      foreach ($select_ary as $sel_id)
      {
        $uid = (int) trim($sel_id);
        $account_repository->update_max_limit(
          account_id: $uid,
          max_limit: $max_limit,
          created_by: $su->id() ?: null,
          schema: $pp->schema_o(),
        );
        $s_user_ids[] = $uid;
      }
      $this->addFlash(
        type: 'success',
        message: [
          'key' => 'users_list.bulk.max_limit.flash.success',
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

    /**
     * End bulk POST
     */

    /**
     * Fetch columns list
     */

    $sql_map = [
      'where'     => [],
      'where_or'  => [],
      'params'    => [],
      'types'     => [],
    ];

    $sql = [];
    $sql['common'] = $sql_map;
    $sql['common']['where'][] = '1 = 1';

    $status_def_ary = self::get_status_def_ary($config_service, $item_access_service, $pp);

    $sql['status'] = $sql_map;

    foreach ($status_def_ary[$status]['sql'] as $st_def_key => $def_sql_ary)
    {
      foreach ($def_sql_ary as $def_val)
      {
        $sql['status'][$st_def_key][] = $def_val;
      }
    }

    $params = ['status'	=> $status];

    $ref_geo = [];

    $type_contact = $db->fetchAllAssociative('select id, abbrev, name
        from ' . $pp->schema() . '.type_contact', [], []);

    $columns = [
        'u'		=> [
            'code'		    => 'Code',
            'name'			=> 'Naam',
            'full_name'		=> 'Volledige naam',
            'postcode'		=> 'Postcode',
            'role'	        => 'Rol',
            'balance'		=> 'Saldo',
            'balance_date'	=> 'Saldo op ',
            'min'		    => 'Min',
            'max'		    => 'Max',
            'comments'		=> 'Commentaar',
            'hobbies'		=> 'Hobbies/interesses',
        ],
    ];

    if ($pp->is_admin())
    {
        $columns['u'] += [
            'birthdate'              => 'Geboortedatum',
            'admin_comments'	    => 'Admin commentaar',
            'periodic_overview_en'	=> 'Periodieke Overzichts E-mail',
            'created_at'	        => 'Gecreëerd',
            'last_edit_at'	        => 'Aangepast',
            'adate'			        => 'Geactiveerd',
            'last_login'		    => 'Laatst ingelogd',
        ];
    }

    foreach ($type_contact as $tc)
    {
        $columns['c'][$tc['abbrev']] = $tc['name'];
    }

    $columns['d'] = [
        'distance'	=> 'Afstand',
    ];

    if ($pp->is_admin())
    {
        $columns['mollie'] = [
            'mollie'    => 'Mollie',
        ];
    }

    $columns['m'] = [
        'wants'		=> 'Vraag',
        'offers'	=> 'Aanbod',
        'total'		=> 'Vraag en aanbod',
    ];

    $message_type_filter = [
        'wants'		=> ['ow' => ['want']],
        'offers'	=> ['ow' => ['offer']],
        'total'		=> [],
    ];

    $columns['a'] = [
        'trans'		=> [
            'in'	=> 'Transacties in',
            'out'	=> 'Transacties uit',
            'total'	=> 'Transacties totaal',
        ],
        'amount'	=> [
            'in'	=> $currency . ' in',
            'out'	=> $currency . ' uit',
            'total'	=> $currency . ' totaal',
        ],
    ];

    $columns['p'] = [
        'c'	=> [
            'adr_split'	=> '.',
        ],
        'a'	=> [
            'days'	=> '.',
            'code'	=> '.',
        ],
        'u'	=> [
            'balance_date'	=> '.',
        ],
    ];

    if (!$full_name_enabled)
    {
        unset($columns['u']['full_name']);
    }

    if (!$postcode_enabled)
    {
        unset($columns['u']['postcode']);
    }

    if (!$comments_enabled)
    {
        unset($columns['u']['comments']);
    }

    if (!$hobbies_enabled)
    {
        unset($columns['u']['hobbies']);
    }

    if (!$birthdate_enabled)
    {
        unset($columns['u']['birthdate']);
    }

    if (!$admin_comments_enabled)
    {
        unset($columns['u']['admin_comments']);
    }

    if (!$periodic_mail_enabled)
    {
        unset($columns['u']['periodic_overview_en']);
    }

    if (!$mollie_enabled)
    {
        unset($columns['mollie']);
    }

    if (!$transactions_enabled)
    {
        unset($columns['u']['balance']);
        unset($columns['u']['min']);
        unset($columns['u']['max']);
        unset($columns['u']['balance_date']);
        unset($columns['a']);
    }

    if (!$limits_enabled)
    {
        unset($columns['u']['min']);
        unset($columns['u']['max']);
    }

    if (!$messages_enabled)
    {
        unset($columns['m']);
    }

    $session_users_columns_key = 'users_columns_';
    $session_users_columns_key .= $pp->role();

    if (count($show_columns))
    {
        $show_columns = self::array_intersect_key_recursive($show_columns, $columns);

        $session->set($session_users_columns_key, $show_columns);
    }
    else
    {
        if ($pp->is_admin() || $pp->is_guest())
        {
            $preset_columns = [
                'u'	=> [
                    'code'	=> 1,
                    'name'		=> 1,
                    'postcode'	=> 1,
                    'balance'		=> 1,
                ],
            ];
        }
        else
        {
            $preset_columns = [
                'u' => [
                    'code'	=> 1,
                    'name'		=> 1,
                    'postcode'	=> 1,
                    'balance'		=> 1,
                ],
                'c'	=> [
                    'gsm'	=> 1,
                    'tel'	=> 1,
                    'adr'	=> 1,
                ],
                'd'	=> [
                    'distance'	=> 1,
                ],
            ];
        }

        $show_columns = $session->get($session_users_columns_key) ?? $preset_columns;
    }

    if (!$full_name_enabled)
    {
        unset($show_columns['u']['full_name']);
    }

    if (!$postcode_enabled)
    {
        unset($show_columns['u']['postcode']);
    }

    if (!$comments_enabled)
    {
        unset($show_columns['u']['comments']);
    }

    if (!$hobbies_enabled)
    {
        unset($show_columns['u']['hobbies']);
    }

    if (!$birthdate_enabled)
    {
        unset($show_columns['u']['birthdate']);
    }

    if (!$admin_comments_enabled)
    {
        unset($show_columns['u']['admin_comments']);
    }

    if (!$periodic_mail_enabled)
    {
        unset($show_columns['u']['periodic_overview_en']);
    }

    if (!$mollie_enabled)
    {
        unset($show_columns['mollie']);
    }

    if (!$transactions_enabled)
    {
        unset($show_columns['u']['balance']);
        unset($show_columns['u']['min']);
        unset($show_columns['u']['max']);
        unset($show_columns['u']['balance_date']);
        unset($show_columns['a']);
    }

    if (!$limits_enabled)
    {
        unset($show_columns['u']['min']);
        unset($show_columns['u']['max']);
    }

    if (!$messages_enabled)
    {
        unset($show_columns['m']);
    }

    $adr_split = $show_columns['p']['c']['adr_split'] ?? '';
    $activity_days = $show_columns['p']['a']['days'] ?? 365;
    $activity_days = $activity_days < 1 ? 365 : $activity_days;
    $activity_filter_code = $show_columns['p']['a']['code'] ?? '';
    $balance_date = $show_columns['p']['u']['balance_date'] ?? '';
    $balance_date = trim($balance_date);

    $users = [];

    $sql_where = implode(' and ', array_merge(...array_column($sql, 'where')));
    $sql_params = array_merge(...array_column($sql, 'params'));
    $sql_types = array_merge(...array_column($sql, 'types'));

    $query = 'select u.*
      from ' . $pp->schema() . '.users u
      where ' . $sql_where . '
      order by u.code asc';

    $res = $db->executeQuery($query, $sql_params, $sql_types);

    while($row = $res->fetchAssociative())
    {
      $users[$row['id']] = $row;
    }

    if (isset($show_columns['u']['balance_date']))
    {
        if ($balance_date)
        {
            $balance_date_rev = $date_format_service->reverse($balance_date, $pp->schema());
        }

        if (!isset($balance_date_rev) || $balance_date_rev === '')
        {
            $balance_date = $date_format_service->get('now', 'day', $pp->schema());
            $balance_date_rev = 'now';
        }

        $datetime = new \DateTimeImmutable($balance_date_rev, new \DateTimeZone('UTC'));

        $balance_ary_on_date = $account_repository->get_balance_ary_on_date(
          datetime: $datetime,
          schema: $pp->schema_o(),
        );

        array_walk($users, function(&$user, $user_id) use ($balance_ary_on_date){
            $user['balance_date'] = $balance_ary_on_date[$user_id] ?? 0;
        });
    }

    $balance_ary = $account_repository->get_balance_ary(
      schema: $pp->schema_o(),
    );

    array_walk($users, function(&$user, $user_id) use ($balance_ary){
      $user['balance'] = $balance_ary[$user_id] ?? 0;
    });

    if (isset($show_columns['u']['min']))
    {
      $min_limit_ary = $account_repository->get_min_limit_ary(
        schema: $pp->schema_o(),
      );
      $min_intersect_ary = array_intersect_key($min_limit_ary, $users);

      foreach ($min_intersect_ary as $user_id => $min_limit)
      {
        $users[$user_id]['min'] = $min_limit;
      }
    }

    if (isset($show_columns['u']['max']))
    {
        $max_limit_ary = $account_repository->get_max_limit_ary(
          schema: $pp->schema_o(),
        );
        $max_intersect_ary = array_intersect_key($max_limit_ary, $users);

        foreach ($max_intersect_ary as $user_id => $max_limit)
        {
            $users[$user_id]['max'] = $max_limit;
        }
    }

    if (isset($show_columns['u']['last_login']))
    {
        $res = $db->executeQuery('select user_id, max(created_at) as last_login
            from ' . $pp->schema() . '.login
            group by user_id');

        while ($row = $res->fetchAssociative())
        {
            if (!isset($users[$row['user_id']]))
            {
                continue;
            }

            $users[$row['user_id']]['last_login'] = $row['last_login'];
        }
    }

    if (isset($show_columns['c']) || (isset($show_columns['d']) && !$su->is_master()))
    {
        $contacts_query = 'select tc.abbrev,
                c.user_id, c.value, c.access
            from ' . $pp->schema() . '.contact c, ' .
                $pp->schema() . '.type_contact tc, ' .
                $pp->schema() . '.users u
            where tc.id = c.id_type_contact ' .
                (isset($show_columns['c']) ? '' : 'and tc.abbrev = \'adr\' ') .
                'and c.user_id = u.id
                and ' . $sql_where;

        $res = $db->executeQuery($contacts_query, $sql_params, $sql_types);

        $contacts = [];

        while ($row = $res->fetchAssociative())
        {
            $contacts[$row['user_id']][$row['abbrev']][] = [
                'value'         => $row['value'],
                'access'        => $row['access'],
            ];
        }
    }

    if (isset($show_columns['d']) && !$su->is_master())
    {
        if (($pp->is_guest() && $su->schema())
            || !isset($contacts[$su->id()]['adr']))
        {
            $my_adr = $db->fetchOne('select c.value
                from ' . $su->schema() . '.contact c, ' .
                    $su->schema() . '.type_contact tc
                where c.user_id = ?
                    and c.id_type_contact = tc.id
                    and tc.abbrev = \'adr\'',
                    [$su->id()], [\PDO::PARAM_INT]);
        }
        else if (!$pp->is_guest()
            && isset($contacts[$su->id()]['adr'][0]['value']))
        {
            $my_adr = trim($contacts[$su->id()]['adr'][0]['value']);
        }

        if (isset($my_adr))
        {
            $ref_geo = $cache_service->get('geo_' . $my_adr);
        }
    }

    if (isset($show_columns['mollie']) && $pp->is_admin())
    {
        $mollie_ary = [];

        $res = $db->executeQuery('select distinct on (u.id)
            u.id, p.is_paid, p.is_canceled,
            p.created_at, p.amount, r.description
            from ' . $pp->schema() . '.users u
            inner join ' . $pp->schema() . '.mollie_payments p
                on u.id = p.user_id
            inner join ' . $pp->schema() . '.mollie_payment_requests r
                on r.id = p.request_id
            where ' . $sql_where . '
            order by u.id asc, p.created_at desc',
            $sql_params, $sql_types);

        while (($row = $res->fetchAssociative()) !== false)
        {
            $mollie_ary[$row['id']] = $row;
        }
    }

    if (isset($show_columns['m']))
    {
        $msgs_count = [];

        if (isset($show_columns['m']['offers']))
        {
            $res = $db->executeQuery('select count(m.id), m.user_id
                from ' . $pp->schema() . '.messages m, ' .
                    $pp->schema() . '.users u
                where m.offer_want = \'offer\'
                    and m.user_id = u.id
                    and ' . $sql_where . '
                group by m.user_id', $sql_params, $sql_types);

            while ($row = $res->fetchAssociative())
            {
                $msgs_count[$row['user_id']]['offers'] = $row['count'];
            }
        }

        if (isset($show_columns['m']['wants']))
        {
            $res = $db->executeQuery('select count(m.id), m.user_id
                from ' . $pp->schema() . '.messages m, ' .
                    $pp->schema() . '.users u
                where m.offer_want = \'want\'
                    and m.user_id = u.id
                    and ' . $sql_where . '
                group by m.user_id', $sql_params, $sql_types);

            while ($row = $res->fetchAssociative())
            {
                $msgs_count[$row['user_id']]['wants'] = $row['count'];
            }
        }

        if (isset($show_columns['m']['total']))
        {
            $res = $db->executeQuery('select count(m.id), m.user_id
                from ' . $pp->schema() . '.messages m, ' .
                    $pp->schema() . '.users u
                where m.user_id = u.id
                    and ' . $sql_where . '
                group by m.user_id', $sql_params, $sql_types);

            while ($row = $res->fetchAssociative())
            {
                $msgs_count[$row['user_id']]['total'] = $row['count'];
            }
        }
    }

    if (isset($show_columns['a']))
    {
        $activity = [];
        $sql_a = $sql;

        $ref_unix = time() - ($activity_days * 86400);
        $ref_datetime = \DateTimeImmutable::createFromFormat('U', (string) $ref_unix);

        $sql_a['activity'] = $sql_map;
        $sql_a['activity']['where'][] = 't.created_at > ?';
        $sql_a['activity']['params'][] = $ref_datetime;
        $sql_a['activity']['types'][] = Types::DATETIME_IMMUTABLE;

        $activity_filter_code = trim($activity_filter_code);

        if ($activity_filter_code)
        {
            [$code_only_activity_filter_code] = explode(' ', $activity_filter_code);

            $activity_filter_user_id = $db->fetchOne('select id
                from ' . $pp->schema() . '.users
                where code = ?',
                [$code_only_activity_filter_code],
                [\PDO::PARAM_STR]);

            if ($activity_filter_user_id)
            {
                $sql_a['filter_from_user'] = $sql_map;
                $sql_a['filter_from_user']['where'][] = 't.id_from <> ?';
                $sql_a['filter_from_user']['params'][] = $activity_filter_user_id;
                $sql_a['filter_from_user']['types'][] = \PDO::PARAM_INT;
                $sql_a['filter_to_user'] = $sql_map;
                $sql_a['filter_to_user']['where'][] = 't.id_to <> ?';
                $sql_a['filter_to_user']['params'][] = $activity_filter_user_id;
                $sql_a['filter_to_user']['types'][] = \PDO::PARAM_INT;
            }
        }

        $sql_a_in = $sql_a;
        unset($sql_a_in['filter_from_user']);

        $sql_a_in_where = implode(' and ', array_merge(...array_column($sql_a_in, 'where')));
        $sql_a_in_params = array_merge(...array_column($sql_a_in, 'params'));
        $sql_a_in_types = array_merge(...array_column($sql_a_in, 'types'));

        $query_in = 'select sum(t.amount),
                count(t.id), t.id_to
            from ' . $pp->schema() . '.transactions t
            inner join ' . $pp->schema() . '.users u
                on t.id_to = u.id
            where ' . $sql_a_in_where . '
            group by t.id_to';

        $res = $db->executeQuery($query_in, $sql_a_in_params, $sql_a_in_types);

        while ($row = $res->fetchAssociative())
        {
            $activity[$row['id_to']] ??= [
                'trans'	    => ['total' => 0],
                'amount'    => ['total' => 0],
            ];

            $activity[$row['id_to']]['trans']['in'] = $row['count'];
            $activity[$row['id_to']]['amount']['in'] = $row['sum'];
            $activity[$row['id_to']]['trans']['total'] += $row['count'];
            $activity[$row['id_to']]['amount']['total'] += $row['sum'];
        }

        $sql_a_out = $sql_a;
        unset($sql_a_out['filter_to_user']);

        $sql_a_out_where = implode(' and ', array_merge(...array_column($sql_a_out, 'where')));
        $sql_a_out_params = array_merge(...array_column($sql_a_out, 'params'));
        $sql_a_out_types = array_merge(...array_column($sql_a_out, 'types'));

        $query_out = 'select sum(t.amount),
                count(t.id), t.id_from
            from ' . $pp->schema() . '.transactions t
            inner join ' . $pp->schema() . '.users u
                on t.id_to = u.id
            where ' . $sql_a_out_where . '
            group by t.id_from';

        $res = $db->executeQuery($query_out, $sql_a_out_params, $sql_a_out_types);

        while ($row = $res->fetchAssociative())
        {
            $activity[$row['id_from']] ??= [
                'trans'	    => ['total' => 0],
                'amount'    => ['total' => 0],
            ];

            $activity[$row['id_from']]['trans']['out'] = $row['count'];
            $activity[$row['id_from']]['amount']['out'] = $row['sum'];
            $activity[$row['id_from']]['trans']['total'] += $row['count'];
            $activity[$row['id_from']]['amount']['total'] += $row['sum'];
        }
    }

    $f_col = '<form method="get">';

    $hidden_ary = $query_params;
    unset($hidden_ary['sh']);

    if (count($hidden_ary))
    {
        $hidden_ary = http_build_query($hidden_ary, 'prefix', '&');
        $hidden_ary = urldecode($hidden_ary);
        $hidden_ary = explode('&', $hidden_ary);

        foreach ($hidden_ary as $hidden_key_value)
        {
            [$name, $value] = explode('=', $hidden_key_value);

            if (!isset($value) || $value === '')
            {
                continue;
            }

            $f_col .= '<input type="hidden" name="' . $name . '" value="' . $value . '">';
        }
    }

    $f_col .= '<div class="panel panel-info collapse" ';
    $f_col .= 'id="show_columns">';
    $f_col .= '<div class="panel-heading">';
    $f_col .= '<h2>Weergave kolommen</h2>';

    $f_col .= '<div class="row">';

    $fc1 = '';
    $fc2 = '';
    $fc3 = '';

    foreach ($columns as $group => $ary)
    {
        if ($group === 'p')
        {
            continue;
        }

        if ($group === 'c')
        {
            $fc2 .= '<h3>Contacten</h3>';
        }
        else if ($group === 'd')
        {
            $fc2 .= '<h3>Afstand</h3>';
            $fc2 .= '<p>Tussen eigen adres en adres van gebruiiker. ';
            $fc2 .= 'De kolom wordt niet getoond wanneer het eigen adres ';
            $fc2 .= 'niet ingesteld is.</p>';
        }
        else if ($group === 'mollie')
        {
            $fc2 .= '<h3>Mollie (EUR)</h3>';
            $fc2 .= '<p>Status van het laatste betaalverzoek.</p>';
        }
        else if ($group === 'a')
        {
            $fc3 .= '<h3>Transacties/activiteit</h3>';

            $fc3 .= '<div class="form-group">';
            $fc3 .= '<label for="p_activity_days" ';
            $fc3 .= 'class="control-label">';
            $fc3 .= 'In periode';
            $fc3 .= '</label>';
            $fc3 .= '<div class="input-group">';
            $fc3 .= '<span class="input-group-addon">';
            $fc3 .= 'dagen';
            $fc3 .= '</span>';
            $fc3 .= '<input type="number" ';
            $fc3 .= 'id="p_activity_days" ';
            $fc3 .= 'name="sh[p][a][days]" ';
            $fc3 .= 'value="';
            $fc3 .= $activity_days;
            $fc3 .= '" ';
            $fc3 .= 'size="4" min="1" class="form-control">';
            $fc3 .= '</div>';
            $fc3 .= '</div>';

            $typeahead_service->ini($pp)
                ->add('accounts', ['status' => 'active']);

            if (!$pp->is_guest())
            {
                $typeahead_service->add('accounts', ['status' => 'extern']);
            }

            if ($pp->is_admin())
            {
                $typeahead_service->add('accounts', ['status' => 'inactive'])
                    ->add('accounts', ['status' => 'ip'])
                    ->add('accounts', ['status' => 'im']);
            }

            $fc3 .= '<div class="form-group">';
            $fc3 .= '<label for="p_activity_filter_code" ';
            $fc3 .= 'class="control-label">';
            $fc3 .= 'Exclusief tegenpartij';
            $fc3 .= '</label>';
            $fc3 .= '<div class="input-group">';
            $fc3 .= '<span class="input-group-addon">';
            $fc3 .= '<i class="fa fa-user"></i>';
            $fc3 .= '</span>';
            $fc3 .= '<input type="text" ';
            $fc3 .= 'name="sh[p][a][code]" ';
            $fc3 .= 'id="p_activity_filter_code" ';
            $fc3 .= 'value="';
            $fc3 .= $activity_filter_code;
            $fc3 .= '" ';
            $fc3 .= 'placeholder="Account Code" ';
            $fc3 .= 'class="form-control" ';
            $fc3 .= 'data-typeahead="';

            $fc3 .= $typeahead_service->str([
                'filter'		=> 'accounts',
                'new_users_days'        => $new_users_days,
                'show_new_status'       => $show_new_status,
                'show_leaving_status'   => $show_leaving_status,
            ]);

            $fc3 .= '">';
            $fc3 .= '</div>';
            $fc3 .= '</div>';

            foreach ($ary as $a_type => $a_ary)
            {
                foreach($a_ary as $key => $lbl)
                {
                    $checkbox_name = 'sh[' . $group . '][' . $a_type . '][' . $key . ']';

                    $fc3 .= strtr(BulkCnst::TPL_CHECKBOX, [
                        '%name%'    => $checkbox_name,
                        '%attr%'    => isset($show_columns[$group][$a_type][$key]) ? ' checked' : '',
                        '%label%'   => $lbl,
                    ]);
                }
            }

            continue;
        }
        else if ($group === 'm')
        {
            $fc3 .= '<h3>Vraag en aanbod</h3>';
        }

        foreach ($ary as $key => $lbl)
        {
            $checkbox_name = 'sh[' . $group . '][' . $key . ']';

            $lbl_plus = '';

            if ($key === 'adr')
            {
                $lbl_plus .= ', split door teken: ';
                $lbl_plus .= '<input type="text" ';
                $lbl_plus .= 'name="sh[p][c][adr_split]" ';
                $lbl_plus .= 'size="1" value="';
                $lbl_plus .= $adr_split;
                $lbl_plus .= '">';
            }

            if ($key === 'balance_date')
            {
                $lbl_plus .= '<div class="input-group">';
                $lbl_plus .= '<span class="input-group-addon">';
                $lbl_plus .= '<i class="fa fa-calendar"></i>';
                $lbl_plus .= '</span>';
                $lbl_plus .= '<input type="text" ';
                $lbl_plus .= 'class="form-control" ';
                $lbl_plus .= 'name="sh[p][u][balance_date]" ';
                $lbl_plus .= 'data-provide="datepicker" ';
                $lbl_plus .= 'data-date-format="';
                $lbl_plus .= $date_format_service->datepicker_format($pp->schema());
                $lbl_plus .= '" ';
                $lbl_plus .= 'data-date-language="nl" ';
                $lbl_plus .= 'data-date-today-highlight="true" ';
                $lbl_plus .= 'data-date-autoclose="true" ';
                $lbl_plus .= 'data-date-enable-on-readonly="false" ';
                $lbl_plus .= 'data-date-end-date="0d" ';
                $lbl_plus .= 'data-date-orientation="bottom" ';
                $lbl_plus .= 'placeholder="';
                $lbl_plus .= $date_format_service->datepicker_placeholder($pp->schema());
                $lbl_plus .= '" ';
                $lbl_plus .= 'value="';
                $lbl_plus .= $balance_date;
                $lbl_plus .= '">';
                $lbl_plus .= '</div>';

                $columns['u']['balance_date'] = 'Saldo op ' . $balance_date;
            }

            $chckbx = strtr(BulkCnst::TPL_CHECKBOX, [
                '%name%'    => $checkbox_name,
                '%attr%'    => isset($show_columns[$group][$key]) ? ' checked' : '',
                '%label%'   => $lbl .  $lbl_plus,
            ]);

            switch ($group)
            {
                case 'u':
                    $fc1 .= $chckbx;
                break;
                case 'c':
                case 'd':
                case 'mollie':
                    $fc2 .= $chckbx;
                break;
                case 'm':
                case 'a':
                    $fc3 .= $chckbx;
                break;
            }
        }
    }

    if ($fc3 === '')
    {
        $f_col .= '<div class="col-md-6">';
        $f_col .= $fc1;
        $f_col .= '</div>';
        $f_col .= '<div class="col-md-6">';
        $f_col .= $fc2;
        $f_col .= '</div>';
    }
    else
    {
        $f_col .= '<div class="col-md-4">';
        $f_col .= $fc1;
        $f_col .= '</div>';
        $f_col .= '<div class="col-md-4">';
        $f_col .= $fc2;
        $f_col .= '</div>';
        $f_col .= '<div class="col-md-4">';
        $f_col .= $fc3;
        $f_col .= '</div>';
    }

    $f_col .= '</div>';
    $f_col .= '<div class="row">';
    $f_col .= '<div class="col-md-12">';
    $f_col .= '<input type="submit" ';
    $f_col .= 'class="btn btn-default" ';
    $f_col .= 'value="Pas weergave kolommen aan">';
    $f_col .= '</div>';
    $f_col .= '</div>';
    $f_col .= '</div>';
    $f_col .= '</div>';

    $f_col .= '</form>';

    $out = self::get_tab_selector(
        $params,
        $link_render,
        $item_access_service,
        $config_service,
        $pp,
        $vr
    );

    $out .= '<div class="panel panel-success printview">';
    $out .= '<div class="table-responsive">';

    $out .= '<table class="table table-bordered table-striped table-hover footable csv" ';
    $out .= 'data-filtering="true" data-filter-delay="0" ';
    $out .= 'data-filter="#q" data-filter-min="1" data-cascade="true" ';
    $out .= 'data-empty="Er zijn geen gebruikers ';
    $out .= 'volgens de selectiecriteria" ';
    $out .= 'data-sorting="true" ';
    $out .= 'data-filter-placeholder="Zoeken" ';
    $out .= 'data-filter-position="left"';

    if (count($ref_geo))
    {
        $out .= ' data-lat="' . $ref_geo['lat'] . '" ';
        $out .= 'data-lng="' . $ref_geo['lng'] . '"';
    }

    $out .= '>';
    $out .= '<thead>';

    $out .= '<tr>';

    $numeric_keys = [
        'balance'	    => true,
        'balance_date'	=> true,
    ];

    $date_keys = [
        'birthdate'      => true,
        'created_at'    => true,
        'last_edit_at'	=> true,
        'adate'			=> true,
        'last_login'	=> true,
    ];

    $link_user_keys = [
        'code'		=> true,
        'name'		=> true,
    ];

    foreach ($show_columns as $group => $ary)
    {
        if ($group === 'p')
        {
            continue;
        }
        else if ($group === 'a')
        {
            foreach ($ary as $a_key => $a_ary)
            {
                foreach ($a_ary as $key => $one)
                {
                    $out .= '<th data-type="numeric">';
                    $out .= $columns[$group][$a_key][$key];
                    $out .= '</th>';
                }
            }

            continue;
        }
        else if ($group === 'd')
        {
            if (count($ref_geo))
            {
                foreach($ary as $key => $one)
                {
                    $out .= '<th>';
                    $out .= $columns[$group][$key];
                    $out .= '</th>';
                }
            }

            continue;
        }
        else if ($group === 'mollie')
        {
            foreach($ary as $key => $one)
            {
                $out .= '<th>';
                $out .= $columns[$group][$key];
                $out .= '</th>';
            }
        }
        else if ($group === 'c')
        {
            $tpl = '<th data-hide="tablet, phone" data-sort-ignore="true">%1$s</th>';

            foreach ($ary as $key => $one)
            {
                if ($key == 'adr' && $adr_split != '')
                {
                    $out .= sprintf($tpl, 'Adres (1)');
                    $out .= sprintf($tpl, 'Adres (2)');
                    continue;
                }

                $out .= sprintf($tpl, $columns[$group][$key]);
            }

            continue;
        }
        else if ($group === 'u')
        {
            foreach ($ary as $key => $one)
            {
                $data_type =  isset($numeric_keys[$key]) ? ' data-type="numeric"' : '';
                $data_sort_initial = $key === 'code' ? ' data-sort-initial="true"' : '';

                $out .= '<th' . $data_type . $data_sort_initial . '>';
                $out .= $columns[$group][$key];
                $out .= '</th>';
            }

            continue;
        }
        else if ($group === 'm')
        {
            foreach ($ary as $key => $one)
            {
                $out .= '<th data-type="numeric">';
                $out .= $columns[$group][$key];
                $out .= '</th>';
            }

            continue;
        }
    }

    $out .= '</tr>';

    $out .= '</thead>';
    $out .= '<tbody>';

    $can_link = $pp->is_admin();

    foreach($users as $id => $u)
    {
        if (($pp->is_user() || $pp->is_guest())
            && ($u['status'] === 1 || $u['status'] === 2))
        {
            $can_link = true;
        }

        $row_stat = $u['status'];

        if ($status === 'new'
            || (isset($u['adate'])
            && $u['status'] === 1
            && $new_users_enabled
            && $item_access_service->is_visible($new_users_access_list)
            && $new_user_treshold->getTimestamp() < strtotime($u['adate'] . ' UTC')))
        {
            $row_stat = 3;
        }

        if ($status !== 'leaving'
            && $row_stat === 2
            && (!$leaving_users_enabled
                || !$item_access_service->is_visible($leaving_users_access_list)
        ))
        {
            $row_stat = 1;
        }

        $first = true;

        $out .= '<tr';

        if (isset(StatusCnst::CLASS_ARY[$row_stat]))
        {
            $out .= ' class="';
            $out .= StatusCnst::CLASS_ARY[$row_stat];
            $out .= '"';
        }

        $out .= ' data-balance="';
        $out .= $u['balance'];
        $out .= '">';

        if (isset($show_columns['u']))
        {
            foreach ($show_columns['u'] as $key => $one)
            {
                $out .= '<td';
                $out .= isset($date_keys[$key]) && isset($u[$key]) ? ' data-value="' . $u[$key] . '"' : '';
                $out .= '>';

                $td = '';

                if (isset($link_user_keys[$key]))
                {
                    if ($can_link)
                    {
                        $td .= $link_render->link_no_attr('users_show', $pp->ary(),
                            ['id' => $u['id'], 'status' => $status], $u[$key] ?: '**leeg**');
                    }
                    else
                    {
                        $td .= htmlspecialchars($u[$key], ENT_QUOTES);
                    }
                }
                else if (isset($date_keys[$key]))
                {
                    if (isset($u[$key]) && $u[$key])
                    {
                        $td .= $date_format_service->get($u[$key], 'day', $pp->schema());
                    }
                    else
                    {
                        $td .= '&nbsp;';
                    }
                }
                else if ($key === 'full_name')
                {
                    if ($item_access_service->is_visible($u['full_name_access']))
                    {
                        if ($can_link)
                        {
                            $td .= $link_render->link_no_attr('users_show', $pp->ary(),
                                ['id' => $u['id'], 'status' => $status], $u['full_name']);
                        }
                        else
                        {
                            $td .= htmlspecialchars($u['full_name'], ENT_QUOTES);
                        }
                    }
                    else
                    {
                        $td .= '<span class="btn btn-default">';
                        $td .= 'verborgen</span>';
                    }
                }
                else if ($key === 'role')
                {
                    $td .= RoleCnst::LABEL_ARY[$u['role']];
                }
                else
                {
                    $td .= htmlspecialchars((string) ($u[$key] ?? ''));
                }

                if ($pp->is_admin() && $first)
                {
                    $out .= strtr(BulkCnst::TPL_CHECKBOX_ITEM_2, [
                        '%id%'      => $id,
                        '%attr%'    => isset($selected_users[$id]) ? ' checked' : '',
                        '%label%'   => $td,
                    ]);

                    $first = false;
                }
                else
                {
                    $out .= $td;
                }

                $out .= '</td>';
            }
        }

        if (isset($show_columns['c']))
        {
            foreach ($show_columns['c'] as $key => $one)
            {
                $out .= '<td>';

                if ($key === 'adr' && $adr_split !== '')
                {
                    if (!isset($contacts[$id][$key]))
                    {
                        $out .= '&nbsp;</td><td>&nbsp;</td>';
                        continue;
                    }

                    [$adr_1, $adr_2] = explode(trim($adr_split), $contacts[$id]['adr'][0]['value']);

                    $out .= self::get_contacts_str($item_access_service, [[
                        'value'     => $adr_1,
                        'access'    => $contacts[$id]['adr'][0]['access']]],
                    'adr');

                    $out .= '</td><td>';

                    $out .= self::get_contacts_str($item_access_service, [[
                        'value'    => $adr_2,
                        'access'   => $contacts[$id]['adr'][0]['access']]],
                    'adr');
                }
                else if (isset($contacts[$id][$key]))
                {
                    $out .= self::get_contacts_str($item_access_service, $contacts[$id][$key], $key);
                }
                else
                {
                    $out .= '&nbsp;';
                }

                $out .= '</td>';
            }
        }

        if (isset($show_columns['d']) && count($ref_geo))
        {
            $out .= '<td data-value="5000000"';

            $adr_ary = $contacts[$id]['adr'][0] ?? [];

            if (isset($adr_ary['access']))
            {
                if ($item_access_service->is_visible($adr_ary['access']))
                {
                    if (count($adr_ary) && $adr_ary['value'])
                    {
                        $geo = $cache_service->get('geo_' . $adr_ary['value']);

                        if ($geo)
                        {
                            $out .= ' data-lat="';
                            $out .= $geo['lat'];
                            $out .= '" data-lng="';
                            $out .= $geo['lng'];
                            $out .= '"';
                        }
                    }

                    $out .= '><i class="fa fa-times"></i>';
                }
                else
                {
                    $out .= '><span class="btn btn-default">verborgen</span>';
                }
            }
            else
            {
                $out .= '><i class="fa fa-times"></i>';
            }

            $out .= '</td>';
        }

        if (isset($show_columns['mollie']))
        {
            foreach ($show_columns['mollie'] as $key => $one)
            {
                $out .= '<td>';

                if (isset($mollie_ary[$id]))
                {
                    $out .= '<span title="';
                    $out .= htmlspecialchars($mollie_ary[$id]['description'], ENT_QUOTES);
                    $out .= "\n";
                    $out .= 'EUR ' . strtr($mollie_ary[$id]['amount'], '.', ',');
                    $out .= "\n";
                    $out .= ' @';
                    $out .= $date_format_service->get($mollie_ary[$id]['created_at'], 'day', $pp->schema());
                    $out .= '" ';
                    $out .= 'class="label label-';

                    if ($mollie_ary[$id]['is_canceled'])
                    {
                        $out .= 'default">geannuleerd';
                    }
                    else if ($mollie_ary[$id]['is_paid'])
                    {
                        $out .= 'success">betaald';
                    }
                    else
                    {
                        $out .= 'warning">open';
                    }

                    $out . '</span>';
                }
                else
                {
                    $out .= '&nbsp;';
                }

                $out .= '</td>';
            }
        }

        if (isset($show_columns['m']))
        {
            foreach($show_columns['m'] as $key => $one)
            {
                $out .= '<td>';

                if (isset($msgs_count[$id][$key]))
                {
                    $out .= $link_render->link_no_attr($vr->get('messages'), $pp->ary(), [
                            'uid'   => $id,
                            'f'	    => [
                                ...['user' => $u['code'] . ' ' . $u['name']],
                                ...$message_type_filter[$key],
                            ],
                        ],
                        (string) $msgs_count[$id][$key]);
                }

                $out .= '</td>';
            }
        }

        if (isset($show_columns['a']))
        {
            $from_date = $date_format_service->get_from_unix(time() - ($activity_days * 86400), 'day', $pp->schema());

            foreach($show_columns['a'] as $a_key => $a_ary)
            {
                foreach ($a_ary as $key => $one)
                {
                    $out .= '<td>';

                    if (isset($activity[$id][$a_key][$key]))
                    {
                        if (isset($code_only_activity_filter_code))
                        {
                            $out .= $activity[$id][$a_key][$key];
                        }
                        else
                        {
                            $out .= $link_render->link_no_attr('transactions', $pp->ary(),
                                [
                                    'f' => [
                                        'from_account'	=> $key === 'in' ? '' : $u['code'] . ' ' . $u['name'],
                                        'to_account'	=> $key === 'out' ? '' : $u['code'] . ' ' . $u['name'],
                                        'account_logic'	=> $key === 'total' ? 'or' : 'and',
                                        'from_date' => $from_date,
                                    ],
                                ],
                                (string) $activity[$id][$a_key][$key]);
                        }
                    }

                    $out .= '</td>';
                }
            }
        }

        $out .= '</tr>';
    }

    $out .= '</tbody>';
    $out .= '</table>';
    $out .= '</div></div>';

    return $this->render('users/users_list.html.twig', [
      'columns_form_raw'  => $f_col,
      'filter_form'       => $filter_form->createView(),
      'row_count'         => count($users),
      'data_list_raw'     => $out,
      'bulk_email_form'   => $bulk_email_form?->createView(),
      'bulk_full_name_access_form' => $bulk_full_name_access_form?->createView(),
      'bulk_role_form' => $bulk_role_form?->createView(),
      'bulk_status_form'  => $bulk_status_form?->createView(),
      'bulk_comments_form'    => $bulk_comments_form?->createView(),
      'bulk_admin_comments_form' => $bulk_admin_comments_form?->createView(),
      'bulk_min_limit_form'     => $bulk_min_limit_form?->createView(),
      'bulk_max_limit_form'     => $bulk_max_limit_form?->createView(),
      'bulk_periodic_overview_en_form'     => $bulk_periodic_overview_en_form?->createView(),
    ]);
  }

  static public function get_status_def_ary(
    ConfigService $config_service,
    ItemAccessService $item_access_service,
    PageParamsService $pp
  ):array
  {
    $new_user_treshold = $config_service->get_new_user_treshold($pp->schema());

    $status_def_ary = [];

    $status_def_ary['active'] = [
      'lbl'	=> $pp->is_admin() ? 'Actief' : 'Alle',
      'sql'	=> [
        'where'     => ['u.status in (1, 2)'],
      ],
      'st'	=> [1, 2],
    ];

    if ($config_service->get_bool('users.new.enabled', $pp->schema()))
    {
      $new_users_access_pane = $config_service->get_str('users.new.access_pane', $pp->schema());

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

    if ($config_service->get_bool('users.leaving.enabled', $pp->schema()))
    {
      $leaving_users_access_pane = $config_service->get_str('users.leaving.access_pane', $pp->schema());

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

  public static function get_contacts_str(
    ItemAccessService $item_access_service,
    array $contacts,
    string $abbrev
  ):string
  {
    $ret = '';

    if (count($contacts))
    {
      end($contacts);
      $end = key($contacts);

      $tpl = '%1$s';

      if ($abbrev === 'mail')
      {
        $tpl = '<a href="mailto:%1$s">%1$s</a>';
      }
      else if ($abbrev === 'web')
      {
        $tpl = '<a href="%1$s">%1$s</a>';
      }

      foreach ($contacts as $key => $contact)
      {
        if ($item_access_service->is_visible($contact['access']))
        {
          $ret .= sprintf($tpl, htmlspecialchars($contact['value'] ?? '', ENT_QUOTES));

          if ($key === $end)
          {
            break;
          }

          $ret .= ',<br>';

          continue;
      }

        $ret .= '<span class="btn btn-default">';
        $ret .= 'verborgen</span>';
        $ret .= '<br>';
      }
    }
    else
    {
        $ret .= '&nbsp;';
    }

    return $ret;
  }

  public static function array_intersect_key_recursive(array $ary_1, array $ary_2)
  {
    $ary_1 = array_intersect_key($ary_1, $ary_2);

    foreach ($ary_1 as $key => &$val)
    {
      if (is_array($val))
      {
        $val = is_array($ary_2[$key]) ? self::array_intersect_key_recursive($val, $ary_2[$key]) : $val;
      }
    }

    return $ary_1;
  }
}
