<?php declare(strict_types=1);

namespace App\Controller\PasswordReset;

use App\Cnst\PagesCnst;
use App\Command\PasswordReset\PasswordResetConfirmCommand;
use App\Email\PasswordReset\Confirm\EmailPasswordResetConfirmMessage;
use App\Form\Type\PasswordReset\PasswordResetConfirmType;
use App\Repository\EmailSentRepository;
use App\Repository\UserRepository;
use App\Security\User;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

#[AsController]
class PasswordResetConfirmController extends AbstractController
{
  #[Route(
    '/{schema}/password-reset/{confirm_token}',
    name: 'password_reset_confirm',
    methods: ['GET', 'POST'],
    priority: 30,
    requirements: [
      'confirm_token' => '%uuid_base58%',
      'schema'        => '%assert.schema%',
    ],
    defaults: [
      'module'        => 'users',
      'sub_module'    => 'password_reset',
    ],
  )]

  public function __invoke(
    Request $request,
    PasswordHasherFactoryInterface $password_hasher_factory,
    string $confirm_token,
    UserRepository $user_repository,
    EmailSentRepository $email_sent_repository,
    UserCacheService $user_cache_service,
    PageParamsService $pp,
    SessionUserService $su
  ):Response
  {
    $uuid_confirm_token = Uuid::fromBase58($confirm_token);

    $form_disabled = false;
    $success = false;

    if ($pp->edit_en()
      && $confirm_token === PagesCnst::CMS_TOKEN
      && $su->is_admin())
    {
      $user_id = $su->id();
      $form_disabled = true;
    }
    else
    {
      $record = $email_sent_repository->get_with_confirm_token(
        confirm_token: $uuid_confirm_token,
        minutes_exp: 60,
        schema: $pp->schema_o(),
      );

      if ($record === false)
      {
        $this->addFlash('content', 'is_not_found');
      }
      else if ($record['message_class'] !== EmailPasswordResetConfirmMessage::class)
      {
        throw $this->createNotFoundException();
      }
      else if ($record['is_confirmed'])
      {
        $this->addFlash('content', 'is_already_confirmed');
        $this->addFlash('confirmed_at', $record['confirmed_at']);
      }
      else if ($record['is_expired'])
      {
        $this->addFlash('content', 'is_expired');
      }
      else
      {
        $success = true;
      }

      if (!$success)
      {
        return $this->redirectToRoute('password_reset_confirm_unvalid', [
          ...$pp->ary(),
          'confirm_token' => $confirm_token,
        ]);
      }
    }

    $command = new PasswordResetConfirmCommand();

    $form_options = [
      'validation_groups' => ['edit'],
      'disabled' => $form_disabled,
    ];

    $form = $this->createForm(
      type: PasswordResetConfirmType::class,
      data: $command,
      options: $form_options,
    );

    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid()
      && isset($record))
    {
      $confirm_data = $record['confirm_data'];
      $user_id = $confirm_data['user_id'];

      $password_hasher = $password_hasher_factory->getPasswordHasher(new User());
      $hashed_password = $password_hasher->hash($command->password);

      $user_repository->set_password(
        id: $user_id,
        password: $hashed_password,
        schema: $pp->schema_o(),
      );

		  $user_cache_service->clear(
        id: $user_id,
        schema: $pp->schema(),
      );

      $email_sent_repository->set_confirmed(
        confirm_token: $uuid_confirm_token,
        schema: $pp->schema_o(),
      );

      $this->addFlash('content', 'success');
      return $this->redirectToRoute('password_reset_success', $pp->ary());
    }

    return $this->render('password_reset/password_reset_confirm.html.twig', [
      'form'  => $form->createView(),
    ]);
  }
}
