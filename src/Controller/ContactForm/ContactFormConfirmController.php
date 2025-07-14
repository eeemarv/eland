<?php declare(strict_types=1);

namespace App\Controller\ContactForm;

use App\Email\ContactForm\Admin\EmailContactFormAdminMessage;
use App\Email\ContactForm\Success\EmailContactFormSuccessMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\DataTokenService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ContactFormConfirmController extends AbstractController
{
  #[Route(
    '/{system}/contact/{token}',
    name: 'contact_form_confirm',
    methods: ['GET'],
    priority: 10,
    requirements: [
      'token'         => '%assert.token%',
      'system'        => '%assert.system%',
    ],
    defaults: [
      'module'        => 'contact_form',
    ],
  )]

  public function __invoke(
    string $token,
    ConfigService $config_service,
    DataTokenService $data_token_service,
    PageParamsService $pp,
    MessageBusInterface $bus,
  ):Response
  {
    if (!$config_service->get_bool('contact_form.enabled', $pp->schema()))
    {
      throw new NotFoundHttpException('Contact form module not enabled.');
    }

    $data = $data_token_service->retrieve($token, 'contact_form', $pp->schema());

    if (!$data)
    {
      return $this->render('contact_form/contact_form_confirm.html.twig', [
        'success' => false,
      ]);
      $this->addFlash('error', 'Ongeldig of verlopen token.');
      return $this->redirectToRoute('contact_form', $pp->ary());
    }

    $sender_email_address = new Address($data['email']);

    $m_contact = new EmailContactFormAdminMessage(
      reply_to: $sender_email_address,
      message: $data['message'],
      agent: $data['agent'],
      ip: $data['ip'],
      schema: $pp->schema_o(),
    );
    $bus->dispatch($m_contact);

    $m_success = new EmailContactFormSuccessMessage(
      to: $sender_email_address,
      message: $data['message'],
      schema: $pp->schema_o(),
    );
    $bus->dispatch($m_success);

    $data_token_service->del($token, 'contact_form', $pp->schema());

    return $this->render('contact_form/contact_form_confirm.html.twig', [
      'success' => true,
    ]);


    $this->addFlash('success', 'Je bericht werd succesvol verzonden.');
    return $this->redirectToRoute('contact_form', $pp->ary());
  }
}
