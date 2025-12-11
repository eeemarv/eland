<?php declare(strict_types=1);

namespace App\Controller\PasswordReset;

use App\Command\PasswordReset\PasswordResetCommand;
use App\Email\PasswordReset\Confirm\EmailPasswordResetConfirmMessage;
use App\Form\Type\PasswordReset\PasswordResetType;
use App\Render\AccountRender;
use App\Repository\UserRepository;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class PasswordResetController extends AbstractController
{
  #[Route(
    '/{schema}/password-reset',
    name: 'password_reset',
    methods: ['GET', 'POST'],
    priority: 30,
    requirements: [
      'schema'        => '%assert.schema%',
    ],
    defaults: [
      'module'        => 'users',
      'sub_module'    => 'password_reset',
    ],
  )]

  public function __invoke(
    Request $request,
    UserRepository $user_repository,
    AccountRender $account_render,
    MessageBusInterface $bus,
    PageParamsService $pp
  ):Response
  {
    $command = new PasswordResetCommand();

    $form_options = [
      'validation_groups' => ['send'],
    ];

    $form = $this->createForm(PasswordResetType::class, $command, $form_options);

    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $command = $form->getData();
      $email = strtolower($command->email);

      $user_id = $user_repository->get_active_id_by_email(
        email: $email,
        schema: $pp->schema_o(),
      );

      $account_str = $account_render->get_str($user_id, $pp->schema());

      $m_confirm = new EmailPasswordResetConfirmMessage(
        to: new Address($email, $account_str),
        user_id: $user_id,
        schema: $pp->schema_o(),
      );
      $bus->dispatch($m_confirm);

      $this->addFlash('content', 'link_sent');

      return $this->redirectToRoute('password_reset_link_sent', $pp->ary());
    }

    return $this->render('password_reset/password_reset.html.twig', [
      'form'  => $form->createView(),
    ]);
  }
}
