<?php declare(strict_types=1);

namespace App\Controller\Index;

use App\Email\Index\Contact\EmailIndexContactMessage;
use App\Email\Index\ContactSuccess\EmailIndexContactSuccessMessage;
use App\Repository\EmailSentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

#[AsController]
class IndexContactConfirmController extends AbstractController
{
  #[Route(
    '/contact/{confirm_token}',
    name: 'index_contact_confirm',
    methods: ['GET'],
    requirements: [
      'confirm_token' => '%uuid_base58%',
    ]
  )]

  public function __invoke(
    string $confirm_token,
    EmailSentRepository $email_sent_repository,
    MessageBusInterface $bus,
  ):Response
  {
    $uuid_confirm_token = Uuid::fromBase58($confirm_token);

    $is_not_found = false;
    $is_expired = false;
    $is_already_confirmed = false;
    $success = false;

    $record = $email_sent_repository->get_with_confirm_token(
      confirm_token: $uuid_confirm_token,
      minutes_exp: 60,
      schema: null,
    );

    if ($record === false)
    {
      $is_not_found = true;
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

      $email_sent_repository->set_confirmed(
        confirm_token: $uuid_confirm_token,
        schema: null
      );

      $data = $record['confirm_data'];

      $sender_email_address = new Address($data['email']);

      $m_contact = new EmailIndexContactMessage(
        reply_to: $sender_email_address,
        message: $data['message'],
        agent: $data['agent'],
        ip: $data['ip'],
      );
      $bus->dispatch($m_contact);

      $m_success = new EmailIndexContactSuccessMessage(
        to: $sender_email_address,
        message: $data['message']
      );
      $bus->dispatch($m_success);
    }

    return $this->render('index/contact_confirm.html.twig', [
      'is_not_found'  => $is_not_found,
      'is_already_confirmed'  => $is_already_confirmed,
      'is_expired'    => $is_expired,
      'success'       => $success,
      'confirmed_at'  => $record['confirmed_at'] ?? null,
    ]);
  }
}
