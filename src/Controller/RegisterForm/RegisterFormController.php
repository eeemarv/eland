<?php declare(strict_types=1);

namespace App\Controller\RegisterForm;

use App\Command\RegisterForm\RegisterFormCommand;
use App\Email\RegisterForm\Confirm\EmailRegisterFormConfirmMessage;
use App\Form\Type\RegisterForm\RegisterFormType;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
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
    ConfigService $config_service,
    MessageBusInterface $bus,
    PageParamsService $pp
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'register_form.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException('Register form not enabled.');
    }

    $postcode_enabled = $config_service->get_bool(
      config_id: 'users.fields.postcode.enabled',
      schema: $pp->schema_o(),
    );

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
      $postcode = $postcode_enabled ? $command->postcode : null;

      $full_name = $first_name . ' ' . $last_name;

      $logger->info('Registration request for ' .
        $email, ['schema' => $pp->schema()]);

      $m_confirm = new EmailRegisterFormConfirmMessage(
        to: new Address($email, $full_name),
        first_name: $command->first_name,
        last_name: $command->last_name,
        full_name: $full_name,
        postcode: $postcode,
        phone: $command->phone,
        mobile: $command->mobile,
        schema: $pp->schema_o(),
      );
      $bus->dispatch($m_confirm);

      $this->addFlash('content', 'link_sent');

      return $this->redirectToRoute('register_form_link_sent', $pp->ary());
    }

    return $this->render('register_form/register_form.html.twig', [
      'form'  => $form->createView(),
    ]);
  }
}
