<?php declare(strict_types=1);

namespace App\Controller\PasswordReset;

use App\Routing\AccessLevel;
use App\Routing\AppModule;
use App\Routing\SchemaRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class PasswordResetLinkSentController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Anonymous,
    module: AppModule::PasswordReset,
    path: '/password-reset/link-sent',
    name: 'password_reset_link_sent',
    methods: ['GET'],
  )]

  public function __invoke():Response
  {
    return $this->render('password_reset/password_reset_link_sent.html.twig', [
    ]);
  }
}
