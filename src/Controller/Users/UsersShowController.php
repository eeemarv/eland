<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Cnst\BulkCnst;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Cnst\StatusCnst;
use App\Cnst\RoleCnst;
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
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\DistanceService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\MailAddrUserService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\VarRouteService;
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
    AssetsService $assets_service,
    ConfigService $config_service,
    FormTokenService $form_token_service,
    ItemAccessService $item_access_service,
    LinkRender $link_render,
    MailAddrUserService $mail_addr_user_service,
    DateFormatService $date_format_service,
    DistanceService $distance_service,
    MessageBusInterface $bus,
    PageParamsService $pp,
    SessionUserService $su,
    VarRouteService $vr,
    ContactsUserShowInlineController $contacts_user_show_inline_controller,
    string $env_s3_url,
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

    // process mail form

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

    $last_login = $user['last_login'];

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
        $env_map_tiles_url
    );

    $contacts_content = $contacts_response->getContent();

    $out = '<div class="row">';
    $out .= '<div class="col-md-6">';

    $out .= '<div class="panel panel-default">';
    $out .= '<div class="panel-body text-center ';
    $out .= 'center-block img-upload" id="img_user">';

    $show_img = $user['image_file'] ? true : false;

    $user_img = $show_img ? '' : ' style="display:none;"';
    $no_user_img = $show_img ? ' style="display:none;"' : '';

    $out .= '<img id="img"';
    $out .= $user_img;
    $out .= ' class="img-rounded img-responsive center-block w-100" ';
    $out .= 'src="';

    if ($user['image_file'])
    {
        $out .= $env_s3_url . $user['image_file'];
    }
    else
    {
        $out .= $assets_service->get('1.gif');
    }

    $out .= '" ';
    $out .= 'data-base-url="' . $env_s3_url . '">';

    $out .= '<div id="no_img"';
    $out .= $no_user_img;
    $out .= '>';
    $out .= '<i class="fa fa-user fa-5x text-muted"></i>';
    $out .= '<br>Geen profielfoto/afbeelding</div>';

    $out .= '</div>';

    if ($pp->is_admin() || $su->is_owner($id))
    {
        $btn_del_attr = ['id'	=> 'btn_remove'];

        if (!$user['image_file'])
        {
            $btn_del_attr['style'] = 'display:none;';
        }

        $out .= '<div class="panel-footer">';
        $out .= '<span class="btn btn-success btn-lg btn-block fileinput-button">';
        $out .= '<i class="fa fa-plus" id="img_plus"></i> Afbeelding opladen';
        $out .= '<input type="file" name="image" ';
        $out .= 'data-url="';

        if ($pp->is_admin())
        {
            $out .= $link_render->context_path('users_image_upload', $pp->ary(),
                ['id' => $id]);
        }
        else
        {
            $out .= $link_render->context_path('users_image_upload_self', $pp->ary(), []);
        }

        $out .= '" data-image-crop data-fileupload ';
        $out .= 'data-message-file-type-not-allowed="Bestandstype is niet toegelaten." ';
        $out .= 'data-message-max-file-size="Het bestand is te groot." ';
        $out .= 'data-message-min-file-size="Het bestand is te klein." ';
        $out .= 'data-message-uploaded-bytes="Het bestand is te groot." ';
        $out .= '></span>';

        $out .= '<p class="text-warning">';
        $out .= 'Toegestane formaten: jpg/jpeg, png, webp, gif, svg. ';
        $out .= 'Je kan ook een afbeelding hierheen verslepen.</p>';

        if ($pp->is_admin())
        {
            $out .= $link_render->link_fa('users_image_del', $pp->ary(),
                ['id' => $id], 'Afbeelding verwijderen', [
                    ...$btn_del_attr,
                    'class' => 'btn btn-danger btn-lg btn-block',
                ],
                'times');
        }
        else
        {
            $out .= $link_render->link_fa('users_image_del_self', $pp->ary(),
                [], 'Afbeelding verwijderen', [
                    ...$btn_del_attr,
                    'class' => 'btn btn-danger btn-lg btn-block',
                ],
                'times');
        }

        $out .= '</div>';
    }

    $out .= '</div></div>';

    $out .= '<div class="col-md-6">';

    $out .= '<div class="panel panel-default printview">';
    $out .= '<div class="panel-heading">';
    $out .= '<dl>';

    if ($full_name_enabled)
    {
        $full_name_access = $user['full_name_access'] ?? 'admin';

        $out .= '<dt>';
        $out .= 'Volledige naam';
        $out .= '</dt>';

        if ($pp->is_admin()
            || $su->is_owner($id)
            || $item_access_service->is_visible($full_name_access))
        {
            $out .= $this->get_dd($user['full_name'] ?? '');
        }
        else
        {
            $out .= '<dd>';
            $out .= '<span class="btn btn-default">';
            $out .= 'verborgen</span>';
            $out .= '</dd>';
        }

        if ($pp->is_admin() || $su->is_owner($id))
        {
            $out .= '<dt>';
            $out .= 'Zichtbaarheid Volledige Naam';
            $out .= '</dt>';
            $out .= '<dd>';
            $out .= $item_access_service->get_label($full_name_access);
            $out .= '</dd>';
        }
    }

    if ($postcode_enabled)
    {
        $out .= '<dt>';
        $out .= 'Postcode';
        $out .= '</dt>';
        $out .= $this->get_dd($user['postcode'] ?? '');
    }

    if ($birthdate_enabled)
    {
        if ($pp->is_admin() || $su->is_owner($id))
        {
            $out .= '<dt>';
            $out .= 'Geboortedatum';
            $out .= '</dt>';

            if (isset($user['birthdate']))
            {
                $out .= $date_format_service->get($user['birthdate'], 'day', $pp->schema());
            }
            else
            {
                $out .= '<dd><i class="fa fa-times"></i></dd>';
            }
        }
    }

    if ($hobbies_enabled)
    {
        $out .= '<dt>';
        $out .= 'Hobbies / Interesses';
        $out .= '</dt>';
        $out .= $this->get_dd($user['hobbies'] ?? '');
    }

    if ($comments_enabled)
    {
        $out .= '<dt>';
        $out .= 'Commentaar';
        $out .= '</dt>';
        $out .= $this->get_dd($user['comments'] ?? '');
    }

    if ($pp->is_admin())
    {
        $out .= '<dt>';
        $out .= 'Tijdstip aanmaak';
        $out .= '</dt>';

        $out .= $this->get_dd($date_format_service->get($user['created_at'], 'min', $pp->schema()));

        $out .= '<dt>';
        $out .= 'Tijdstip activering';
        $out .= '</dt>';

        if (isset($user['adate']))
        {
            $out .= $this->get_dd($date_format_service->get($user['adate'], 'min', $pp->schema()));
        }
        else
        {
            $out .= '<dd><i class="fa fa-times"></i></dd>';
        }

        $out .= '<dt>';
        $out .= 'Laatste login';
        $out .= '</dt>';

        if (isset($last_login))
        {
            $out .= $this->get_dd($date_format_service->get($last_login, 'min', $pp->schema()));
        }
        else
        {
            $out .= '<dd><i class="fa fa-times"></i></dd>';
        }

        $out .= '<dt>';
        $out .= 'Rechten / rol';
        $out .= '</dt>';
        $out .= $this->get_dd(RoleCnst::LABEL_ARY[$user['role']]);

        $out .= '<dt>';
        $out .= 'Status';
        $out .= '</dt>';
        $out .= $this->get_dd(StatusCnst::LABEL_ARY[$user['status']]);

        if ($admin_comments_enabled)
        {
            $out .= '<dt>';
            $out .= 'Commentaar van de admin';
            $out .= '</dt>';
            $out .= $this->get_dd($user['admin_comments'] ?? '');
        }
    }

    if ($transactions_enabled)
    {
        $out .= '<dt>Saldo</dt>';
        $out .= '<dd>';
        $out .= '<span class="label label-info">';
        $out .= $balance;
        $out .= '</span>&nbsp;';
        $out .= $currency;
        $out .= '</dd>';

        if ($limits_enabled)
        {
            $out .= '<dt>Minimum limiet</dt>';
            $out .= '<dd>';

            if (isset($min_limit))
            {
                $out .= '<span class="label label-danger">';
                $out .= $min_limit;
                $out .= '</span>&nbsp;';
                $out .= $currency;
            }
            else if (isset($system_min_limit))
            {
                $out .= '<span class="label label-default">';
                $out .= $system_min_limit;
                $out .= '</span>&nbsp;';
                $out .= $currency;
                $out .= ' (Minimum Systeemslimiet)';
            }
            else
            {
                $out .= '<i class="fa fa-times"></i>';
            }

            $out .= '</dd>';

            $out .= '<dt>Maximum limiet</dt>';
            $out .= '<dd>';

            if (isset($max_limit))
            {
                $out .= '<span class="label label-success">';
                $out .= $max_limit;
                $out .= '</span>&nbsp;';
                $out .= $currency;
            }
            else if (isset($system_max_limit))
            {
                $out .= '<span class="label label-default">';
                $out .= $system_max_limit;
                $out .= '</span>&nbsp;';
                $out .= $currency;
                $out .= ' (Maximum Systeemslimiet)';
            }
            else
            {
                $out .= '<i class="fa fa-times"></i>';
            }

            $out .= '</dd>';
        }
    }

    if ($periodic_mail_enabled
        && ($pp->is_admin() || $su->is_owner($id)))
    {
        $out .= '<dt>';
        $out .= 'Periodieke Overzichts E-mail';
        $out .= '</dt>';
        $out .= $user['periodic_overview_en'] ? 'Aan' : 'Uit';
        $out .= '</dl>';
    }

    $out .= '</div></div></div></div>';


    if (!$is_self)
    {
      /*
        $out .= self::get_mail_form(
            $id,
            $user_mail_content,
            $user_mail_cc,
            $account_render,
            $form_token_service,
            $mail_addr_user_service,
            $pp,
            $su
        );
      */
    }

    $out .= $contacts_content;

    if ($transactions_enabled)
    {
        $out .= '<div class="row">';
        $out .= '<div class="col-md-12">';

        $out .= '<h3>Huidig saldo: <span class="label label-info">';
        $out .= $balance;
        $out .= '</span> ';
        $out .= $currency;
        $out .= '</h3>';
        $out .= '</div></div>';

        $out .= '<div class="row print-hide">';
        $out .= '<div class="col-md-6">';
        $out .= '<div id="chartdiv" data-height="480px" data-width="960px" ';

        $out .= 'data-transactions-plot-user="';
        $out .= htmlspecialchars($link_render->context_path('transactions_plot_user',
            $pp->ary(), ['user_id' => $id, 'days' => $tdays]));

        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-md-6">';
        $out .= '<div id="donutdiv" data-height="480px" ';
        $out .= 'data-width="960px"></div>';
        $out .= '<h4>Interacties laatste jaar</h4>';
        $out .= '</div>';
        $out .= '</div>';
    }

    if (!$is_self && ($messages_enabled || $transactions_enabled))
    {
        $out .= '<div class="row">';
        $out .= '<div class="col-md-12">';

        $out .= '<div class="panel panel-default">';
        $out .= '<div class="panel-body">';

        $account_str = $account_render->str($id, $pp->schema());

        $attr_link_messages = $attr_link_transactions = [
            'class'     => 'btn btn-default btn-lg btn-block',
            'disabled'  => 'disabled',
        ];

        if ($count_messages)
        {
            unset($attr_link_messages['disabled']);
        }

        if ($count_transactions)
        {
            unset($attr_link_transactions['disabled']);
        }

        if ($messages_enabled)
        {
            $out .= $link_render->link_fa($vr->get('messages'),
                $pp->ary(),
                ['uid' => $id],
                'Vraag en aanbod van ' . $account_str .
                ' (' . $count_messages . ')',
                $attr_link_messages,
                'newspaper-o');
        }

        if ($transactions_enabled)
        {
            $out .= $link_render->link_fa('transactions',
                $pp->ary(),
                ['uid' => $id],
                'Transacties van ' . $account_str .
                ' (' . $count_transactions . ')',
                $attr_link_transactions,
                'exchange');
        }

        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';
    }

    error_log(json_encode($user));

    return $this->render('users/users_show.html.twig', [
      'user'      => $user,
      'content'   => $out,
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

  public static function get_mail_form(
    int $user_id,
    string $user_mail_content,
    bool $user_mail_cc,
    AccountRender $account_render,
    FormTokenService $form_token_service,
    MailAddrUserService $mail_addr_user_service,
    PageParamsService $pp,
    SessionUserService $su
  ):string
  {
    $mail_from = $mail_addr_user_service->get($su->id(), $su->schema());
    $mail_to = $mail_addr_user_service->get($user_id, $pp->schema());

    $user_mail_disabled = true;

    if ($su->is_master())
    {
      $placeholder = 'Het master account kan geen berichten versturen.';
    }
    else if ($su->is_owner($user_id))
    {
      $placeholder = 'Je kan geen E-mail berichten naar jezelf verzenden.';
    }
    else if (!count($mail_to))
    {
        $placeholder = 'Er is geen E-mail adres bekend van deze gebruiker.';
    }
    else if (!count($mail_from))
    {
        $placeholder = 'Om het E-mail formulier te gebruiken moet een E-mail adres ingesteld zijn voor je eigen Account.';
    }
    else
    {
        $placeholder = '';
        $user_mail_disabled = false;
    }

    $out = '<h3><i class="fa fa-envelop-o"></i> ';
    $out .= 'Stuur een bericht naar ';
    $out .=  $account_render->link($user_id, $pp->ary());
    $out .= '</h3>';
    $out .= '<div class="panel panel-info">';
    $out .= '<div class="panel-heading">';

    $out .= '<form method="post"">';

    $out .= '<div class="form-group">';
    $out .= '<textarea name="user_mail_content" rows="6" placeholder="';
    $out .= $placeholder . '" ';
    $out .= 'class="form-control" required';
    $out .= $user_mail_disabled ? ' disabled' : '';
    $out .= '>';
    $out .= $user_mail_content;
    $out .= '</textarea>';
    $out .= '</div>';

    $user_mail_cc_attr = $user_mail_cc ? ' checked' : '';
    $user_mail_cc_attr .= $user_mail_disabled ? ' disabled' : '';

    $out .= strtr(BulkCnst::TPL_CHECKBOX, [
        '%name%'        => 'user_mail_cc',
        '%label%'       => 'Stuur een kopie naar mijzelf',
        '%attr%'        => $user_mail_cc_attr,
    ]);

    $out .= $form_token_service->get_hidden_input();
    $out .= '<input type="submit" name="user_mail_submit" ';
    $out .= 'value="Versturen" class="btn btn-info btn-lg"';
    $out .= $user_mail_disabled ? ' disabled' : '';
    $out .= '>';

    $out .= '</form>';

    $out .= '</div>';
    $out .= '</div>';

    return $out;
  }
}
