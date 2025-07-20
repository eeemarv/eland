<?php declare(strict_types=1);

namespace App\Controller\PasswordReset;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class PasswordResetSuccessController extends AbstractController
{
  #[Route(
    '/{system}/password-reset/success',
    name: 'password_reset_success',
    methods: ['GET'],
    requirements: [
      'system'        => '%assert.system%',
    ],
    defaults: [
      'module'        => 'users',
      'sub_module'    => 'password_reset',
    ],
  )]

  public function __invoke():Response
  {
    return $this->render('password_reset/password_reset_success.html.twig');
  }
}
