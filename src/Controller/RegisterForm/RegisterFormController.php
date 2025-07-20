<?php declare(strict_types=1);

namespace App\Controller\RegisterForm;

use App\Command\RegisterForm\RegisterFormCommand;
use App\Email\RegisterForm\RegisterFormConfirm\EmailRegisterFormConfirmMessage;
use App\Form\Type\RegisterForm\RegisterFormType;
use App\Repository\EmailSentRepository;
use App\Service\ConfigService;
use App\Service\DataTokenService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class RegisterFormController extends AbstractController
{
  #[Route(
    '/{system}/register',
    name: 'register_form',
    methods: ['GET', 'POST'],
    priority: 30,
    requirements: [
      'system'        => '%assert.system%',
    ],
    defaults: [
      'module'        => 'register_form',
    ],
  )]

  public function __invoke(
    Request $request,
    LoggerInterface $logger,
    DataTokenService $data_token_service,
    ConfigService $config_service,
    EmailSentRepository $email_sent_repository,
    MessageBusInterface $bus,
    PageParamsService $pp
  ):Response
  {
    if (!$config_service->get_bool('register_form.enabled', $pp->schema()))
    {
      $this->createNotFoundException('Register form not enabled.');
    }

    $session = $request->getSession();
    if ($session instanceof Session)
    {
      $flash_bag = $session->getFlashBag();
      if ($flash_bag->peek('content'))
      {
        /** no form, just a flash message */
        return $this->render('register_form/register_form.html.twig', []);
      }
    }

    $postcode_enabled = $config_service->get_bool('users.fields.postcode.enabled', $pp->schema());

    $command = new RegisterFormCommand();

    $form_options = [
      'validation_groups' => ['send'],
    ];

    $form = $this->createForm(RegisterFormType::class, $command, $form_options);
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $command = $form->getData();

      $email = strtolower($command->email);
      $first_name = $command->first_name;
      $last_name = $command->last_name;
      $postcode = $command->postcode;
      $phone = $command->phone;
      $mobile = $command->mobile;

      $full_name = $first_name . ' ' . $last_name;

      $logger->info('Registration request for ' .
        $email, ['schema' => $pp->schema()]);

      $reg = [
        'email'         => $email,
        'first_name'    => $first_name,
        'last_name'     => $last_name,
        'full_name'     => $full_name,
        'tel'           => $phone,
        'gsm'           => $mobile,
      ];

      if ($postcode_enabled)
      {
        $reg['postcode'] = $postcode;
      }

      $token = $data_token_service->store($reg,
        'register_form', $pp->schema(), 86400); // 1 day

/*
      $mail_queue->queue([
        'schema'	=> $pp->schema(),
        'to' 		=> [new Address($email, $full_name)],
        'vars'		=> ['token' => $token],
        'template'	=> 'register/confirm',
      ], 10000);
*/

      $m_confirm = new EmailRegisterFormConfirmMessage(
        to: new Address($email, $full_name),
        token: $token,
        schema: $pp->schema_o(),
      );
      $bus->dispatch($m_confirm);

      $this->addFlash('success', 'Open je E-mailbox en klik op de
        bevestigingslink in de E-mail die we naar je gestuurd
        hebben om je inschrijving te voltooien.');

      $this->addFlash('content', 'open_email');

      return $this->redirectToRoute('register_form', $pp->ary());
    }

    return $this->render('register_form/register_form.html.twig', [
      'form'  => $form->createView(),
    ]);
  }
}
