<?php declare(strict_types=1);

namespace App\Controller\SupportForm;

use App\Routing\AccessLevel;
use App\Routing\AppModule;
use App\Routing\SchemaRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class SupportFormSentController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::User,
    module: AppModule::SupportForm,
    path: '/support/sent',
    name: 'support_form_sent',
    methods: ['GET'],
  )]

  public function __invoke(
  ):Response
  {
    return $this->render('support_form/support_form_sent.html.twig');
  }
}
