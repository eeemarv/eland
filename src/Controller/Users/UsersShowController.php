<?php declare(strict_types=1);

namespace App\Controller\Users;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Command\Tags\TagsUsersCommand;
use App\Command\Users\UsersMailContactCommand;
use App\Controller\Contacts\ContactsUserShowInlineController;
use App\Email\UserPrivate\Copy\EmailUserPrivateCopyMessage;
use App\Email\UserPrivate\Message\EmailUserPrivateMessageMessage;
use App\Form\Type\MailContact\MailContactType;
use App\Form\Type\Tags\TagsUsersType;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Repository\ContactRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Service\ConfigService;
use App\Service\DistanceService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersShowController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/users/{id}/{status}',
    name: 'users_show',
    methods: ['GET', 'POST'],
    priority: 10,
    requirements: [
      'id'            => '%assert.id%',
      'status'        => '%assert.account_status%',
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.guest%',
    ],
    defaults: [
      'is_self'       => false,
      'status'        => 'active',
      'module'        => 'users',
    ],
  )]

  #[Route(
    '/{schema}/{role_short}/users/self',
    name: 'users_show_self',
    methods: ['GET'],
    priority: 10,
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.user%',
    ],
    defaults: [
      'id'            => 0,
      'is_self'       => true,
      'status'        => 'active',
      'module'        => 'users',
    ],
  )]

  public function __invoke(
    Request $request,
    string $status,
    int $id,
    bool $is_self,
    Db $db,
    ContactRepository $contact_repository,
    UserRepository $user_repository,
    TagRepository $tag_repository,
    AccountRender $account_render,
    ConfigService $config_service,
    ItemAccessService $item_access_service,
    LinkRender $link_render,
    DistanceService $distance_service,
    MessageBusInterface $bus,
    PageParamsService $pp,
    SessionUserService $su,
    ContactsUserShowInlineController $contacts_user_show_inline_controller,
    string $env_map_access_token,
    string $env_map_tiles_url
  ):Response
  {
    if (!$pp->is_admin()
        && !in_array($status, ['active', 'new', 'leaving']))
    {
      throw $this->createAccessDeniedException(
        'No access for this user status'
      );
    }

    if ($id === 0 && $is_self)
    {
      $id = $su->id();
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

    $tdays = $request->query->get('tdays', '365');

    $user = $user_repository->get_with_page_data(
      id: $id,
      status: $status,
      schema: $pp->schema_o(),
    );

    if ($user === false)
    {
      throw $this->createNotFoundException(
        'The user with id ' . $id . ' not found'
      );
    }

    if (!$pp->is_admin()
      && !in_array($user['status'], [1, 2]))
    {
      throw $this->createAccessDeniedException(
        'You have no access to this user account.'
      );
    }

    $new_user_treshold = $config_service->get_new_user_treshold(
      schema: $pp->schema_o(),
    );

    $is_new = false;

    if (isset($user['adate'])
      && $new_user_treshold->getTimestamp() < strtotime($user['adate'] . ' UTC'))
    {
      $is_new = true;
    }

    $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);

    $full_name_edit_en = $config_service->get_bool(
      config_id: 'users.fields.full_name.self_edit',
      schema: $pp->schema_o(),
    );

    if (!$su->is_owner($id))
    {
      $full_name_edit_en = false;
    }

    if ($pp->is_admin())
    {
      $full_name_edit_en = true;
    }

    $tags_enabled = $config_service->get_bool(
      config_id: 'users.tags.enabled',
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
    $min_limit = $user['min_limit'];
    $max_limit = $user['max_limit'];
    $balance = $user['balance'];

    $system_min_limit = $config_service->get_int(
      config_id: 'accounts.limits.global.min',
      schema: $pp->schema_o(),
    );
    $system_max_limit = $config_service->get_int(
      config_id: 'accounts.limits.global.max',
      schema: $pp->schema_o(),
    );
    $currency = $config_service->get_str(
      config_id: 'transactions.currency.name',
      schema: $pp->schema_o(),
    );

    /***
     *
     *
     */

    $tags_form = null;
    $render_tags = false;

    if ($pp->is_admin() && $tags_enabled)
    {
      $tags_command = new TagsUsersCommand();

      $tags_command->tags = $tag_repository->get_id_ary_for_user(
        user_id: $id,
        schema: $pp->schema_o(),
        active_only: true,
      );

      $tags_form = $this->createForm(
        type: TagsUsersType::class,
        data: $tags_command,
      );

      $tags_form->handleRequest($request);

      if ($tags_form->isSubmitted() &&
        $tags_form->isValid())
      {
        $tags_command = $tags_form->getData();

        $count_changes = $tag_repository->update_for_user(
          command: $tags_command,
          user_id: $id,
          created_by: $su->id(),
          schema: $pp->schema_o(),
        );

        //$response_cache->clear_cache($pp->schema());

        if ($count_changes)
        {
          $this->addFlash(
            type: 'success',
            message: [
              'key' => 'users_show.tags.flash.success',
              'params'  => [
                'count' => $count_changes,
              ]
            ]
          );
        }
        else
        {
          $this->addFlash(
            type: 'warning',
            message: [
              'key' => 'flash.no_change',
            ]);
        }

        if ($is_self)
        {
          return $this->redirectToRoute('users_show_self', $pp->ary());
        }

        return $this->redirectToRoute('users_show', [...$pp->ary(),
          'id'    => $id,
        ]);
      }

      $render_tags = true;
    }

    /**
     * Mail form
    */

    $mail_command = new UsersMailContactCommand();

    $mail_form = $this->createForm(
      type: MailContactType::class,
      data: $mail_command,
      options: [
        'to_user_id'  => $id,
      ],
    );

    $mail_form->handleRequest($request);

    if ($mail_form->isSubmitted()
      && $mail_form->isValid())
    {
      $mail_command = $mail_form->getData();

      $m_message = new EmailUserPrivateMessageMessage(
        sender_id: $su->id(),
        sender_schema: $su->schema_o(),
        message: $mail_command->message,
        user_id: $id,
        schema: $pp->schema_o(),
      );
      $bus->dispatch($m_message);

      if ($mail_command->cc)
      {
        $m_copy = new EmailUserPrivateCopyMessage(
          sender_id: $su->id(),
          sender_schema: $su->schema_o(),
          message: $mail_command->message,
          user_id: $id,
          schema: $pp->schema_o(),
        );
        $bus->dispatch($m_copy);
      }

      $this->addFlash(
        type: 'success',
        message: [
          'key' => 'mail_contact.flash.success',
          'params'  => [
            'user'  => $user['name'],
          ]
        ],
      );

      return $this->redirectToRoute('users_show', [
        ...$pp->ary(),
        'id' => $id,
        'status'  => $status,
      ]);
    }

    /***
     *
     *
     *
     */

    $count_messages = $user['message_count'];
    $count_transactions = $user['transaction_count'];

    $params['status'] = $status;

    $intersystem_missing = false;

    if ($pp->is_admin()
        && $user['role'] === 'guest'
        && $config_service->get_intersystem_en(schema: $pp->schema_o()))
    {
        $intersystem_id = $db->fetchOne('select id
            from ' . $pp->schema() . '.letsgroups
            where localletscode = ?',
            [$user['code']],
            [Types::STRING]);

        if (!$intersystem_id)
        {
            $intersystem_missing = true;
        }
    }
    else
    {
        $intersystem_id = 0;
    }

    $contacts_response = $contacts_user_show_inline_controller(
      $user['id'],
      $contact_repository,
      $item_access_service,
      $link_render,
      $pp,
      $su,
      $distance_service,
      $account_render,
      $env_map_access_token,
      $env_map_tiles_url,
    );

    $contacts_content = $contacts_response->getContent();

    error_log(json_encode($user));

    return $this->render('users/users_show.html.twig', [
      'user'      => $user,
      'user_contacts_table_raw' => $contacts_content,
      'id'        => $id,
      'status'    => $status,
      'is_self'   => $is_self,
      'full_name_edit_en'     => $full_name_edit_en,
      'prev_id'     => $user['prev_id'],
      'next_id'     => $user['next_id'],
      'last_login'  => $user['last_login'],
      'min_limit'   => $user['min_limit'],
      'max_limit'   => $user['max_limit'],
      'balance'     => $user['balance'],
      'contacts'    => $user['contacts'],
      'transaction_count'   => $user['transaction_count'],
      'message_count'       => $user['message_count'],
      'count_transactions'    => $count_transactions,
      'count_messages'        => $count_messages,
      'is_intersystem'        => $is_intersystem,
      'is_new'                => $is_new,
      'tags_form'             => $tags_form,
      'render_tags'           => $render_tags,
      'tdays'                 => $tdays,
      'mail_form'             => $mail_form,
      'intersystem_missing'   => $intersystem_missing,
      'intersystem_id'        => $intersystem_id,
    ]);
  }

  private function get_dd(string $str):string
  {
    $out =  '<dd>';
    $out .=  $str ? htmlspecialchars($str, ENT_QUOTES) : '<span class="fa fa-times"></span>';
    $out .=  '</dd>';
    return $out;
  }
}
