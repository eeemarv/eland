<?php declare(strict_types=1);

namespace App\Controller\Users;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Command\Users\UsersMailContactCommand;
use App\Email\UserPrivate\Copy\EmailUserPrivateCopyMessage;
use App\Email\UserPrivate\Message\EmailUserPrivateMessageMessage;
use App\Form\Type\MailContact\MailContactType;
use App\Repository\UserRepository;
use App\Service\ConfigService;
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
    UserRepository $user_repository,
    ItemAccessService $item_access_service,
    ConfigService $config_service,
    MessageBusInterface $bus,
    PageParamsService $pp,
    SessionUserService $su,
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

    $tdays = $request->query->get('tdays', '365');

    $user = $user_repository->get_with_page_data(
      id: $id,
      status: $status,
      current_user_id: $su->id(),
      current_user_schema: $su->schema_o(),
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

    if (isset($user['activated_at'])
      && $new_user_treshold->getTimestamp() < strtotime($user['activated_at'] . ' UTC'))
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

    $contacts = $user['contacts'];

    $map_markers = [];

    foreach ($contacts as $c)
    {
      if ($c['abbrev'] !== 'adr')
      {
        continue;
      }

      if (!$item_access_service->is_visible($c['access']))
      {
        continue;
      }

      if (!isset($c['latitude']) || !isset($c['longitude']))
      {
        continue;
      }

      $map_markers[] = [
        'lat' => $c['latitude'],
        'lng' => $c['longitude'],
        'distance'  => $c['distance'],
        'address'  => $c['value'],
      ];
    }

    return $this->render('users/users_show.html.twig', [
      'user'      => $user,
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
      'contacts'    => $contacts,
      'map_markers' => $map_markers,
      'transaction_count'   => $user['transaction_count'],
      'message_count'       => $user['message_count'],
      'tags'                => $user['tags'],
      'count_transactions'    => $count_transactions,
      'count_messages'        => $count_messages,
      'is_intersystem'        => $is_intersystem,
      'is_new'                => $is_new,
      'tdays'                 => $tdays,
      'mail_form'             => $mail_form,
      'intersystem_missing'   => $intersystem_missing,
      'intersystem_id'        => $intersystem_id,
    ]);
  }
}
