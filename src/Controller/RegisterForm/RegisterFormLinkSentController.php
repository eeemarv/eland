<?php declare(strict_types=1);

namespace App\Controller\RegisterForm;

use App\Routing\AccessLevel;
use App\Routing\AppModule;
use App\Routing\SchemaRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class RegisterFormLinkSentController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Anonymous,
    module: AppModule::RegisterForm,
    path: '/register/link_sent',
    name: 'register_form_link_sent',
    methods: ['GET'],
  )]

  public function __invoke(
  ):Response
  {
    return $this->render('register_form/register_form_link_sent.html.twig', []);
  }
}
