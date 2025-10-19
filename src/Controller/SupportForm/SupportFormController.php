<?php declare(strict_types=1);

namespace App\Controller\SupportForm;

use App\Command\SupportForm\SupportFormCommand;
use App\Email\SupportForm\Admin\EmailSupportFormAdminMessage;
use App\Email\SupportForm\Copy\EmailSupportFormCopyMessage;
use App\Form\Type\SupportForm\SupportFormType;
use App\Service\ConfigService;
use App\Service\MailAddrSystemService;
use App\Service\MailAddrUserService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class SupportFormController extends AbstractController
{
  #[Route(
    '/{system}/{role_short}/support',
    name: 'support_form',
    methods: ['GET', 'POST'],
    priority: 20,
    requirements: [
      'system'        => '%assert.system%',
      'role_short'    => '%assert.role_short.user%',
    ],
    defaults: [
      'module'        => 'support_form',
    ],
  )]

  public function __invoke(
    Request $request,
    ConfigService $config_service,
    MailAddrUserService $mail_addr_user_service,
    MessageBusInterface $bus,
    PageParamsService $pp,
    SessionUserService $su,
    MailAddrSystemService $mail_addr_system_service
  ):Response
  {
    if (!$config_service->get_bool('support_form.enabled', $pp->schema()))
    {
      throw $this->createNotFoundException('Support form not enabled.');
    }

    $is_master = $su->is_master();
    $mail_enabled = $config_service->get_bool('mail.enabled', $pp->schema());
    $support_addr = $mail_addr_system_service->get_support($pp->schema());
    $form_disabled = !$mail_enabled || count($support_addr) < 1 || $is_master;

    $user_email_ary = $is_master ? [] : $mail_addr_user_service->get_active($su->id(), $pp->schema());
    $can_reply = count($user_email_ary) > 0;

    $command = new SupportFormCommand();
    $command->cc = true;

    $form_options = [
      'validation_groups'     => ['send'],
      'disabled'  => $form_disabled,
    ];

    $form = $this->createForm(SupportFormType::class, $command, $form_options);

    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid()
      && !$form_disabled)
    {
      $command = $form->getData();

      if ($command->cc && $can_reply)
      {
        $m_copy = new EmailSupportFormCopyMessage(
          user_id: $su->id(),
          message: $command->message,
          schema: $pp->schema_o(),
        );
        $bus->dispatch($m_copy);
      }

      $m_support = new EmailSupportFormAdminMessage(
        message: $command->message,
        user_id: $su->id(),
        schema: $pp->schema_o(),
      );
      $bus->dispatch($m_support);

      $this->addFlash('content', 'sent');

      return $this->redirectToRoute('support_form_sent', $pp->ary());
    }

    if ($is_master)
    {
      $this->addFlash('warning', ['key' => 'flash.email.not_for_master']);
    }
    else
    {
      if (!$can_reply)
      {
        $this->addFlash('warning', ['key' => 'flash.email.missing_for_your_account']);
      }
    }

    if (!$mail_enabled)
    {
      $this->addFlash('warning', ['key' => 'flash.email.functions_disabled']);
    }
    else if (count($support_addr) < 1)
    {
      $this->addFlash('warning', ['key' => 'flash.email.no_support_config']);
    }

    return $this->render('support_form/support_form.html.twig', [
      'form'      => $form->createView(),
      'can_reply' => $can_reply,
    ]);
  }
}
