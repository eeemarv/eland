<?php declare(strict_types=1);

namespace App\Controller\RegisterForm;

use App\Email\RegisterForm\Admin\EmailRegisterFormAdminMessage;
use App\Email\RegisterForm\Confirm\EmailRegisterFormConfirmMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\EmailSentRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

#[AsController]
class RegisterFormConfirmController extends AbstractController
{
  #[Route(
    '/{system}/register/{confirm_token}',
    name: 'register_form_confirm',
    methods: ['GET'],
    priority: 30,
    requirements: [
      'confirm_token' => '%uuid_base58%',
      'system'        => '%assert.system%',
    ],
    defaults: [
      'module'        => 'register_form',
    ],
  )]

  public function __invoke(
    string $confirm_token,
    Db $db,
    ConfigService $config_service,
    EmailSentRepository $email_sent_repository,
    MessageBusInterface $bus,
    PageParamsService $pp
  ):Response
  {
    if (!$config_service->get_bool('register_form.enabled', $pp->schema()))
    {
      throw $this->createNotFoundException('Register form not enabled.');
    }

    $uuid_confirm_token = Uuid::fromBase58($confirm_token);

    $is_not_found = false;
    $is_expired = false;
    $is_already_confirmed = false;
    $success = false;

    $postcode_enabled = $config_service->get_bool('users.fields.postcode.enabled', $pp->schema());

    $record = $email_sent_repository->get_with_confirm_token(
      confirm_token: $uuid_confirm_token,
      minutes_exp: 60,
      schema: $pp->schema_o(),
    );

    if ($record === false)
    {
      $is_not_found = true;
    }
    else if ($record['message_class'] !== EmailRegisterFormConfirmMessage::class)
    {
      throw $this->createNotFoundException();
    }
    else if ($record['is_confirmed'])
    {
      $is_already_confirmed = true;
    }
    else if ($record['is_expired'])
    {
      $is_expired = true;
    }
    else
    {
      $success = true;

      $data = $record['confirm_data'];

      /**
       * Insert user data - start
       */

      for ($i = 0; $i < 20; $i++)
      {
        $name = $data['first_name'];

        if ($i)
        {
          $name .= ' ';

          if ($i < strlen($data['last_name']))
          {
            $name .= substr($data['last_name'], 0, $i);
          }
          else
          {
            $name .= substr(hash('sha512', $pp->schema() . time() . mt_rand(0, 100000)), 0, 4);
          }
        }

        $fetched_name = $db->fetchOne('select name
          from ' . $pp->schema() . '.users
          where name = ?',
          [$name], [Types::STRING]);

        if ($fetched_name === false)
        {
          break;
        }
      }

      $user = [
        'name'			            => $name,
        'full_name'		          => $data['full_name'],
        'status'		            => 5,
        'role'	                => 'user',
        'periodic_overview_en'	=> 't',
      ];

      if (isset($data['postcode'])
        && $postcode_enabled)
      {
        $user['postcode'] = $data['postcode'];
      }

      $db->beginTransaction();

      try
      {
        $db->insert($pp->schema() . '.users', $user);

        $user_id = (int) $db->lastInsertId($pp->schema() . '.users_id_seq');

        $tc = [];

        $stmt = $db->prepare('select abbrev, id
          from ' . $pp->schema() . '.type_contact');

        $res = $stmt->executeQuery();

        while($row = $res->fetchAssociative())
        {
          $tc[$row['abbrev']] = $row['id'];
        }

        $data['email'] = strtolower($data['email']);

        $mail = [
          'user_id'			=> $user_id,
          'access'      => 'admin',
          'value'				=> $data['email'],
          'id_type_contact'	=> $tc['mail'],
        ];

        $db->insert($pp->schema() . '.contact', $mail);

        if (isset($data['mobile']) || isset($data['phone']))
        {
          if (isset($data['mobile']) && $data['mobile'])
          {
            $gsm = [
              'user_id'			=> $user_id,
              'access'      => 'admin',
              'value'				=> $data['mobile'],
              'id_type_contact'	=> $tc['gsm'],
            ];

            $db->insert($pp->schema() . '.contact', $gsm);
          }

          if (isset($data['phone']) && $data['phone'])
          {
            $tel = [
              'user_id'			    => $user_id,
              'access'          => 'admin',
              'value'				    => $data['phone'],
              'id_type_contact'	=> $tc['tel'],
            ];

            $db->insert($pp->schema() . '.contact', $tel);
          }
        }
        $db->commit();
      }
      catch (\Exception $e)
      {
        $db->rollback();
        throw $e;
      }

      /**
       * Insert user data - end
       */

      $email_sent_repository->set_confirmed(
        confirm_token: $uuid_confirm_token,
        schema: $pp->schema_o(),
      );

      $m_contact = new EmailRegisterFormAdminMessage(
        user_id: $user_id,
        schema: $pp->schema_o(),
      );
      $bus->dispatch($m_contact);
    }

    return $this->render('register_form/register_form_confirm.html.twig', [
      'is_not_found'  => $is_not_found,
      'is_already_confirmed'  => $is_already_confirmed,
      'is_expired'    => $is_expired,
      'success'       => $success,
      'confirmed_at'  => $record['confirmed_at'] ?? null,
    ]);
  }
}
