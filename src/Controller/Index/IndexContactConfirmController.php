<?php declare(strict_types=1);

namespace App\Controller\Index;

use App\Email\Index\Contact\EmailIndexContactMessage;
use App\Email\Index\ContactSuccess\EmailIndexContactSuccessMessage;
use App\Service\DataTokenService;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class IndexContactConfirmController extends AbstractController
{
  #[Route(
    '/contact/{token}',
    name: 'index_contact_confirm',
    methods: ['GET'],
    requirements: [
        'token'         => '%assert.token%',
    ]
  )]

  public function __invoke(
    string $token,
    DataTokenService $data_token_service,
    //MailerInterface $mailer,
    MessageBusInterface $bus,
  ):Response
  {
    $data = $data_token_service->retrieve($token, 'index_contact_form', null);

    if (!$data)
    {
      $this->addFlash('alert', [
        'type'      => 'error',
        'message'   => 'Ongeldig of verlopen token.',
      ]);
      return $this->redirectToRoute('index_contact');
    }

    $data_token_service->del($token, 'index_contact_form', null);

    $sender_email_address = new Address($data['email_address']);

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


    /**
    $email = new TemplatedEmail();
    $email->from($from_email_address);
    $email->to($hoster_email_address);
    $email->subject('Bericht van eLAND contactformulier');
    $email->htmlTemplate('@email/index/index_contact.html.twig');
    $email->context($data);
    $email->replyTo($sender_email_address);
    $mailer->send($email);

    $email_cc = new TemplatedEmail();
    $email_cc->from($from_email_address);
    $email_cc->to($sender_email_address);
    $email_cc->subject('Kopie van je bericht');
    $email_cc->htmlTemplate('@email/index/index_contact_success.html.twig');
    $email_cc->context($data);
    $mailer->send($email_cc);
*/

    return $this->render('index/contact_confirm.html.twig');
  }
}
