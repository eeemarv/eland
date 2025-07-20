<?php declare(strict_types=1);

namespace App\Controller\PasswordReset;

use App\Email\PasswordReset\Confirm\EmailPasswordResetConfirmMessage;
use App\Repository\EmailSentRepository;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

#[AsController]
class PasswordResetConfirmUnvalidController extends AbstractController
{
  #[Route(
    '/{system}/password-reset/unvalid/{confirm_token}',
    name: 'password_reset_confirm_unvalid',
    methods: ['GET'],
    requirements: [
      'confirm_token' => '%uuid_base58%',
      'system'        => '%assert.system%',
    ],
    defaults: [
      'module'        => 'users',
      'sub_module'    => 'password_reset',
    ],
  )]

  public function __invoke(
    string $confirm_token,
    EmailSentRepository $email_sent_repository,
    PageParamsService $pp,
  ):Response
  {
    $uuid_confirm_token = Uuid::fromBase58($confirm_token);

    $is_not_found = false;
    $is_expired = false;
    $is_already_confirmed = false;

    $record = $email_sent_repository->get_with_confirm_token(
      confirm_token: $uuid_confirm_token,
      minutes_exp: 60,
      schema: $pp->schema_o(),
    );

    if ($record === false)
    {
      $is_not_found = true;
    }
    else if ($record['message_class'] !== EmailPasswordResetConfirmMessage::class)
    {
      $this->createNotFoundException();
    }
    else if ($record['is_confirmed'])
    {
      $is_already_confirmed = true;
    }
    else if ($record['is_expired'])
    {
      $is_expired = true;
    }

    return $this->render('password_reset/password_reset_confirm_unvalid.html.twig', [
      'is_not_found' => $is_not_found,
      'is_already_confirmed' => $is_already_confirmed,
      'confirmed_at'  => $record['confirmed_at'] ?? null,
      'is_expired'    => $is_expired,
    ]);
  }
}
